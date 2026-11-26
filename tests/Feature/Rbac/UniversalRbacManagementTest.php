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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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

function makeUniversalRbacTenant(array &$createdTenants, string $slugPrefix = 'urbac'): Tenant
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

it('enforces auth redirect and 403 permission checks across central and tenant contexts', function () use (&$createdTenants) {
    $this->seed(CentralRolesAndPermissionsSeeder::class);
    $this->seed(CentralMenuSeeder::class);

    $tenant = makeUniversalRbacTenant($createdTenants);
    $plainUser = User::factory()->create();

    TenantUser::create([
        'tenant_id' => $tenant->id,
        'user_id' => $plainUser->id,
        'is_active' => true,
    ]);

    // Guest redirects
    $this->get('http://presentify.test/rbac/menus')->assertRedirect('http://presentify.test/login');
    $this->get("http://{$tenant->slug}.presentify.test/rbac/menus")->assertRedirect("http://{$tenant->slug}.presentify.test/sso/login");

    // Plain user forbidden on both Central and Tenant
    $this->actingAs($plainUser)->get('http://presentify.test/rbac/menus')->assertForbidden();
    $this->actingAs($plainUser)->get('http://presentify.test/rbac/permissions')->assertForbidden();
    $this->actingAs($plainUser)->get('http://presentify.test/rbac/roles')->assertForbidden();

    $this->actingAs($plainUser)
        ->withSession([ScopeSessions::$tenantIdKey => $tenant->id])
        ->get("http://{$tenant->slug}.presentify.test/rbac/menus")
        ->assertForbidden();

    // End leftover tenant context from previous HTTP request before assigning Central role.
    tenancy()->end();
    DB::purge('tenant');

    // Central superadmin_saas allowed on Central, but still forbidden on Tenant without tenant role
    $plainUser->assignRole('superadmin_saas');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $plainUser->unsetRelation('roles')->unsetRelation('permissions');

    $this->actingAs($plainUser)
        ->withoutVite()
        ->get('http://presentify.test/rbac/menus')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('universal/rbac/menu/index'));

    $this->actingAs($plainUser)
        ->withSession([ScopeSessions::$tenantIdKey => $tenant->id])
        ->get("http://{$tenant->slug}.presentify.test/rbac/menus")
        ->assertForbidden();
});

it('supports menu CRUD, toggle status, reorder, and delete in tenant context with cache invalidation', function () use (&$createdTenants) {
    $tenant = makeUniversalRbacTenant($createdTenants);
    $admin = User::factory()->create();
    TenantUser::create(['tenant_id' => $tenant->id, 'user_id' => $admin->id, 'is_active' => true]);

    tenancy()->initialize($tenant);
    try {
        $admin->assignRole('admin_tenant');
    } finally {
        tenancy()->end();
        DB::purge('tenant');
    }

    $baseUrl = "http://{$tenant->slug}.presentify.test";

    // Create menu
    $this->actingAs($admin)
        ->withSession([ScopeSessions::$tenantIdKey => $tenant->id])
        ->post("{$baseUrl}/rbac/menus", [
            'parent_id' => null,
            'sort_num' => 99,
            'icon' => 'Calendar',
            'label_name' => 'Jadwal Shift',
            'controller_name' => 'ShiftController',
            'route_name' => 'shifts.index',
            'url' => '/shifts',
            'permission_prefix' => 'shifts',
            'is_active' => true,
            'is_divider' => false,
        ])
        ->assertRedirect("{$baseUrl}/rbac/menus");

    tenancy()->initialize($tenant);
    try {
        $createdMenu = Menu::where('permission_prefix', 'shifts')->firstOrFail();
        expect($createdMenu->label_name)->toBe('Jadwal Shift');

        // Warm menu cache
        $menuService = app(MenuService::class);
        $menuService->getMenusForUser($admin);
        expect(Cache::has($menuService->cacheKey($admin)))->toBeTrue();
    } finally {
        tenancy()->end();
        DB::purge('tenant');
    }

    // Update menu
    $this->actingAs($admin)
        ->withSession([ScopeSessions::$tenantIdKey => $tenant->id])
        ->put("{$baseUrl}/rbac/menus/{$createdMenu->id}", [
            'parent_id' => null,
            'sort_num' => 88,
            'icon' => 'CalendarClock',
            'label_name' => 'Jadwal Kerja',
            'controller_name' => 'ShiftController',
            'route_name' => 'shifts.index',
            'url' => '/shifts',
            'permission_prefix' => 'work_shifts',
            'is_active' => true,
            'is_divider' => false,
        ])
        ->assertRedirect("{$baseUrl}/rbac/menus");

    // Toggle status
    $this->actingAs($admin)
        ->withSession([ScopeSessions::$tenantIdKey => $tenant->id])
        ->patch("{$baseUrl}/rbac/menus/{$createdMenu->id}/toggle-status")
        ->assertRedirect();

    tenancy()->initialize($tenant);
    try {
        $refreshed = $createdMenu->fresh();
        expect($refreshed)->not->toBeNull()
            ->and($refreshed->label_name)->toBe('Jadwal Kerja')
            ->and($refreshed->is_active)->toBeFalse()
            ->and(Cache::has(app(MenuService::class)->cacheKey($admin)))->toBeFalse();
    } finally {
        tenancy()->end();
        DB::purge('tenant');
    }

    // Reorder menu
    $this->actingAs($admin)
        ->withSession([ScopeSessions::$tenantIdKey => $tenant->id])
        ->post("{$baseUrl}/rbac/menus/reorder", [
            'items' => [
                ['id' => $createdMenu->id, 'parent_id' => null, 'sort_num' => 1],
            ],
        ])
        ->assertRedirect("{$baseUrl}/rbac/menus/sort");

    // Delete menu
    $this->actingAs($admin)
        ->withSession([ScopeSessions::$tenantIdKey => $tenant->id])
        ->delete("{$baseUrl}/rbac/menus/{$createdMenu->id}")
        ->assertRedirect("{$baseUrl}/rbac/menus");

    tenancy()->initialize($tenant);
    try {
        expect(Menu::where('id', $createdMenu->id)->exists())->toBeFalse();
    } finally {
        tenancy()->end();
        DB::purge('tenant');
    }
});

