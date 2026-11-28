<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\Tenant\ThemeCustomizerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Middleware\ScopeSessions;

uses(RefreshDatabase::class);

/** @var array<int, Tenant> */
$createdTenants = [];

afterEach(function () use (&$createdTenants) {
    if (tenancy()->initialized) {
        tenancy()->end();
    }

    DB::purge('tenant');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach ($createdTenants as $tenant) {
        $tenant->delete();
    }

    $createdTenants = [];
});

function makeTenantThemeSubject(array &$createdTenants, string $slugPrefix = 'ttheme'): Tenant
{
    $slug = $slugPrefix.uniqid();
    $tenant = Tenant::create([
        'name' => 'Instansi '.$slug,
        'slug' => $slug,
        'is_active' => true,
    ]);
    $tenant->domains()->create(['domain' => $slug]);
    $createdTenants[] = $tenant;

    return $tenant;
}

function attachTenantThemeUser(Tenant $tenant, ?string $roleName = null, array $extraPermissions = []): User
{
    $user = User::factory()->create();

    TenantUser::create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'is_active' => true,
    ]);

    tenancy()->initialize($tenant);

    if ($roleName !== null) {
        $role = Role::query()->where('name', $roleName)->where('guard_name', 'web')->firstOrFail();
        $user->assignRole($role);
    }

    foreach ($extraPermissions as $permissionName) {
        $permission = Permission::query()->firstOrCreate(
            ['name' => $permissionName, 'guard_name' => 'web'],
            ['group' => 'Instansi'],
        );
        $user->givePermissionTo($permission);
    }

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    tenancy()->end();

    return $user;
}

it('validates and formats hex and hsl color palettes and radius in ThemeCustomizerService', function () {
    $service = app(ThemeCustomizerService::class);

    expect($service->isValidColor('#0f766e'))->toBeTrue()
        ->and($service->isValidColor('#ABC'))->toBeTrue()
        ->and($service->isValidColor('hsl(173 80% 26%)'))->toBeTrue()
        ->and($service->isValidColor('hsl(173, 80%, 26%)'))->toBeTrue()
        ->and($service->isValidColor('hsl(420 80% 26%)'))->toBeFalse()
        ->and($service->isValidColor('red'))->toBeFalse()
        ->and($service->isValidColor('#12345'))->toBeFalse();

    expect($service->normalizeColor('#ABC'))->toBe('#aabbcc')
        ->and($service->normalizeColor('hsl(173, 80%, 26%)'))->toBe('hsl(173 80% 26%)');

    $lightVars = $service->formatCssVariables([
        'primary' => '#0f766e',
        'accent' => '#f0fdfa',
        'radius' => '0.5rem',
    ]);

    expect($lightVars)->toMatchArray([
        '--primary' => '#0f766e',
        '--primary-foreground' => '#ffffff',
        '--sidebar-primary' => '#0f766e',
        '--sidebar-primary-foreground' => '#ffffff',
        '--accent' => '#f0fdfa',
        '--accent-foreground' => '#09090b',
        '--ring' => '#0f766e',
        '--radius' => '0.5rem',
    ]);

    // Very dark primary must be safely lightened on dark mode while accent darkens cleanly.
    $darkVars = $service->formatCssVariables([
        'primary' => '#1e293b',
        'accent' => '#f1f5f9',
        'radius' => '0.375rem',
    ], true);

    expect($darkVars['--primary'])->not->toBe('#1e293b')
        ->and($darkVars['--primary-foreground'])->toBe('#09090b')
        ->and($darkVars['--accent'])->not->toBe('#f1f5f9')
        ->and($darkVars['--accent-foreground'])->toBe('#ffffff');
});

