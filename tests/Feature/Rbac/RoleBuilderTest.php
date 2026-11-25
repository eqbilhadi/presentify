<?php

use App\Models\Member;
use App\Models\Menu;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\Rbac\MenuSyncService;
use App\Services\Rbac\PermissionService;
use App\Services\Rbac\RoleBuilderService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Stancl\Tenancy\Middleware\ScopeSessions;

uses(RefreshDatabase::class);

/** @var array<int, Tenant> */
$createdTenants = [];

afterEach(function () use (&$createdTenants) {
    app(PermissionService::class)->flushCache();

    if (tenancy()->initialized) {
        tenancy()->end();
    }

    DB::purge('tenant');

    foreach ($createdTenants as $tenant) {
        $tenant->delete();
    }

    $createdTenants = [];
});

function createInitializedTenant(array &$createdTenants, string $slugPrefix = 'rbac'): Tenant
{
    $slug = $slugPrefix.uniqid();
    $tenant = Tenant::create([
        'name' => 'Instansi '.$slug,
        'slug' => $slug,
        'is_active' => true,
    ]);
    $tenant->domains()->create(['domain' => $slug]);
    $createdTenants[] = $tenant;

    tenancy()->initialize($tenant);
    try {
        (new RbacSeeder)->run();
        app(MenuSyncService::class)->sync();
    } finally {
        tenancy()->end();
        DB::purge('tenant');
    }

    return $tenant;
}

function createTenantMemberUser(Tenant $tenant, ?string $roleName = null, string $status = 'active'): array
{
    $user = User::factory()->create();

    TenantUser::create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'is_active' => true,
    ]);

    tenancy()->initialize($tenant);
    try {
        $member = Member::create([
            'global_user_id' => $user->id,
            'name' => $user->name,
            'status' => $status,
        ]);

        if ($roleName !== null) {
            $member->assignRole($roleName);
        }
    } finally {
        tenancy()->end();
        DB::purge('tenant');
    }

    return [$user, $member];
}

function tenantUrl(Tenant $tenant, string $path): string
{
    return "http://{$tenant->slug}.presentify.test{$path}";
}

it('blocks access to tenant role routes from central domain', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('http://presentify.test/settings/roles')
        ->assertNotFound();
});

it('redirects unauthenticated guests on tenant role routes to login', function () use (&$createdTenants) {
    $tenant = createInitializedTenant($createdTenants);

    $this->get(tenantUrl($tenant, '/settings/roles'))
        ->assertRedirect(route('login'));
});

it('forbids members without manage_tenant or settings.roles.view permission', function () use (&$createdTenants) {
    $tenant = createInitializedTenant($createdTenants);
    [$user] = createTenantMemberUser($tenant, 'member');

    $this->actingAs($user)
        ->withSession([ScopeSessions::$tenantIdKey => $tenant->id])
        ->get(tenantUrl($tenant, '/settings/roles'))
        ->assertForbidden();
});

