<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Role;
use Database\Seeders\Concerns\SeedsCrudMenus;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class TenantMenuSeeder extends Seeder
{
    use SeedsCrudMenus;

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $superadmin = Role::query()->where('name', 'superadmin_saas')->where('guard_name', 'web')->firstOrFail();
        $adminTenant = Role::query()->where('name', 'admin_tenant')->where('guard_name', 'web')->firstOrFail();
        $verifier = Role::query()->where('name', 'verifier')->where('guard_name', 'web')->firstOrFail();
        $member = Role::query()->where('name', 'member')->where('guard_name', 'web')->firstOrFail();

        $allEmployees = [$superadmin, $adminTenant, $verifier, $member];
        $approvers = [$superadmin, $adminTenant, $verifier];
        $admins = [$superadmin, $adminTenant];

        $this->seedMenuWithActions([
            'sort_num' => 10,
            'icon' => 'LayoutGrid',
            'label_name' => 'Dashboard',
            'route_name' => 'dashboard',
            'url' => '/dashboard',
            'permission_prefix' => 'dashboard',
        ], ['view'], $allEmployees);

        $attendanceGroup = $this->seedMenuWithActions([
            'sort_num' => 20,
            'icon' => 'Clock',
            'label_name' => 'Presensi',
        ], [], $allEmployees);

        $this->seedMenuWithActions([
            'parent_id' => $attendanceGroup->id,
            'sort_num' => 10,
            'icon' => 'Fingerprint',
            'label_name' => 'Catat Kehadiran',
            'url' => '/attendance/check-in',
            'permission_prefix' => 'attendances',
        ], ['view', 'create'], $allEmployees);

        $this->seedMenuWithActions([
            'parent_id' => $attendanceGroup->id,
            'sort_num' => 20,
            'icon' => 'FileText',
            'label_name' => 'Pengajuan Izin & Cuti',
            'url' => '/leave-requests',
            'permission_prefix' => 'leaves',
        ], ['view', 'create', 'update', 'delete'], $allEmployees);

        $this->seedMenuWithActions([
            'sort_num' => 30,
            'icon' => 'CheckSquare',
            'label_name' => 'Persetujuan',
            'url' => '/approvals',
            'permission_prefix' => 'approvals',
        ], ['view', 'update'], $approvers);

        $this->seedDivider('Administrasi Instansi', 40, $admins);

        $orgGroup = $this->seedMenuWithActions([
            'sort_num' => 50,
            'icon' => 'Building2',
            'label_name' => 'Organisasi',
        ], [], $admins);

        $this->seedMenuWithActions([
            'parent_id' => $orgGroup->id,
            'sort_num' => 10,
            'icon' => 'Network',
            'label_name' => 'Unit Kerja',
            'url' => '/organization/units',
            'permission_prefix' => 'units',
        ], ['view', 'create', 'update', 'delete'], $admins);

        $this->seedMenuWithActions([
            'parent_id' => $orgGroup->id,
            'sort_num' => 20,
            'icon' => 'Users',
            'label_name' => 'Pegawai & Anggota',
            'url' => '/organization/members',
            'permission_prefix' => 'members',
        ], ['view', 'create', 'update', 'delete'], $admins);

        $this->seedMenuWithActions([
            'parent_id' => $orgGroup->id,
            'sort_num' => 30,
            'icon' => 'UserCheck',
            'label_name' => 'Delegasi Wewenang',
            'url' => '/organization/delegations',
            'permission_prefix' => 'delegations',
        ], ['view', 'create', 'update', 'delete'], $admins);

        $settingsGroup = $this->seedMenuWithActions([
            'sort_num' => 60,
            'icon' => 'Settings',
            'label_name' => 'Pengaturan & RBAC',
        ], [], $admins);

        $this->seedMenuWithActions([
            'parent_id' => $settingsGroup->id,
            'sort_num' => 5,
            'icon' => 'Building2',
            'label_name' => 'Profil Instansi',
            'route_name' => 'tenant.profile.edit',
            'url' => '/tenant/profile',
            'permission_prefix' => 'tenant_profile',
        ], ['view', 'update'], $admins);

        $this->seedMenuWithActions([
            'parent_id' => $settingsGroup->id,
            'sort_num' => 10,
            'icon' => 'BookOpen',
            'label_name' => 'Kebijakan Presensi',
            'url' => '/policies',
            'permission_prefix' => 'policies',
        ], ['view', 'create', 'update', 'delete'], $admins);

        $this->seedMenuWithActions([
            'parent_id' => $settingsGroup->id,
            'sort_num' => 20,
            'icon' => 'ListTree',
            'label_name' => 'Manajemen Menu',
            'route_name' => 'rbac.menus.index',
            'url' => '/rbac/menus',
            'permission_prefix' => 'menus',
        ], ['view', 'create', 'update', 'delete'], $admins);

        $this->seedMenuWithActions([
            'parent_id' => $settingsGroup->id,
            'sort_num' => 30,
            'icon' => 'KeyRound',
            'label_name' => 'Hak Akses',
            'route_name' => 'rbac.permissions.index',
            'url' => '/rbac/permissions',
            'permission_prefix' => 'permissions',
        ], ['view', 'create', 'update', 'delete'], $admins);

        $this->seedMenuWithActions([
            'parent_id' => $settingsGroup->id,
            'sort_num' => 40,
            'icon' => 'Shield',
            'label_name' => 'Peran & Matriks',
            'route_name' => 'rbac.roles.index',
            'url' => '/rbac/roles',
            'permission_prefix' => 'roles',
        ], ['view', 'create', 'update', 'delete'], $admins);

        $this->seedMenuWithActions([
            'parent_id' => $settingsGroup->id,
            'sort_num' => 50,
            'icon' => 'UserCog',
            'label_name' => 'Manajemen Pengguna',
            'route_name' => 'tenant.rbac.users.index',
            'url' => '/rbac/tenant-users',
            'permission_prefix' => 'users',
        ], ['view', 'create', 'update'], $admins);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