it('isolates theme endpoints to tenant domains and enforces permissions', function () use (&$createdTenants) {
    $tenant = makeTenantThemeSubject($createdTenants);
    $member = attachTenantThemeUser($tenant, 'member');
    $viewerOnly = attachTenantThemeUser($tenant, null, ['tenant_theme.view']);

    // Guest redirects to tenant SSO login.
    $this->get("http://{$tenant->slug}.presentify.test/tenant/theme")
        ->assertRedirect("http://{$tenant->slug}.presentify.test/sso/login");

    // Central domain must reject tenant-only theme routes with 404.
    $this->actingAs($member)
        ->get('http://presentify.test/tenant/theme')
        ->assertNotFound();

    // Member without permission receives 403.
    $this->actingAs($member)
        ->withSession([ScopeSessions::$tenantIdKey => $tenant->id])
        ->withoutVite()
        ->get("http://{$tenant->slug}.presentify.test/tenant/theme")
        ->assertForbidden();

    // Viewer-only can view page (200) but cannot update or reset theme (403).
    $this->actingAs($viewerOnly)
        ->withSession([ScopeSessions::$tenantIdKey => $tenant->id])
        ->withoutVite()
        ->get("http://{$tenant->slug}.presentify.test/tenant/theme")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/theme/edit')
            ->where('theme.config', null)
        );

    $this->actingAs($viewerOnly)
        ->withSession([ScopeSessions::$tenantIdKey => $tenant->id])
        ->put("http://{$tenant->slug}.presentify.test/tenant/theme", [
            'primary' => '#0f766e',
            'accent' => '#f0fdfa',
            'radius' => '0.5rem',
        ])
        ->assertForbidden();

    $this->actingAs($viewerOnly)
        ->withSession([ScopeSessions::$tenantIdKey => $tenant->id])
        ->delete("http://{$tenant->slug}.presentify.test/tenant/theme")
        ->assertForbidden();
});

it('updates, shares, validates, and resets tenant theme config on sys_tenants', function () use (&$createdTenants) {
    $tenant = makeTenantThemeSubject($createdTenants);
    $admin = attachTenantThemeUser($tenant, 'admin_tenant');

    // Update using HSL primary and 3-digit hex accent.
    $this->actingAs($admin)
        ->withSession([ScopeSessions::$tenantIdKey => $tenant->id])
        ->put("http://{$tenant->slug}.presentify.test/tenant/theme", [
            'primary' => 'hsl(173, 80%, 26%)',
            'accent' => '#DEF',
            'radius' => '0.75rem',
        ])
        ->assertRedirect("http://{$tenant->slug}.presentify.test/tenant/theme");

    $tenant->refresh();

    expect($tenant->theme_config)->toBe([
        'primary' => 'hsl(173 80% 26%)',
        'accent' => '#ddeeff',
        'radius' => '0.75rem',
    ]);

    // Inertia shared props and Blade HTML output include the formatted CSS variables.
    $response = $this->actingAs($admin)
        ->withSession([ScopeSessions::$tenantIdKey => $tenant->id])
        ->withoutVite()
        ->get("http://{$tenant->slug}.presentify.test/dashboard");

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('tenancy.current.theme.config.primary', 'hsl(173 80% 26%)')
            ->where('tenancy.current.theme.css_variables.--primary', 'hsl(173 80% 26%)')
            ->where('tenancy.current.theme.css_variables.--accent', '#ddeeff')
            ->where('tenancy.current.theme.css_variables.--radius', '0.75rem')
        );

    expect($response->getContent())->toContain('id="tenant-theme-initial"')
        ->and($response->getContent())->toContain('--primary: hsl(173 80% 26%);');

    // Validation errors on invalid colors / radius.
    $this->actingAs($admin)
        ->withSession([ScopeSessions::$tenantIdKey => $tenant->id])
        ->put("http://{$tenant->slug}.presentify.test/tenant/theme", [
            'primary' => 'not-a-color',
            'accent' => 'hsl(500 50% 50%)',
            'radius' => '99rem',
        ])
        ->assertSessionHasErrors(['primary', 'accent', 'radius']);

    // Reset clears theme_config and shared CSS variables.
    $this->actingAs($admin)
        ->withSession([ScopeSessions::$tenantIdKey => $tenant->id])
        ->delete("http://{$tenant->slug}.presentify.test/tenant/theme")
        ->assertRedirect("http://{$tenant->slug}.presentify.test/tenant/theme");

    $tenant->refresh();
    expect($tenant->theme_config)->toBeNull();

    $this->actingAs($admin)
        ->withSession([ScopeSessions::$tenantIdKey => $tenant->id])
        ->withoutVite()
        ->get("http://{$tenant->slug}.presentify.test/tenant/theme")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('tenancy.current.theme.config', null)
            ->where('tenancy.current.theme.css_variables', [])
        );
});

