<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
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
        File::deleteDirectory(storage_path('tenant'.$tenant->getTenantKey()));
        $tenant->delete();
    }

    $createdTenants = [];
});

function makeTenantProfileSubject(array &$createdTenants, string $slugPrefix = 'tprof'): Tenant
{
    $slug = $slugPrefix.uniqid();
    $tenant = Tenant::create([
        'name' => 'Instansi '.$slug,
        'slug' => $slug,
        'is_active' => true,
        'contact_email' => 'kontak@'.$slug.'.go.id',
    ]);
    $tenant->domains()->create(['domain' => $slug]);
    $createdTenants[] = $tenant;

    return $tenant;
}

function attachTenantProfileUser(Tenant $tenant, ?string $roleName = null, array $extraPermissions = []): User
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

it('isolates tenant profile routes to tenant domains and enforces permissions', function () use (&$createdTenants) {
    $tenant = makeTenantProfileSubject($createdTenants);
    $member = attachTenantProfileUser($tenant, 'member');
    $viewerOnly = attachTenantProfileUser($tenant, null, ['tenant_profile.view']);

    // Unauthenticated guest on tenant domain redirects to SSO login.
    $this->get("http://{$tenant->slug}.presentify.test/tenant/profile")
        ->assertRedirect("http://{$tenant->slug}.presentify.test/sso/login");

    // Central domain must reject tenant-only routes with 404.
    $this->actingAs($member)
        ->get('http://presentify.test/tenant/profile')
        ->assertNotFound();

    // Member without tenant_profile.view gets 403.
    $this->actingAs($member)
        ->withSession([ScopeSessions::$tenantIdKey => $tenant->id])
        ->withoutVite()
        ->get("http://{$tenant->slug}.presentify.test/tenant/profile")
        ->assertForbidden();

    // Viewer-only user can open page (200) but cannot update profile or branding (403).
    $this->actingAs($viewerOnly)
        ->withSession([ScopeSessions::$tenantIdKey => $tenant->id])
        ->withoutVite()
        ->get("http://{$tenant->slug}.presentify.test/tenant/profile")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/profile/edit')
            ->where('profile.slug', $tenant->slug)
        );

    $this->actingAs($viewerOnly)
        ->withSession([ScopeSessions::$tenantIdKey => $tenant->id])
        ->put("http://{$tenant->slug}.presentify.test/tenant/profile", [
            'name' => 'Ubah Tanpa Izin',
        ])
        ->assertForbidden();

    $this->actingAs($viewerOnly)
        ->withSession([ScopeSessions::$tenantIdKey => $tenant->id])
        ->post("http://{$tenant->slug}.presentify.test/tenant/profile/branding", [
            'type' => 'logo',
            'file' => UploadedFile::fake()->image('logo.png', 200, 200),
        ])
        ->assertForbidden();

    $this->actingAs($viewerOnly)
        ->withSession([ScopeSessions::$tenantIdKey => $tenant->id])
        ->delete("http://{$tenant->slug}.presentify.test/tenant/profile/branding/logo")
        ->assertForbidden();
});

it('updates tenant identity metadata in sys_tenants and validates fields', function () use (&$createdTenants) {
    $tenant = makeTenantProfileSubject($createdTenants);
    $admin = attachTenantProfileUser($tenant, 'admin_tenant');
    $originalSlug = $tenant->slug;

    $this->actingAs($admin)
        ->withSession([ScopeSessions::$tenantIdKey => $tenant->id])
        ->put("http://{$tenant->slug}.presentify.test/tenant/profile", [
            'name' => 'Dinas Komunikasi dan Informatika',
            'contact_email' => 'sekretariat@diskominfo.go.id',
            'phone' => '021-3456789',
            'address' => 'Jl. Merdeka Barat No. 9, Jakarta Pusat',
            'website' => 'https://diskominfo.go.id',
            'tagline' => 'Transparan, Cepat, dan Terukur',
        ])
        ->assertRedirect("http://{$tenant->slug}.presentify.test/tenant/profile");

    $tenant->refresh();

    expect($tenant->name)->toBe('Dinas Komunikasi dan Informatika')
        ->and($tenant->slug)->toBe($originalSlug)
        ->and($tenant->contact_email)->toBe('sekretariat@diskominfo.go.id')
        ->and($tenant->phone)->toBe('021-3456789')
        ->and($tenant->address)->toBe('Jl. Merdeka Barat No. 9, Jakarta Pusat')
        ->and($tenant->website)->toBe('https://diskominfo.go.id')
        ->and($tenant->tagline)->toBe('Transparan, Cepat, dan Terukur');

    $this->actingAs($admin)
        ->withSession([ScopeSessions::$tenantIdKey => $tenant->id])
        ->put("http://{$tenant->slug}.presentify.test/tenant/profile", [
            'name' => 'A',
            'contact_email' => 'bukan-email',
            'website' => 'bukan-url',
        ])
        ->assertSessionHasErrors(['name', 'contact_email', 'website']);
});