it('allows admin_tenant to view roles index with counts', function () use (&$createdTenants) {
    $tenant = createInitializedTenant($createdTenants);
    [$user] = createTenantMemberUser($tenant, 'admin_tenant');

    $this->actingAs($user)
        ->withSession([ScopeSessions::$tenantIdKey => $tenant->id])
        ->get(tenantUrl($tenant, '/settings/roles'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/roles/index')
            ->has('roles', 5)
            ->where('roles.0.is_system', true)
        );
});

it('allows custom role with settings.roles.view to view roles index but forbids creating without create permission', function () use (&$createdTenants) {
    $tenant = createInitializedTenant($createdTenants);
    [$user, $member] = createTenantMemberUser($tenant);

    tenancy()->initialize($tenant);
    try {
        $viewRolesPerm = Permission::where('name', 'settings.roles.view')->firstOrFail();
        $customRole = Role::create([
            'name' => 'auditor_peran',
            'label' => 'Auditor Peran',
            'is_system' => false,
        ]);
        $customRole->syncPermissions([$viewRolesPerm->id]);
        $member->assignRole($customRole);
    } finally {
        tenancy()->end();
        DB::purge('tenant');
    }

    $this->actingAs($user)
        ->withSession([ScopeSessions::$tenantIdKey => $tenant->id])
        ->get(tenantUrl($tenant, '/settings/roles'))
        ->assertOk();

    $this->actingAs($user)
        ->withSession([ScopeSessions::$tenantIdKey => $tenant->id])
        ->post(tenantUrl($tenant, '/settings/roles'), [
            'label' => 'Role Baru',
            'permission_ids' => [],
        ])
        ->assertForbidden();
});

it('creates custom role with auto-slug and automatically enforces view dependency for non-view permissions', function () use (&$createdTenants) {
    $tenant = createInitializedTenant($createdTenants);
    [$user] = createTenantMemberUser($tenant, 'admin_tenant');

    tenancy()->initialize($tenant);
    try {
        $editLeavePerm = Permission::where('name', 'attendance.leave.edit')->firstOrFail();
        $viewLeavePerm = Permission::where('name', 'attendance.leave.view')->firstOrFail();
        $bypassGeoPerm = Permission::where('name', 'bypass_geofence')->firstOrFail();
    } finally {
        tenancy()->end();
        DB::purge('tenant');
    }

    $response = $this->actingAs($user)
        ->withSession([ScopeSessions::$tenantIdKey => $tenant->id])
        ->post(tenantUrl($tenant, '/settings/roles'), [
            'label' => 'Operator Cuti Lapangan',
            'description' => 'Mengelola pengajuan cuti pegawai lapangan.',
            // Intentionally omit `attendance.leave.view` to verify server-side Tier-1 auto-inclusion.
            'permission_ids' => [$editLeavePerm->id, $bypassGeoPerm->id],
        ]);

    $response->assertRedirect(tenantUrl($tenant, '/settings/roles'));

    tenancy()->initialize($tenant);
    try {
        $createdRole = Role::where('name', 'operator_cuti_lapangan')->firstOrFail();
        $assignedIds = $createdRole->permissions()->pluck('rbac_permissions.id')->all();

        expect($createdRole->is_system)->toBeFalse()
            ->and($createdRole->label)->toBe('Operator Cuti Lapangan')
            ->and($assignedIds)->toContain($editLeavePerm->id)
            ->and($assignedIds)->toContain($viewLeavePerm->id)
            ->and($assignedIds)->toContain($bypassGeoPerm->id);
    } finally {
        tenancy()->end();
        DB::purge('tenant');
    }
});

it('updates system role label and permissions while preserving immutable slug', function () use (&$createdTenants) {
    $tenant = createInitializedTenant($createdTenants);
    [$user] = createTenantMemberUser($tenant, 'admin_tenant');

    tenancy()->initialize($tenant);
    try {
        $verifierRole = Role::where('name', 'verifier')->firstOrFail();
        $inboxCreatePerm = Permission::where('name', 'approvals.inbox.create')->firstOrFail();
        $inboxViewPerm = Permission::where('name', 'approvals.inbox.view')->firstOrFail();
    } finally {
        tenancy()->end();
        DB::purge('tenant');
    }

    $this->actingAs($user)
        ->withSession([ScopeSessions::$tenantIdKey => $tenant->id])
        ->put(tenantUrl($tenant, "/settings/roles/{$verifierRole->id}"), [
            'label' => 'Kepala Unit / Verifikator',
            'description' => 'Menyetujui izin bawahan.',
            'permission_ids' => [$inboxCreatePerm->id],
        ])
        ->assertRedirect(tenantUrl($tenant, '/settings/roles'));

    tenancy()->initialize($tenant);
    try {
        $verifierRole->refresh();
        $assignedIds = $verifierRole->permissions()->pluck('rbac_permissions.id')->all();

        expect($verifierRole->name)->toBe('verifier')
            ->and($verifierRole->label)->toBe('Kepala Unit / Verifikator')
            ->and($assignedIds)->toContain($inboxCreatePerm->id)
            ->and($assignedIds)->toContain($inboxViewPerm->id);
    } finally {
        tenancy()->end();
        DB::purge('tenant');
    }
});

it('deletes custom role cleanly and prevents deleting system roles', function () use (&$createdTenants) {
    $tenant = createInitializedTenant($createdTenants);
    [$user] = createTenantMemberUser($tenant, 'admin_tenant');

    tenancy()->initialize($tenant);
    try {
        $systemRole = Role::where('name', 'member')->firstOrFail();
        $customRole = app(RoleBuilderService::class)->createRole([
            'label' => 'Magang Sementara',
            'permission_ids' => [],
        ]);
    } finally {
        tenancy()->end();
        DB::purge('tenant');
    }

    // System role deletion must be rejected
    $this->actingAs($user)
        ->withSession([ScopeSessions::$tenantIdKey => $tenant->id])
        ->from(tenantUrl($tenant, '/settings/roles'))
        ->delete(tenantUrl($tenant, "/settings/roles/{$systemRole->id}"))
        ->assertSessionHasErrors('role');

    // Custom role deletion must succeed
    $this->actingAs($user)
        ->withSession([ScopeSessions::$tenantIdKey => $tenant->id])
        ->delete(tenantUrl($tenant, "/settings/roles/{$customRole->id}"))
        ->assertRedirect(tenantUrl($tenant, '/settings/roles'));

    tenancy()->initialize($tenant);
    try {
        expect(Role::find($systemRole->id))->not->toBeNull()
            ->and(Role::find($customRole->id))->toBeNull();
    } finally {
        tenancy()->end();
        DB::purge('tenant');
    }
});

it('enforces 2-tier permission rules via Gate and PermissionService', function () use (&$createdTenants) {
    $tenant = createInitializedTenant($createdTenants);
    [$user, $member] = createTenantMemberUser($tenant);
    [$inactiveUser] = createTenantMemberUser($tenant, 'admin_tenant', 'inactive');
    [$superUser] = createTenantMemberUser($tenant, 'superadmin_saas');

    tenancy()->initialize($tenant);
    try {
        $checkinCreate = Permission::where('name', 'attendance.checkin.create')->firstOrFail();
        $checkinView = Permission::where('name', 'attendance.checkin.view')->firstOrFail();
        $bypassGeofence = Permission::where('name', 'bypass_geofence')->firstOrFail();

        $brokenRole = Role::create([
            'name' => 'broken_custom',
            'label' => 'Broken Custom',
            'is_system' => false,
        ]);
        // Simulate raw DB state with `create` granted WITHOUT `view`
        $brokenRole->syncPermissions([$checkinCreate->id, $bypassGeofence->id]);
        $member->assignRole($brokenRole);

        $permissionService = app(PermissionService::class);
        $permissionService->flushCache();

        // Non-view menu permission without `view` must be denied; global permission allowed
        expect(Gate::forUser($user)->allows('attendance.checkin.create'))->toBeFalse()
            ->and(Gate::forUser($user)->allows('bypass_geofence'))->toBeTrue()
            ->and(Gate::forUser($inactiveUser)->allows('manage_tenant'))->toBeFalse()
            ->and(Gate::forUser($superUser)->allows('attendance.checkin.create'))->toBeTrue();

        // Granting `view` unlocks `create`
        $brokenRole->givePermissions([$checkinView->id]);
        $permissionService->flushCache();

        expect(Gate::forUser($user)->allows('attendance.checkin.create'))->toBeTrue()
            ->and(Gate::forUser($user)->allows('has-permission', 'attendance.checkin.create'))->toBeTrue();
    } finally {
        tenancy()->end();
        DB::purge('tenant');
    }
});

it('renders role editor page with 2-tier matrix for create and edit', function () use (&$createdTenants) {
    $tenant = createInitializedTenant($createdTenants);
    [$user] = createTenantMemberUser($tenant, 'admin_tenant');

    tenancy()->initialize($tenant);
    try {
        $verifierRole = Role::where('name', 'verifier')->firstOrFail();
        $expectedMenuRows = Menu::has('permissions')->count() + Menu::whereNull('parent_id')->whereHas('children.permissions')->count();
    } finally {
        tenancy()->end();
        DB::purge('tenant');
    }

    $this->actingAs($user)
        ->withSession([ScopeSessions::$tenantIdKey => $tenant->id])
        ->get(tenantUrl($tenant, '/settings/roles/create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/roles/role-editor')
            ->where('role', null)
            ->has('matrix.actions', 6)
            ->has('matrix.global_permissions', 6)
            ->has('matrix.menu_rows', $expectedMenuRows)
        );

    $this->actingAs($user)
        ->withSession([ScopeSessions::$tenantIdKey => $tenant->id])
        ->get(tenantUrl($tenant, "/settings/roles/{$verifierRole->id}/edit"))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/roles/role-editor')
            ->where('role.name', 'verifier')
            ->has('role.permission_ids')
        );
});
