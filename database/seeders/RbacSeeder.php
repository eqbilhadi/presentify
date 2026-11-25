<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Seeds 5 system roles and their default permission matrix
 * into each tenant database.
 *
 * Run via: php artisan tenants:run db:seed --option="class=RbacSeeder"
 */
class RbacSeeder extends Seeder
{
    // ----------------------------------------------------------------
    // Role definitions
    // ----------------------------------------------------------------
    private const ROLES = [
        [
            'name'        => 'superadmin_saas',
            'label'       => 'Superadmin SaaS',
            'description' => 'Hak akses penuh seluruh sistem SaaS — hanya untuk pengelola platform.',
            'is_system'   => true,
        ],
        [
            'name'        => 'admin_tenant',
            'label'       => 'Admin Instansi',
            'description' => 'Mengelola seluruh konfigurasi, pengguna, dan data instansi.',
            'is_system'   => true,
        ],
        [
            'name'        => 'verifier',
            'label'       => 'Atasan / Verifikator',
            'description' => 'Memverifikasi kehadiran dan menyetujui pengajuan izin bawahan.',
            'is_system'   => true,
        ],
        [
            'name'        => 'member',
            'label'       => 'Pegawai / Anggota',
            'description' => 'Mencatat kehadiran dan mengajukan izin/cuti.',
            'is_system'   => true,
        ],
        [
            'name'        => 'kiosk',
            'label'       => 'Akun Kiosk',
            'description' => 'Akun khusus perangkat kiosk — hanya aksi check-in QR.',
            'is_system'   => true,
        ],
    ];

    // ----------------------------------------------------------------
    // Global permissions (menu_id = null) — Tier-2
    // ----------------------------------------------------------------
    private const GLOBAL_PERMISSIONS = [
        ['name' => 'bypass_geofence',    'label' => 'Bypass Geofence',         'action' => 'bypass'],
        ['name' => 'override_attendance','label' => 'Override Kehadiran',       'action' => 'override'],
        ['name' => 'reset_device',       'label' => 'Reset Perangkat',          'action' => 'override'],
        ['name' => 'cross_unit_approval','label' => 'Approval Lintas Unit',     'action' => 'override'],
        ['name' => 'export_global',      'label' => 'Ekspor Data Global',       'action' => 'export'],
        ['name' => 'manage_tenant',      'label' => 'Kelola Konfigurasi Tenant','action' => 'override'],
    ];

    // ----------------------------------------------------------------
    // Role → global permission matrix
    // ----------------------------------------------------------------
    private const ROLE_GLOBAL_PERMISSIONS = [
        'superadmin_saas' => [
            'bypass_geofence', 'override_attendance', 'reset_device',
            'cross_unit_approval', 'export_global', 'manage_tenant',
        ],
        'admin_tenant' => [
            'bypass_geofence', 'override_attendance', 'reset_device',
            'cross_unit_approval', 'export_global', 'manage_tenant',
        ],
        'verifier' => [
            'cross_unit_approval',
        ],
        'member' => [],
        'kiosk'  => [],
    ];

    public function run(): void
    {
        // 1. Upsert roles
        $roleMap = [];
        foreach (self::ROLES as $data) {
            $role = Role::updateOrCreate(['name' => $data['name']], $data);
            $roleMap[$data['name']] = $role;
        }

        // 2. Upsert global permissions
        $permMap = [];
        foreach (self::GLOBAL_PERMISSIONS as $data) {
            $perm = Permission::updateOrCreate(
                ['name' => $data['name'], 'menu_id' => null],
                array_merge($data, ['menu_id' => null]),
            );
            $permMap[$data['name']] = $perm;
        }

        // 3. Assign global permissions to roles (syncWithoutDetaching — idempotent)
        foreach (self::ROLE_GLOBAL_PERMISSIONS as $roleName => $permNames) {
            $role = $roleMap[$roleName];
            $ids  = array_map(fn (string $p) => $permMap[$p]->id, $permNames);
            $role->permissions()->syncWithoutDetaching($ids);
        }
    }
}
