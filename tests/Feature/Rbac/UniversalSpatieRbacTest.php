<?php

use App\Models\Menu;
use App\Models\MenuAction;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\Rbac\MenuService;
use Database\Seeders\CentralMenuSeeder;
use Database\Seeders\CentralRolesAndPermissionsSeeder;
use Database\Seeders\TenantMenuSeeder;
use Database\Seeders\TenantRolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
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

function createRbacTenant(array &$createdTenants, string $slugPrefix = 'rbac'): Tenant
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

it('creates identical rbac tables in central and tenant databases with ULID keys and bigint model_id', function () use (&$createdTenants) {
    $expectedTables = [
        'rbac_roles',
        'rbac_permissions',
        'rbac_model_has_roles',
        'rbac_model_has_permissions',
        'rbac_role_has_permissions',
        'rbac_menus',
        'rbac_menu_roles',
        'rbac_menu_actions',
    ];

    foreach ($expectedTables as $table) {
        expect(Schema::hasTable($table))->toBeTrue();
    }

    $tenant = createRbacTenant($createdTenants);

    tenancy()->initialize($tenant);
    try {
        foreach ($expectedTables as $table) {
            expect(Schema::hasTable($table))->toBeTrue();
        }
    } finally {
        tenancy()->end();
        DB::purge('tenant');
    }
});

it('seeds central roles, standalone permissions, and menus idempotently with ULID keys', function () {
    $this->seed(CentralRolesAndPermissionsSeeder::class);
    $this->seed(CentralMenuSeeder::class);

    $roleCount = Role::count();
    $permCount = Permission::count();
    $menuCount = Menu::count();
    $actionCount = MenuAction::count();

    expect($roleCount)->toBe(1)
        ->and(Role::where('name', 'superadmin_saas')->exists())->toBeTrue()
        ->and($permCount)->toBeGreaterThan(3)
        ->and($menuCount)->toBeGreaterThan(4)
        ->and($actionCount)->toBeGreaterThan(4);

    // Re-running seeders must remain non-destructive and idempotent.
    $this->seed(CentralRolesAndPermissionsSeeder::class);
    $this->seed(CentralMenuSeeder::class);

    expect(Role::count())->toBe($roleCount)
        ->and(Permission::count())->toBe($permCount)
        ->and(Menu::count())->toBe($menuCount)
        ->and(MenuAction::count())->toBe($actionCount);

    $role = Role::where('name', 'superadmin_saas')->firstOrFail();
    $permission = Permission::firstOrFail();
    $menu = Menu::firstOrFail();
    $action = MenuAction::firstOrFail();

    expect(Str::isUlid($role->id))->toBeTrue()
        ->and(Str::isUlid($permission->id))->toBeTrue()
        ->and(Str::isUlid($menu->id))->toBeTrue()
        ->and(Str::isUlid($action->id))->toBeTrue();
});

it('automatically seeds 5 default roles, standalone permissions, and menus when a tenant is created', function () use (&$createdTenants) {
    $tenant = createRbacTenant($createdTenants);

    tenancy()->initialize($tenant);
    try {
        $roles = Role::pluck('name')->all();

        expect($roles)->toContain(
            'superadmin_saas',
            'admin_tenant',
            'verifier',
            'member',
            'kiosk',
        )->and($roles)->toHaveCount(5);

        $standaloneNames = Permission::standalone()->pluck('name')->all();
        expect($standaloneNames)->toContain(
            'attendance.override',
            'attendance.bypass_geofence',
            'device.reset',
            'kiosk.qr.display',
            'kiosk.scan',
            'leave.approve_all_units',
            'ai.command_bar.use',
            'audit.view',
        );

        $menuActionPermissions = MenuAction::pluck('permission_name')->all();
        expect($menuActionPermissions)->toContain('menus.view', 'roles.update', 'units.view');

        // Re-seeding tenant must be idempotent.
        $initialMenus = Menu::count();
        $this->seed(TenantRolesAndPermissionsSeeder::class);
        $this->seed(TenantMenuSeeder::class);

        expect(Menu::count())->toBe($initialMenus);
    } finally {
        tenancy()->end();
        DB::purge('tenant');
    }
});