it('syncs menu actions with rbac_permissions and manages standalone permissions cleanly', function () use (&$createdTenants) {
    $tenant = makeUniversalRbacTenant($createdTenants);
    $admin = User::factory()->create();
    TenantUser::create(['tenant_id' => $tenant->id, 'user_id' => $admin->id, 'is_active' => true]);

    tenancy()->initialize($tenant);
    try {
        $admin->assignRole('admin_tenant');
        $dashboardMenu = Menu::where('permission_prefix', 'dashboard')->firstOrFail();
    } finally {
        tenancy()->end();
        DB::purge('tenant');
    }

    $baseUrl = "http://{$tenant->slug}.presentify.test";

    // Create menu action -> auto-creates dashboard.export permission
    $this->actingAs($admin)
        ->withSession([ScopeSessions::$tenantIdKey => $tenant->id])
        ->post("{$baseUrl}/rbac/menus/{$dashboardMenu->id}/actions", [
            'action' => 'export',
            'label' => 'Ekspor Ringkasan',
            'route_name' => 'dashboard.export',
            'order' => 2,
        ])
        ->assertRedirect();

    tenancy()->initialize($tenant);
    try {
        $menuAction = MenuAction::where('permission_name', 'dashboard.export')->firstOrFail();
        $boundPerm = Permission::where('name', 'dashboard.export')->firstOrFail();
        expect($boundPerm->group)->toBeNull();
    } finally {
        tenancy()->end();
        DB::purge('tenant');
    }

    // Cannot edit menu-bound permission through standalone endpoint
    $this->actingAs($admin)
        ->withSession([ScopeSessions::$tenantIdKey => $tenant->id])
        ->put("{$baseUrl}/rbac/permissions/{$boundPerm->id}", [
            'name' => 'dashboard.hacked',
            'group' => 'Umum',
        ])
        ->assertSessionHasErrors('permission');

    // Update menu action -> renames permission in rbac_permissions
    $this->actingAs($admin)
        ->withSession([ScopeSessions::$tenantIdKey => $tenant->id])
        ->put("{$baseUrl}/rbac/menu-actions/{$menuAction->id}", [
            'action' => 'download',
            'label' => 'Unduh Ringkasan',
            'route_name' => 'dashboard.download',
            'order' => 3,
        ])
        ->assertRedirect();

    tenancy()->initialize($tenant);
    try {
        expect(Permission::where('name', 'dashboard.download')->exists())->toBeTrue()
            ->and(Permission::where('name', 'dashboard.export')->exists())->toBeFalse();
    } finally {
        tenancy()->end();
        DB::purge('tenant');
    }

    // Delete menu action -> removes permission from rbac_permissions
    $this->actingAs($admin)
        ->withSession([ScopeSessions::$tenantIdKey => $tenant->id])
        ->delete("{$baseUrl}/rbac/menu-actions/{$menuAction->id}")
        ->assertRedirect();

    tenancy()->initialize($tenant);
    try {
        expect(Permission::where('name', 'dashboard.download')->exists())->toBeFalse();
    } finally {
        tenancy()->end();
        DB::purge('tenant');
    }

    // Standalone permission CRUD
    $this->actingAs($admin)
        ->withSession([ScopeSessions::$tenantIdKey => $tenant->id])
        ->post("{$baseUrl}/rbac/permissions", [
            'name' => 'reports.schedule',
            'group' => 'Laporan',
        ])
        ->assertRedirect();

    tenancy()->initialize($tenant);
    try {
        $standalone = Permission::where('name', 'reports.schedule')->firstOrFail();
        expect($standalone->group)->toBe('Laporan');
    } finally {
        tenancy()->end();
        DB::purge('tenant');
    }

    $this->actingAs($admin)
        ->withSession([ScopeSessions::$tenantIdKey => $tenant->id])
        ->put("{$baseUrl}/rbac/permissions/{$standalone->id}", [
            'name' => 'reports.auto_schedule',
            'group' => 'Laporan Otomatis',
        ])
        ->assertRedirect();

    $this->actingAs($admin)
        ->withSession([ScopeSessions::$tenantIdKey => $tenant->id])
        ->withoutVite()
        ->get("{$baseUrl}/rbac/permissions?search=auto_schedule")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('universal/rbac/permission/index')
            ->has('standalonePermissions.data', 1)
        );

    $this->actingAs($admin)
        ->withSession([ScopeSessions::$tenantIdKey => $tenant->id])
        ->delete("{$baseUrl}/rbac/permissions/{$standalone->id}")
        ->assertRedirect();

    tenancy()->initialize($tenant);
    try {
        expect(Permission::where('id', $standalone->id)->exists())->toBeFalse();
    } finally {
        tenancy()->end();
        DB::purge('tenant');
    }
});

