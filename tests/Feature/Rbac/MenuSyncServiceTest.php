<?php

use App\Models\Menu;
use App\Models\Permission;
use App\Models\Tenant;
use App\Services\Rbac\MenuSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

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

// ----------------------------------------------------------------
// Helpers
// ----------------------------------------------------------------

function makeTenant(array &$createdTenants): Tenant
{
    $slug   = 'test' . uniqid();
    $tenant = Tenant::create([
        'name'     => 'Test Tenant ' . $slug,
        'slug'     => $slug,
        'is_active' => true,
    ]);
    $tenant->domains()->create(['domain' => $slug]);
    $createdTenants[] = $tenant;

    return $tenant;
}

function withTenant(Tenant $tenant, callable $callback): void
{
    tenancy()->initialize($tenant);
    try {
        $callback();
    } finally {
        tenancy()->end();
        DB::purge('tenant');
    }
}

// ----------------------------------------------------------------
// Test cases
// ----------------------------------------------------------------

it('creates all system menus from registry on first sync', function () use (&$createdTenants) {
    $tenant = makeTenant($createdTenants);

    withTenant($tenant, function () {
        $registryCount = count(config('presentify-menus', []));

        app(MenuSyncService::class)->sync();

        expect(Menu::count())->toBe($registryCount);
    });
});

it('creates menu-bound permissions for every registered action', function () use (&$createdTenants) {
    $tenant = makeTenant($createdTenants);

    withTenant($tenant, function () {
        app(MenuSyncService::class)->sync();

        $totalExpected = collect(config('presentify-menus', []))
            ->sum(fn ($entry) => count($entry['permissions']));

        expect(Permission::whereNotNull('menu_id')->count())->toBe($totalExpected);
    });
});

it('assigns correct parent_id for child menus', function () use (&$createdTenants) {
    $tenant = makeTenant($createdTenants);

    withTenant($tenant, function () {
        app(MenuSyncService::class)->sync();

        $child  = Menu::where('key', 'attendance.checkin')->firstOrFail();
        $parent = Menu::where('key', 'attendance')->firstOrFail();

        expect($child->parent_id)->toBe($parent->id);
    });
});

it('does not overwrite custom_label set by tenant on subsequent sync', function () use (&$createdTenants) {
    $tenant = makeTenant($createdTenants);

    withTenant($tenant, function () {
        app(MenuSyncService::class)->sync();

        // Simulate tenant customizing the label
        Menu::where('key', 'attendance')->update(['custom_label' => 'Absensi Pegawai']);

        // Run sync again
        app(MenuSyncService::class)->sync();

        $menu = Menu::where('key', 'attendance')->firstOrFail();
        expect($menu->custom_label)->toBe('Absensi Pegawai');
    });
});

it('does not overwrite custom icon set by tenant on subsequent sync', function () use (&$createdTenants) {
    $tenant = makeTenant($createdTenants);

    withTenant($tenant, function () {
        app(MenuSyncService::class)->sync();

        Menu::where('key', 'dashboard')->update(['icon' => 'home']);

        app(MenuSyncService::class)->sync();

        $menu = Menu::where('key', 'dashboard')->firstOrFail();
        expect($menu->icon)->toBe('home');
    });
});

it('does not overwrite custom sort_order set by tenant on subsequent sync', function () use (&$createdTenants) {
    $tenant = makeTenant($createdTenants);

    withTenant($tenant, function () {
        app(MenuSyncService::class)->sync();

        Menu::where('key', 'reports')->update(['sort_order' => 99]);

        app(MenuSyncService::class)->sync();

        $menu = Menu::where('key', 'reports')->firstOrFail();
        expect($menu->sort_order)->toBe(99);
    });
});

it('sync is idempotent — running twice produces the same count', function () use (&$createdTenants) {
    $tenant = makeTenant($createdTenants);

    withTenant($tenant, function () {
        $service = app(MenuSyncService::class);
        $service->sync();

        $menuCount      = Menu::count();
        $permissionCount = Permission::whereNotNull('menu_id')->count();

        $service->sync();

        expect(Menu::count())->toBe($menuCount)
            ->and(Permission::whereNotNull('menu_id')->count())->toBe($permissionCount);
    });
});

it('updates system-owned label field when registry changes', function () use (&$createdTenants) {
    $tenant = makeTenant($createdTenants);

    withTenant($tenant, function () {
        app(MenuSyncService::class)->sync();

        // Simulate registry label change by directly mutating DB (system field)
        Menu::where('key', 'dashboard')->update(['label' => 'Old Label']);

        app(MenuSyncService::class)->sync();

        $menu = Menu::where('key', 'dashboard')->firstOrFail();
        expect($menu->label)->toBe('Dashboard'); // restored from registry
    });
});

it('creates no global permissions during menu sync', function () use (&$createdTenants) {
    $tenant = makeTenant($createdTenants);

    withTenant($tenant, function () {
        app(MenuSyncService::class)->sync();

        // Global permissions (Tier-2) should come from RbacSeeder, not MenuSyncService
        expect(Permission::whereNull('menu_id')->count())->toBe(0);
    });
});

it('permission name follows key.action convention', function () use (&$createdTenants) {
    $tenant = makeTenant($createdTenants);

    withTenant($tenant, function () {
        app(MenuSyncService::class)->sync();

        $perm = Permission::where('name', 'attendance.checkin.view')->firstOrFail();
        expect($perm->action)->toBe('view')
            ->and($perm->menu_id)->not->toBeNull();
    });
});