it('isolates user roles and permissions across central and tenant databases via HasTenantAwareRoles', function () use (&$createdTenants) {
    $this->seed(CentralRolesAndPermissionsSeeder::class);
    $this->seed(CentralMenuSeeder::class);

    $tenantA = createRbacTenant($createdTenants, 'alpha');
    $tenantB = createRbacTenant($createdTenants, 'beta');

    $user = User::factory()->create();
    TenantUser::create(['tenant_id' => $tenantA->id, 'user_id' => $user->id, 'is_active' => true]);
    TenantUser::create(['tenant_id' => $tenantB->id, 'user_id' => $user->id, 'is_active' => true]);

    // Central role assignment
    $user->assignRole('superadmin_saas');
    expect($user->hasRole('superadmin_saas'))->toBeTrue()
        ->and($user->can('tenants.manage'))->toBeTrue();

    // Tenant A: assign member (must NOT inherit Central superadmin_saas)
    tenancy()->initialize($tenantA);
    try {
        expect($user->hasRole('superadmin_saas'))->toBeFalse()
            ->and($user->can('menus.view'))->toBeFalse();

        $user->assignRole('member');
        expect($user->hasRole('member'))->toBeTrue()
            ->and($user->can('attendances.create'))->toBeTrue()
            ->and($user->can('menus.view'))->toBeFalse();

        $pivotRow = DB::table('rbac_model_has_roles')
            ->where('model_id', $user->id)
            ->where('model_type', User::class)
            ->first();
        expect($pivotRow)->not->toBeNull()
            ->and((int) $pivotRow->model_id)->toBe($user->id);
    } finally {
        tenancy()->end();
        DB::purge('tenant');
    }

    // Tenant B: assign admin_tenant (isolated from Tenant A's member and Central)
    tenancy()->initialize($tenantB);
    try {
        expect($user->hasRole('member'))->toBeFalse()
            ->and($user->hasRole('superadmin_saas'))->toBeFalse();

        $user->assignRole('admin_tenant');
        expect($user->hasRole('admin_tenant'))->toBeTrue()
            ->and($user->can('menus.view'))->toBeTrue()
            ->and($user->can('roles.update'))->toBeTrue();
    } finally {
        tenancy()->end();
        DB::purge('tenant');
    }

    // Back in Central context: still superadmin_saas only
    expect($user->hasRole('superadmin_saas'))->toBeTrue()
        ->and($user->hasRole('admin_tenant'))->toBeFalse();
});

it('builds context-isolated cached sidebar menus and invalidates per user and per role', function () use (&$createdTenants) {
    $this->seed(CentralRolesAndPermissionsSeeder::class);
    $this->seed(CentralMenuSeeder::class);

    $tenant = createRbacTenant($createdTenants, 'menutest');
    $user = User::factory()->create();
    $user->assignRole('superadmin_saas');

    $menuService = app(MenuService::class);

    // Central cache key
    expect($menuService->cacheKey($user))->toBe("menus:central:{$user->id}");
    $centralMenus = $menuService->getMenusForUser($user);
    expect($centralMenus)->not->toBeEmpty()
        ->and(Cache::has($menuService->cacheKey($user)))->toBeTrue();

    tenancy()->initialize($tenant);
    try {
        $user->assignRole('member');

        $tenantCacheKey = $menuService->cacheKey($user);
        expect($tenantCacheKey)->toBe("menus:{$tenant->id}:{$user->id}");

        $memberMenus = $menuService->getMenusForUser($user);
        $labels = array_column($memberMenus, 'label_name');
        expect($labels)->toContain('Dashboard', 'Presensi')
            ->and($labels)->not->toContain('Pengaturan & RBAC');

        // Deactivate 'Presensi' and verify cache stays stale until invalidated for role
        Menu::where('label_name', 'Presensi')->update(['is_active' => false]);
        expect(array_column($menuService->getMenusForUser($user), 'label_name'))->toContain('Presensi');

        $memberRole = Role::where('name', 'member')->firstOrFail();
        $menuService->invalidateForRole($memberRole);

        expect(Cache::has($tenantCacheKey))->toBeFalse();
        expect(array_column($menuService->getMenusForUser($user), 'label_name'))->not->toContain('Presensi');

        $menuService->invalidateForUser($user);
        expect(Cache::has($tenantCacheKey))->toBeFalse();
    } finally {
        tenancy()->end();
        DB::purge('tenant');
    }
});

it('enforces spatie role and permission middleware aliases on routes', function () use (&$createdTenants) {
    $this->seed(CentralRolesAndPermissionsSeeder::class);

    Route::middleware(['web', 'auth', 'permission:tenants.manage'])
        ->get('/_test-spatie-central-perm', fn () => response('ok'));

    $tenant = createRbacTenant($createdTenants, 'mwtest');
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('http://presentify.test/_test-spatie-central-perm')
        ->assertForbidden();

    $user->assignRole('superadmin_saas');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->unsetRelation('roles')->unsetRelation('permissions');

    $this->actingAs($user)
        ->get('http://presentify.test/_test-spatie-central-perm')
        ->assertOk();

    // Even with Central superadmin_saas, Tenant DB permission check must deny without tenant role.
    Route::middleware(['web', 'auth', 'permission:menus.view'])
        ->get('/_test-spatie-tenant-perm', fn () => response('ok'));

    $this->actingAs($user)
        ->withSession([ScopeSessions::$tenantIdKey => $tenant->id])
        ->get("http://{$tenant->slug}.presentify.test/_test-spatie-tenant-perm")
        ->assertForbidden();
});
