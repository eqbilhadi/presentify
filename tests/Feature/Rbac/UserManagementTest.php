<?php

use App\Models\Member;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Database\Seeders\CentralMenuSeeder;
use Database\Seeders\CentralRolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

function makeUserMgmtTenant(array &$createdTenants, string $slugPrefix = 'umgmt'): Tenant
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

it('enforces domain isolation and permissions for central and tenant user management', function () use (&$createdTenants) {
    $this->seed(CentralRolesAndPermissionsSeeder::class);
    $this->seed(CentralMenuSeeder::class);

    $tenant = makeUserMgmtTenant($createdTenants);
    $user = User::factory()->create();

    TenantUser::create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'is_active' => true,
    ]);

    // Central user route must return 404 on tenant domain
    $this->actingAs($user)
        ->withSession([ScopeSessions::$tenantIdKey => $tenant->id])
        ->get("http://{$tenant->slug}.presentify.test/rbac/central-users")
        ->assertNotFound();

    // Tenant user route must return 404 on central domain
    tenancy()->end();
    DB::purge('tenant');

    $this->actingAs($user)
        ->get('http://presentify.test/rbac/tenant-users')
        ->assertNotFound();

    // Plain user without users.view permission gets 403 on both contexts
    $this->actingAs($user)
        ->get('http://presentify.test/rbac/central-users')
        ->assertForbidden();

    $this->actingAs($user)
        ->withSession([ScopeSessions::$tenantIdKey => $tenant->id])
        ->get("http://{$tenant->slug}.presentify.test/rbac/tenant-users")
        ->assertForbidden();
});

it('supports central user CRUD and blocks self-deletion', function () {
    $this->seed(CentralRolesAndPermissionsSeeder::class);
    $this->seed(CentralMenuSeeder::class);

    $admin = User::factory()->create();
    $admin->assignRole('superadmin_saas');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $superadminRole = Role::query()->where('name', 'superadmin_saas')->firstOrFail();

    $this->actingAs($admin)
        ->withoutVite()
        ->get('http://presentify.test/rbac/central-users')
        ->assertOk();

    // Create central user
    $this->actingAs($admin)
        ->post('http://presentify.test/rbac/central-users', [
            'name' => 'Operator Pusat',
            'email' => 'operator@presentify.test',
            'password' => 'SecretPass123!',
            'password_confirmation' => 'SecretPass123!',
            'role_ids' => [$superadminRole->id],
        ])
        ->assertRedirect('http://presentify.test/rbac/central-users');

    $created = User::query()->where('email', 'operator@presentify.test')->firstOrFail();
    expect($created->hasRole('superadmin_saas'))->toBeTrue();

    // Update central user
    $this->actingAs($admin)
        ->put("http://presentify.test/rbac/central-users/{$created->id}", [
            'name' => 'Operator Pusat Senior',
            'email' => 'operator@presentify.test',
            'role_ids' => [$superadminRole->id],
        ])
        ->assertRedirect('http://presentify.test/rbac/central-users');

    expect($created->fresh()?->name)->toBe('Operator Pusat Senior');

    // Cannot delete self
    $this->actingAs($admin)
        ->delete("http://presentify.test/rbac/central-users/{$admin->id}")
        ->assertSessionHasErrors('user');

    // Can delete another user
    $this->actingAs($admin)
        ->delete("http://presentify.test/rbac/central-users/{$created->id}")
        ->assertRedirect('http://presentify.test/rbac/central-users');

    expect(User::query()->whereKey($created->id)->exists())->toBeFalse();
});

it('supports tenant user creation, attaching existing global user, role update, and status toggle', function () use (&$createdTenants) {
    $tenant = makeUserMgmtTenant($createdTenants);
    $tenantAdmin = User::factory()->create();
    $existingGlobalUser = User::factory()->create([
        'name' => 'Budi Santoso',
        'email' => 'budi@presentify.test',
    ]);

    TenantUser::create([
        'tenant_id' => $tenant->id,
        'user_id' => $tenantAdmin->id,
        'is_active' => true,
    ]);

    tenancy()->initialize($tenant);
    $tenantAdmin->assignRole('admin_tenant');
    $memberRole = Role::query()->where('name', 'member')->firstOrFail();
    $verifierRole = Role::query()->where('name', 'verifier')->firstOrFail();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    tenancy()->end();
    DB::purge('tenant');

    // Index accessible by admin_tenant
    $this->actingAs($tenantAdmin)
        ->withoutVite()
        ->withSession([ScopeSessions::$tenantIdKey => $tenant->id])
        ->get("http://{$tenant->slug}.presentify.test/rbac/tenant-users")
        ->assertOk();

    // Attach existing global user to tenant without password
    $this->actingAs($tenantAdmin)
        ->withSession([ScopeSessions::$tenantIdKey => $tenant->id])
        ->post("http://{$tenant->slug}.presentify.test/rbac/tenant-users", [
            'email' => $existingGlobalUser->email,
            'nip' => '199001012015011001',
            'position' => 'Staf Pelaksana',
            'is_active' => true,
            'role_ids' => [$memberRole->id],
        ])
        ->assertRedirect("http://{$tenant->slug}.presentify.test/rbac/tenant-users");

    expect(TenantUser::query()
        ->where('tenant_id', $tenant->id)
        ->where('user_id', $existingGlobalUser->id)
        ->where('is_active', true)
        ->exists())->toBeTrue();

    tenancy()->initialize($tenant);
    $existingGlobalUser->unsetRelation('roles');
    expect($existingGlobalUser->hasRole('member'))->toBeTrue();
    expect(Member::query()->where('global_user_id', $existingGlobalUser->id)->where('nip', '199001012015011001')->exists())->toBeTrue();
    tenancy()->end();
    DB::purge('tenant');

    // Update tenant user role & toggle status
    $this->actingAs($tenantAdmin)
        ->withSession([ScopeSessions::$tenantIdKey => $tenant->id])
        ->put("http://{$tenant->slug}.presentify.test/rbac/tenant-users/{$existingGlobalUser->id}", [
            'nip' => '199001012015011001',
            'position' => 'Kepala Seksi',
            'is_active' => true,
            'role_ids' => [$verifierRole->id],
        ])
        ->assertRedirect("http://{$tenant->slug}.presentify.test/rbac/tenant-users");

    $this->actingAs($tenantAdmin)
        ->withSession([ScopeSessions::$tenantIdKey => $tenant->id])
        ->patch("http://{$tenant->slug}.presentify.test/rbac/tenant-users/{$existingGlobalUser->id}/toggle-status")
        ->assertRedirect();

    expect(TenantUser::query()
        ->where('tenant_id', $tenant->id)
        ->where('user_id', $existingGlobalUser->id)
        ->value('is_active'))->toBeFalsy();

    // Cannot deactivate self
    $this->actingAs($tenantAdmin)
        ->withSession([ScopeSessions::$tenantIdKey => $tenant->id])
        ->patch("http://{$tenant->slug}.presentify.test/rbac/tenant-users/{$tenantAdmin->id}/toggle-status")
        ->assertSessionHasErrors('user');
});