it('uploads, resizes, serves, replaces, and deletes tenant logo and favicon cleanly', function () use (&$createdTenants) {
    $tenant = makeTenantProfileSubject($createdTenants);
    $admin = attachTenantProfileUser($tenant, 'admin_tenant');

    // Upload oversized logo (1200x600 -> scaled cleanly to width 512, height 256).
    $this->actingAs($admin)
        ->withSession([ScopeSessions::$tenantIdKey => $tenant->id])
        ->post("http://{$tenant->slug}.presentify.test/tenant/profile/branding", [
            'type' => 'logo',
            'file' => UploadedFile::fake()->image('logo-besar.png', 1200, 600),
        ])
        ->assertRedirect("http://{$tenant->slug}.presentify.test/tenant/profile");

    $tenant->refresh();
    $firstLogoPath = $tenant->logo_path;

    expect($firstLogoPath)->toBeString()->toStartWith('branding/logo-');

    tenancy()->initialize($tenant);
    expect(Storage::disk('public')->exists((string) $firstLogoPath))->toBeTrue();
    $logoInfo = getimagesizefromstring((string) Storage::disk('public')->get((string) $firstLogoPath));
    tenancy()->end();

    expect($logoInfo)->not->toBeFalse()
        ->and($logoInfo[0])->toBe(512)
        ->and($logoInfo[1])->toBe(256);

    // Asset endpoint serves logo & Inertia shared props expose logo_url.
    $this->actingAs($admin)
        ->withSession([ScopeSessions::$tenantIdKey => $tenant->id])
        ->get("http://{$tenant->slug}.presentify.test{$tenant->logoUrl()}")
        ->assertOk();

    $this->actingAs($admin)
        ->withSession([ScopeSessions::$tenantIdKey => $tenant->id])
        ->withoutVite()
        ->get("http://{$tenant->slug}.presentify.test/tenant/profile")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('tenancy.current.logo_url', $tenant->logoUrl())
            ->where('profile.logo_url', $tenant->logoUrl())
        );

    // Replace logo -> previous file must be removed and replaced with new one.
    $this->actingAs($admin)
        ->withSession([ScopeSessions::$tenantIdKey => $tenant->id])
        ->post("http://{$tenant->slug}.presentify.test/tenant/profile/branding", [
            'type' => 'logo',
            'file' => UploadedFile::fake()->image('logo-baru.png', 300, 300),
        ])
        ->assertRedirect("http://{$tenant->slug}.presentify.test/tenant/profile");

    $tenant->refresh();
    $secondLogoPath = $tenant->logo_path;

    tenancy()->initialize($tenant);
    expect(Storage::disk('public')->exists((string) $firstLogoPath))->toBeFalse()
        ->and(Storage::disk('public')->exists((string) $secondLogoPath))->toBeTrue();
    tenancy()->end();

    // Upload oversized favicon (512x512 -> scaled cleanly to <= 128x128).
    $this->actingAs($admin)
        ->withSession([ScopeSessions::$tenantIdKey => $tenant->id])
        ->post("http://{$tenant->slug}.presentify.test/tenant/profile/branding", [
            'type' => 'favicon',
            'file' => UploadedFile::fake()->image('favicon.png', 512, 512),
        ])
        ->assertRedirect("http://{$tenant->slug}.presentify.test/tenant/profile");

    $tenant->refresh();
    $faviconPath = $tenant->favicon_path;

    tenancy()->initialize($tenant);
    expect(Storage::disk('public')->exists((string) $faviconPath))->toBeTrue();
    $faviconInfo = getimagesizefromstring((string) Storage::disk('public')->get((string) $faviconPath));
    tenancy()->end();

    expect($faviconInfo)->not->toBeFalse()
        ->and($faviconInfo[0])->toBe(128)
        ->and($faviconInfo[1])->toBe(128);

    // Path traversal or unknown files must return 404.
    $this->get("http://{$tenant->slug}.presentify.test/tenant-assets/../../.env")
        ->assertNotFound();

    // Delete logo and favicon cleanly.
    $this->actingAs($admin)
        ->withSession([ScopeSessions::$tenantIdKey => $tenant->id])
        ->delete("http://{$tenant->slug}.presentify.test/tenant/profile/branding/logo")
        ->assertRedirect("http://{$tenant->slug}.presentify.test/tenant/profile");

    $this->actingAs($admin)
        ->withSession([ScopeSessions::$tenantIdKey => $tenant->id])
        ->delete("http://{$tenant->slug}.presentify.test/tenant/profile/branding/favicon")
        ->assertRedirect("http://{$tenant->slug}.presentify.test/tenant/profile");

    $tenant->refresh();

    tenancy()->initialize($tenant);
    expect($tenant->logo_path)->toBeNull()
        ->and($tenant->favicon_path)->toBeNull()
        ->and(Storage::disk('public')->exists((string) $secondLogoPath))->toBeFalse()
        ->and(Storage::disk('public')->exists((string) $faviconPath))->toBeFalse();
    tenancy()->end();
});