it('supports extended style, base color, chart color, fonts, menu style, and preset code round-trip', function () use (&$createdTenants) {
    $service = app(ThemeCustomizerService::class);

    $encoded = $service->encodePresetCode([
        'style' => 'lyra',
        'base_color' => 'stone',
        'theme_color' => 'orange',
        'primary' => '#ea580c',
        'accent' => '#fff7ed',
        'chart_color' => 'orange',
        'heading_font' => 'Oxanium',
        'body_font' => 'Outfit',
        'radius' => '0rem',
        'menu_style' => 'inverted',
        'menu_accent' => 'bold',
    ]);

    expect($encoded)->toStartWith('prs_');

    $decoded = $service->decodePresetCode("--preset {$encoded}");
    expect($decoded)->toMatchArray([
        'style' => 'lyra',
        'base_color' => 'stone',
        'theme_color' => 'orange',
        'primary' => '#ea580c',
        'accent' => '#fff7ed',
        'chart_color' => 'orange',
        'heading_font' => 'Oxanium',
        'body_font' => 'Outfit',
        'radius' => '0rem',
        'menu_style' => 'inverted',
        'menu_accent' => 'bold',
    ])
        ->and($service->decodePresetCode('invalid-code'))->toBeNull();

    $fontUrl = $service->buildFontStylesheetUrl('Oxanium', 'Outfit');
    expect($fontUrl)->toContain('fonts.googleapis.com/css2')
        ->and($fontUrl)->toContain('Oxanium')
        ->and($fontUrl)->toContain('Outfit');

    $tenant = makeTenantThemeSubject($createdTenants, 'tfull');
    $admin = attachTenantThemeUser($tenant, 'admin_tenant');

    $this->actingAs($admin)
        ->withSession([ScopeSessions::$tenantIdKey => $tenant->id])
        ->put("http://{$tenant->slug}.presentify.test/tenant/theme", [
            'style' => 'lyra',
            'base_color' => 'stone',
            'theme_color' => 'orange',
            'primary' => '#ea580c',
            'accent' => '#fff7ed',
            'chart_color' => 'orange',
            'heading_font' => 'Oxanium',
            'body_font' => 'Outfit',
            'radius' => '0rem',
            'menu_style' => 'inverted',
            'menu_accent' => 'bold',
        ])
        ->assertRedirect("http://{$tenant->slug}.presentify.test/tenant/theme");

    $response = $this->actingAs($admin)
        ->withSession([ScopeSessions::$tenantIdKey => $tenant->id])
        ->withoutVite()
        ->get("http://{$tenant->slug}.presentify.test/dashboard");

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('tenancy.current.theme.config.style', 'lyra')
            ->where('tenancy.current.theme.config.base_color', 'stone')
            ->where('tenancy.current.theme.config.heading_font', 'Oxanium')
            ->where('tenancy.current.theme.config.body_font', 'Outfit')
            ->where('tenancy.current.theme.config.menu_style', 'inverted')
            ->where('tenancy.current.theme.config.menu_accent', 'bold')
            ->where('tenancy.current.theme.css_variables.--chart-1', '#f97316')
            ->where('tenancy.current.theme.css_variables.--sidebar-accent', '#ea580c')
            ->where('tenancy.current.theme.data_attributes.style', 'lyra')
        );

    expect($response->getContent())->toContain('data-theme-style="lyra"')
        ->and($response->getContent())->toContain('id="tenant-theme-fonts"')
        ->and($response->getContent())->toContain('Oxanium');

    // Validation errors on invalid extended fields
    $this->actingAs($admin)
        ->withSession([ScopeSessions::$tenantIdKey => $tenant->id])
        ->put("http://{$tenant->slug}.presentify.test/tenant/theme", [
            'primary' => '#ea580c',
            'accent' => '#fff7ed',
            'radius' => '0rem',
            'style' => 'unknown-style',
            'base_color' => 'neon',
            'chart_color' => 'not-valid',
            'heading_font' => 'Comic Sans MS',
            'body_font' => 'Papyrus',
            'menu_style' => 'invalid-menu',
            'menu_accent' => 'invalid-accent',
        ])
        ->assertSessionHasErrors([
            'style',
            'base_color',
            'chart_color',
            'heading_font',
            'body_font',
            'menu_style',
            'menu_accent',
        ]);
});