it('manages roles with menu, menu action, and standalone permissions sync and shares auth.permissions and auth.menus', function () use (&$createdTenants) {
    $this->seed(CentralRolesAndPermissionsSeeder::class);
    $this->seed(CentralMenuSeeder::class);

    $tenant = makeUniversalRbacTenant($createdTenants);
    $admin = User::factory()->create();
    $operator = User::factory()->create();
    TenantUser::create(['tenant_id' => $tenant->id, 'user_id' => $admin->id, 'is_active' => true]);
    TenantUser::create(['tenant_id' => $tenant->id, 'user_id' => $operator->id, 'is_active' => true]);

    // Give admin central superadmin_saas to test Central role endpoints and shared props too
    $admin->assignRole('superadmin_saas');

    tenancy()->initialize($tenant);
    try {
        $admin->assignRole('admin_tenant');
        $dashboardMenu = Menu::where('permission_prefix', 'dashboard')->firstOrFail();
        $menusMenu = Menu::where('permission_prefix', 'menus')->firstOrFail();
        $menusViewAction = MenuAction::where('permission_name', 'menus.view')->firstOrFail();
        $auditViewPerm = Permission::where('name', 'audit.view')->firstOrFail();
        $superadminRole = Role::where('name', 'superadmin_saas')->firstOrFail();
    } finally {
        tenancy()->end();
        DB::purge('tenant');
    }

    $baseUrl = "http://{$tenant->slug}.presentify.test";

    // Create custom role in Tenant
    $this->actingAs($admin)
        ->withSession([ScopeSessions::$tenantIdKey => $tenant->id])
        ->post("{$baseUrl}/rbac/roles", [
            'name' => 'menu_auditor',
            'menu_ids' => [$dashboardMenu->id, $menusMenu->id],
            'menu_action_ids' => [$menusViewAction->id],
            'permission_ids' => [$auditViewPerm->id],
        ])
        ->assertRedirect("{$baseUrl}/rbac/roles");

    tenancy()->initialize($tenant);
    try {
        $customRole = Role::where('name', 'menu_auditor')->firstOrFail();
        $operator->assignRole($customRole);
        expect($customRole->menus()->pluck('rbac_menus.id')->all())
            ->toContain($dashboardMenu->id, $menusMenu->id);
        expect($operator->can('menus.view'))->toBeTrue()
            ->and($operator->can('audit.view'))->toBeTrue()
            ->and($operator->can('menus.create'))->toBeFalse();
    } finally {
        tenancy()->end();
        DB::purge('tenant');
    }

    // Shared props on Tenant dashboard reflect operator's tenant permissions and menus
    $this->actingAs($operator)
        ->withSession([ScopeSessions::$tenantIdKey => $tenant->id])
        ->withoutVite()
        ->get("{$baseUrl}/dashboard")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('auth.permissions', fn ($perms) => collect($perms)->contains('menus.view') && collect($perms)->contains('audit.view'))
            ->has('auth.menus')
        );

    // Operator can access GET /rbac/menus (menus.view) but receives 403 on POST /rbac/menus (menus.create)
    $this->actingAs($operator)
        ->withSession([ScopeSessions::$tenantIdKey => $tenant->id])
        ->withoutVite()
        ->get("{$baseUrl}/rbac/menus")
        ->assertOk();

    $this->actingAs($operator)
        ->withSession([ScopeSessions::$tenantIdKey => $tenant->id])
        ->post("{$baseUrl}/rbac/menus", ['label_name' => 'Blocked', 'sort_num' => 1, 'is_active' => true, 'is_divider' => false])
        ->assertForbidden();

    // Shared props on Central dashboard reflect admin's Central permissions (isolated from Tenant)
    $this->actingAs($admin)
        ->withoutVite()
        ->get('http://presentify.test/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('auth.permissions', fn ($perms) => collect($perms)->contains('tenants.manage') && ! collect($perms)->contains('audit.view'))
            ->has('auth.menus')
        );

    // Protected role superadmin_saas cannot be deleted
    $this->actingAs($admin)
        ->withSession([ScopeSessions::$tenantIdKey => $tenant->id])
        ->delete("{$baseUrl}/rbac/roles/{$superadminRole->id}")
        ->assertSessionHasErrors('role');

    // Verify all RBAC Inertia pages render with valid component paths
    $this->actingAs($admin)
        ->withSession([ScopeSessions::$tenantIdKey => $tenant->id])
        ->withoutVite()
        ->get("{$baseUrl}/rbac/menus/create")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('universal/rbac/menu/form'));

    $this->actingAs($admin)
        ->withSession([ScopeSessions::$tenantIdKey => $tenant->id])
        ->withoutVite()
        ->get("{$baseUrl}/rbac/menus/sort")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('universal/rbac/menu/sort'));

    $this->actingAs($admin)
        ->withSession([ScopeSessions::$tenantIdKey => $tenant->id])
        ->withoutVite()
        ->get("{$baseUrl}/rbac/roles")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('universal/rbac/role/index'));

    $this->actingAs($admin)
        ->withSession([ScopeSessions::$tenantIdKey => $tenant->id])
        ->withoutVite()
        ->get("{$baseUrl}/rbac/roles/{$customRole->id}/edit")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('universal/rbac/role/form'));

    // Update & delete custom role
    $this->actingAs($admin)
        ->withSession([ScopeSessions::$tenantIdKey => $tenant->id])
        ->put("{$baseUrl}/rbac/roles/{$customRole->id}", [
            'name' => 'menu_auditor_v2',
            'menu_ids' => [$dashboardMenu->id],
            'permission_ids' => [$auditViewPerm->id],
        ])
        ->assertRedirect("{$baseUrl}/rbac/roles");

    $this->actingAs($admin)
        ->withSession([ScopeSessions::$tenantIdKey => $tenant->id])
        ->delete("{$baseUrl}/rbac/roles/{$customRole->id}")
        ->assertRedirect("{$baseUrl}/rbac/roles");

    tenancy()->initialize($tenant);
    try {
        expect(Role::where('id', $customRole->id)->exists())->toBeFalse();
    } finally {
        tenancy()->end();
        DB::purge('tenant');
    }
});
