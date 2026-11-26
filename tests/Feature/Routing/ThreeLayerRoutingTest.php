<?php

use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Support\RouteLoader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\ScopeSessions;

uses(RefreshDatabase::class);

/** @var array<int, Tenant> */
$createdTenants = [];

afterEach(function () use (&$createdTenants) {
    if (tenancy()->initialized) {
        tenancy()->end();
    }

    DB::purge('tenant');

    foreach ($createdTenants as $tenant) {
        $tenant->delete();
    }

    $createdTenants = [];
});

function makeRoutingTenant(array &$createdTenants, string $slug = 'instansi-satu'): Tenant
{
    $tenant = Tenant::create([
        'name' => 'Instansi '.$slug,
        'slug' => $slug,
        'is_active' => true,
    ]);
    $tenant->domains()->create(['domain' => $slug]);
    $createdTenants[] = $tenant;

    return $tenant;
}

it('serves central routes across central_domains and blocks them on tenant subdomains', function () use (&$createdTenants) {
    $tenant = makeRoutingTenant($createdTenants);
    $user = User::factory()->create();

    foreach (['presentify.test', 'app.presentify.test', 'localhost', '127.0.0.1'] as $centralHost) {
        $this->withoutVite()
            ->get("http://{$centralHost}/")
            ->assertOk();

        $this->actingAs($user)
            ->withoutVite()
            ->get("http://{$centralHost}/workspace")
            ->assertOk();
    }

    $this->get("http://{$tenant->slug}.presentify.test/")->assertNotFound();
    $this->actingAs($user)->get("http://{$tenant->slug}.presentify.test/workspace")->assertNotFound();
    $this->get("http://{$tenant->slug}.presentify.test/auth/otp")->assertNotFound();
});

it('serves tenant routes on tenant subdomains and rejects central and unknown domains', function () use (&$createdTenants) {
    $tenant = makeRoutingTenant($createdTenants);

    $this->get("http://{$tenant->slug}.presentify.test/sso/login")
        ->assertRedirect();

    $this->get('http://presentify.test/sso/login')->assertNotFound();
    $this->get('http://app.presentify.test/sso/login')->assertNotFound();
    $this->get('http://localhost/sso/login')->assertNotFound();
    $this->get('http://unknown-tenant.presentify.test/sso/login')->assertNotFound();
});

it('serves universal routes on both central and tenant subdomains while returning 404 for unknown domains', function () use (&$createdTenants) {
    $tenant = makeRoutingTenant($createdTenants);
    $user = User::factory()->create();

    TenantUser::create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'is_active' => true,
    ]);

    foreach (['presentify.test', 'app.presentify.test', 'localhost'] as $centralHost) {
        $this->actingAs($user)
            ->withoutVite()
            ->get("http://{$centralHost}/dashboard")
            ->assertOk();

        $this->actingAs($user)
            ->withoutVite()
            ->get("http://{$centralHost}/settings/profile")
            ->assertOk();
    }

    $this->actingAs($user)
        ->withSession([ScopeSessions::$tenantIdKey => $tenant->id])
        ->withoutVite()
        ->get("http://{$tenant->slug}.presentify.test/dashboard")
        ->assertOk();

    $this->actingAs($user)
        ->withSession([ScopeSessions::$tenantIdKey => $tenant->id])
        ->withoutVite()
        ->get("http://{$tenant->slug}.presentify.test/settings/profile")
        ->assertOk();

    // Unknown subdomains and third-party domains must return 404 even before auth redirect.
    $this->get('http://unknown-tenant.presentify.test/dashboard')->assertNotFound();
    $this->get('http://evil.example.com/dashboard')->assertNotFound();
    $this->actingAs($user)->get('http://unknown-tenant.presentify.test/settings/profile')->assertNotFound();
});

it('keeps route folders flat at 1-level depth and loads no Route::domain bindings', function () {
    foreach (['central', 'tenant', 'universal'] as $context) {
        $dir = base_path("routes/{$context}");
        expect(File::isDirectory($dir))->toBeTrue();
        expect(File::directories($dir))->toBeEmpty();
    }

    foreach (Route::getRoutes() as $route) {
        expect($route->getDomain())->toBeNull();
    }
});

it('ignores nested subdirectories in RouteLoader::load', function () {
    $tempContext = '_test_flat_loader_'.uniqid();
    $baseDir = base_path("routes/{$tempContext}");
    $nestedDir = "{$baseDir}/nested";

    File::ensureDirectoryExists($nestedDir);
    File::put("{$baseDir}/top.php", "<?php\n\\Illuminate\\Support\\Facades\\Route::get('/_test-top-route', fn () => 'ok')->name('test.top');\n");
    File::put("{$nestedDir}/ignored.php", "<?php\n\\Illuminate\\Support\\Facades\\Route::get('/_test-nested-route', fn () => 'nope')->name('test.nested');\n");

    try {
        RouteLoader::load($tempContext);
        Route::getRoutes()->refreshNameLookups();

        expect(Route::has('test.top'))->toBeTrue()
            ->and(Route::has('test.nested'))->toBeFalse();
    } finally {
        File::deleteDirectory($baseDir);
    }
});

it('allows confirm password and security fortify routes on known tenant subdomains while blocking guest auth routes and unknown domains', function () use (&$createdTenants) {
    $tenant = makeRoutingTenant($createdTenants);
    $user = User::factory()->create();

    TenantUser::create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->withSession([ScopeSessions::$tenantIdKey => $tenant->id])
        ->withoutVite()
        ->get("http://{$tenant->slug}.presentify.test/settings/security")
        ->assertRedirect("http://{$tenant->slug}.presentify.test/user/confirm-password");

    $this->actingAs($user)
        ->withSession([ScopeSessions::$tenantIdKey => $tenant->id])
        ->withoutVite()
        ->get("http://{$tenant->slug}.presentify.test/user/confirm-password")
        ->assertOk();

    $this->actingAs($user)
        ->withSession([ScopeSessions::$tenantIdKey => $tenant->id])
        ->post("http://{$tenant->slug}.presentify.test/user/confirm-password", [
            'password' => 'password',
        ])
        ->assertRedirect();

    $this->get("http://{$tenant->slug}.presentify.test/login")->assertNotFound();
    $this->actingAs($user)->get('http://unknown-tenant.presentify.test/user/confirm-password')->assertNotFound();
});
