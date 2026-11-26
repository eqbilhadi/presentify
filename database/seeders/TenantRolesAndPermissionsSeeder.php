<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class TenantRolesAndPermissionsSeeder extends Seeder
{
    /** @var array<int, string> */
    private const DEFAULT_ROLES = [
        'superadmin_saas',
        'admin_tenant',
        'verifier',
        'member',
        'kiosk',
    ];

    /** @var array<int, array{name: string, group: string}> */
    private const STANDALONE_PERMISSIONS = [
        ['name' => 'attendance.override', 'group' => 'Presensi'],
        ['name' => 'attendance.bypass_geofence', 'group' => 'Presensi'],
        ['name' => 'device.reset', 'group' => 'Perangkat & Kiosk'],
        ['name' => 'kiosk.qr.display', 'group' => 'Perangkat & Kiosk'],
        ['name' => 'kiosk.scan', 'group' => 'Perangkat & Kiosk'],
        ['name' => 'leave.approve_all_units', 'group' => 'Persetujuan'],
        ['name' => 'ai.command_bar.use', 'group' => 'AI & Audit'],
        ['name' => 'audit.view', 'group' => 'AI & Audit'],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        /** @var array<string, Role> $roles */
        $roles = [];

        foreach (self::DEFAULT_ROLES as $roleName) {
            $roles[$roleName] = Role::query()->updateOrCreate(
                ['name' => $roleName, 'guard_name' => 'web'],
            );
        }

        /** @var array<string, string> $permissionIdsByName */
        $permissionIdsByName = [];

        foreach (self::STANDALONE_PERMISSIONS as $definition) {
            $permission = Permission::query()->updateOrCreate(
                ['name' => $definition['name'], 'guard_name' => 'web'],
                ['group' => $definition['group']],
            );
            $permissionIdsByName[$definition['name']] = $permission->id;
        }

        $allIds = array_values($permissionIdsByName);
        $roles['superadmin_saas']->permissions()->syncWithoutDetaching($allIds);
        $roles['admin_tenant']->permissions()->syncWithoutDetaching($allIds);

        $roles['verifier']->permissions()->syncWithoutDetaching([
            $permissionIdsByName['leave.approve_all_units'],
        ]);

        $roles['kiosk']->permissions()->syncWithoutDetaching([
            $permissionIdsByName['kiosk.qr.display'],
            $permissionIdsByName['kiosk.scan'],
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
