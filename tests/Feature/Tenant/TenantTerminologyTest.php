<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\Tenant\TerminologyService;
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

function makeTenantTerminologySubject(array &$createdTenants, string $slugPrefix = 'tterm'): Tenant
{
    $slug = $slugPrefix.uniqid();
    $tenant = Tenant::create([
        'name' => 'Instansi '.$slug,
        'slug' => $slug,
        'is_active' => true,
        'terminology' => [
            'employee' => 'Guru',
            'unit' => 'Kelas',
        ],
    ]);
    $tenant->domains()->create(['domain' => $slug]);
    $createdTenants[] = $tenant;

    return $tenant;
}

function attachTenantTerminologyUser(Tenant $tenant, ?string $roleName = null, array $extraPermissions = []): User
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

it('merges partial tenant terminology overrides with canonical default terms in TerminologyService', function () use (&$createdTenants) {
    $service = app(TerminologyService::class);
    $tenant = makeTenantTerminologySubject($createdTenants);

    $resolved = $service->resolveTerms($tenant);

    expect($resolved)->toMatchArray([
        'employee' => 'Guru',
        'unit' => 'Kelas',
        'supervisor' => 'Atasan',
        'delegation' => 'Pelaksana Tugas',
        'attendance' => 'Presensi',
        'leave' => 'Izin & Cuti',
        'nip' => 'NIP',
        'office' => 'Instansi',
    ]);

    expect($service->resolveTerms(null))->toBe($service->getDefaults());
});

it('isolates terminology routes to tenant domains and enforces permissions', function () use (&$createdTenants) {
    $tenant = makeTenantTerminologySubject($createdTenants);
    $member = attachTenantTerminologyUser($tenant, 'member');
    $viewerOnly = attachTenantTerminologyUser($tenant, null, ['tenant_terminology.view']);

    // Guest redirects to SSO login.
    $this->get("http://{$tenant->slug}.presentify.test/tenant/terminology")
        ->assertRedirect("http://{$tenant->slug}.presentify.test/sso/login");

    // Central domain must reject tenant-only terminology routes with 404.
    $this->actingAs($member)
        ->get('http://presentify.test/tenant/terminology')
        ->assertNotFound();

    // Member without permission gets 403.
    $this->actingAs($member)
        ->withSession([ScopeSessions::$tenantIdKey => $tenant->id])
        ->withoutVite()
        ->get("http://{$tenant->slug}.presentify.test/tenant/terminology")
        ->assertForbidden();

    // Viewer-only can view page (200) but cannot update or reset (403).
    $this->actingAs($viewerOnly)
        ->withSession([ScopeSessions::$tenantIdKey => $tenant->id])
        ->withoutVite()
        ->get("http://{$tenant->slug}.presentify.test/tenant/terminology")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/terminology/edit')
            ->where('terminology.terms.employee', 'Guru')
            ->where('terminology.terms.unit', 'Kelas')
            ->where('tenancy.terminology.employee', 'Guru')
        );

    $defaults = app(TerminologyService::class)->getDefaults();

    $this->actingAs($viewerOnly)
        ->withSession([ScopeSessions::$tenantIdKey => $tenant->id])
        ->put("http://{$tenant->slug}.presentify.test/tenant/terminology", [
            'terms' => $defaults,
        ])
        ->assertForbidden();

    $this->actingAs($viewerOnly)
        ->withSession([ScopeSessions::$tenantIdKey => $tenant->id])
        ->delete("http://{$tenant->slug}.presentify.test/tenant/terminology")
        ->assertForbidden();
});

it('updates, shares across Inertia pages, validates, and resets tenant terminology on sys_tenants', function () use (&$createdTenants) {
    $tenant = makeTenantTerminologySubject($createdTenants);
    $admin = attachTenantTerminologyUser($tenant, 'admin_tenant');

    $customTerms = [
        'employee' => 'Karyawan',
        'unit' => 'Divisi',
        'supervisor' => 'Manajer',
        'delegation' => 'Acting Manager',
        'attendance' => 'Absensi',
        'leave' => 'Cuti & Izin',
        'nip' => 'ID Karyawan',
        'office' => 'Perusahaan',
    ];

    $this->actingAs($admin)
        ->withSession([ScopeSessions::$tenantIdKey => $tenant->id])
        ->put("http://{$tenant->slug}.presentify.test/tenant/terminology", [
            'terms' => $customTerms,
        ])
        ->assertRedirect("http://{$tenant->slug}.presentify.test/tenant/terminology");

    $tenant->refresh();
    expect($tenant->terminology)->toBe($customTerms);

    // Shared prop tenancy.terminology is present across tenant pages.
    $this->actingAs($admin)
        ->withSession([ScopeSessions::$tenantIdKey => $tenant->id])
        ->withoutVite()
        ->get("http://{$tenant->slug}.presentify.test/dashboard")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('tenancy.terminology.employee', 'Karyawan')
            ->where('tenancy.terminology.unit', 'Divisi')
            ->where('tenancy.terminology.supervisor', 'Manajer')
        );

    // Validation fails when any canonical term key is blank or exceeds 60 chars.
    $this->actingAs($admin)
        ->withSession([ScopeSessions::$tenantIdKey => $tenant->id])
        ->put("http://{$tenant->slug}.presentify.test/tenant/terminology", [
            'terms' => [
                ...$customTerms,
                'employee' => '',
                'unit' => str_repeat('x', 61),
            ],
        ])
        ->assertSessionHasErrors(['terms.employee', 'terms.unit']);

    // Reset reverts terminology to defaults.
    $this->actingAs($admin)
        ->withSession([ScopeSessions::$tenantIdKey => $tenant->id])
        ->delete("http://{$tenant->slug}.presentify.test/tenant/terminology")
        ->assertRedirect("http://{$tenant->slug}.presentify.test/tenant/terminology");

    $tenant->refresh();
    expect($tenant->terminology)->toBeNull();

    $this->actingAs($admin)
        ->withSession([ScopeSessions::$tenantIdKey => $tenant->id])
        ->withoutVite()
        ->get("http://{$tenant->slug}.presentify.test/tenant/terminology")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('terminology.is_customized', false)
            ->where('tenancy.terminology.employee', 'Pegawai')
            ->where('tenancy.terminology.unit', 'Unit Kerja')
        );
});
