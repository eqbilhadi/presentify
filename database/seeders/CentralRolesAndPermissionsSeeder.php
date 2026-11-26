<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class CentralRolesAndPermissionsSeeder extends Seeder
{
    /** @var array<int, array{name: string, group: string}> */
    private const STANDALONE_PERMISSIONS = [
        ['name' => 'tenants.manage', 'group' => 'Platform'],
        ['name' => 'plans.manage', 'group' => 'Platform'],
        ['name' => 'system.audit.view', 'group' => 'Sistem'],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $superadmin = Role::query()->updateOrCreate(
            ['name' => 'superadmin_saas', 'guard_name' => 'web'],
        );

        $permissionIds = [];

        foreach (self::STANDALONE_PERMISSIONS as $definition) {
            $permission = Permission::query()->updateOrCreate(
                ['name' => $definition['name'], 'guard_name' => 'web'],
                ['group' => $definition['group']],
            );
            $permissionIds[] = $permission->id;
        }

        $superadmin->permissions()->syncWithoutDetaching($permissionIds);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
