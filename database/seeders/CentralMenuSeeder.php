<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Role;
use Database\Seeders\Concerns\SeedsCrudMenus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

class CentralMenuSeeder extends Seeder
{
    use SeedsCrudMenus;

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        DB::transaction(function (): void {
            /** @var Role $superadmin */
            $superadmin = Role::query()
                ->where('name', 'superadmin_saas')
                ->where('guard_name', 'web')
                ->firstOrFail();

            $roles = [$superadmin];

            $this->seedMenuWithActions([
                'sort_num' => 10,
                'icon' => 'LayoutGrid',
                'label_name' => 'Dashboard',
                'route_name' => 'dashboard',
                'url' => '/dashboard',
                'permission_prefix' => 'dashboard',
            ], ['view'], $roles);

            $this->seedMenuWithActions([
                'sort_num' => 20,
                'icon' => 'Building2',
                'label_name' => 'Manajemen Tenant',
                'url' => '/tenants',
                'permission_prefix' => 'tenants',
            ], ['view', 'create', 'update', 'delete'], $roles);

            $this->seedDivider('Sistem & Akses', 30, $roles);

            $rbacParent = $this->seedMenuWithActions([
                'sort_num' => 40,
                'icon' => 'Shield',
                'label_name' => 'Pengaturan RBAC',
            ], [], $roles);

            $this->seedMenuWithActions([
                'parent_id' => $rbacParent->id,
                'sort_num' => 10,
                'icon' => 'ListTree',
                'label_name' => 'Manajemen Menu',
                'route_name' => 'rbac.menus.index',
                'url' => '/rbac/menus',
                'permission_prefix' => 'menus',
            ], ['view', 'create', 'update', 'delete'], $roles);

            $this->seedMenuWithActions([
                'parent_id' => $rbacParent->id,
                'sort_num' => 20,
                'icon' => 'KeyRound',
                'label_name' => 'Hak Akses',
                'route_name' => 'rbac.permissions.index',
                'url' => '/rbac/permissions',
                'permission_prefix' => 'permissions',
            ], ['view', 'create', 'update', 'delete'], $roles);

            $this->seedMenuWithActions([
                'parent_id' => $rbacParent->id,
                'sort_num' => 30,
                'icon' => 'UserCog',
                'label_name' => 'Peran & Matriks',
                'route_name' => 'rbac.roles.index',
                'url' => '/rbac/roles',
                'permission_prefix' => 'roles',
            ], ['view', 'create', 'update', 'delete'], $roles);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
