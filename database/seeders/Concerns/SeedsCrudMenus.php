<?php

declare(strict_types=1);

namespace Database\Seeders\Concerns;

use App\Models\Menu;
use App\Models\MenuAction;
use App\Models\Permission;
use App\Models\Role;

trait SeedsCrudMenus
{
    /** @var array<string, string> */
    private const DEFAULT_ACTION_LABELS = [
        'view' => 'Lihat',
        'create' => 'Tambah',
        'update' => 'Ubah',
        'delete' => 'Hapus',
        'export' => 'Ekspor',
        'import' => 'Impor',
    ];

    /**
     * Idempotently seed a menu item, its menu-bound actions, synced Spatie permissions,
     * and non-destructive role assignments.
     *
     * @param  array{
     *     parent_id?: string|null,
     *     sort_num: int,
     *     icon?: string|null,
     *     label_name: string,
     *     controller_name?: string|null,
     *     route_name?: string|null,
     *     url?: string|null,
     *     permission_prefix?: string|null,
     *     is_active?: bool,
     *     is_divider?: bool,
     * }  $attributes
     * @param  array<int, string|array{action: string, label?: string, route_name?: string|null, order?: int}>  $actions
     * @param  array<int, Role>  $roles
     */
    protected function seedMenuWithActions(array $attributes, array $actions = [], array $roles = []): Menu
    {
        $parentId = $attributes['parent_id'] ?? null;
        $isDivider = $attributes['is_divider'] ?? false;
        $permissionPrefix = $attributes['permission_prefix'] ?? null;

        $lookup = ['parent_id' => $parentId, 'is_divider' => $isDivider];

        if ($permissionPrefix !== null && $permissionPrefix !== '') {
            $lookup['permission_prefix'] = $permissionPrefix;
        } else {
            $lookup['label_name'] = $attributes['label_name'];
        }

        $menu = Menu::query()->updateOrCreate($lookup, [
            'sort_num' => $attributes['sort_num'],
            'icon' => $attributes['icon'] ?? null,
            'label_name' => $attributes['label_name'],
            'controller_name' => $attributes['controller_name'] ?? null,
            'route_name' => $attributes['route_name'] ?? null,
            'url' => $attributes['url'] ?? null,
            'permission_prefix' => $permissionPrefix,
            'is_active' => $attributes['is_active'] ?? true,
            'is_divider' => $isDivider,
        ]);

        $createdPermissionIds = [];

        if ($permissionPrefix !== null && $permissionPrefix !== '') {
            foreach ($actions as $index => $actionConfig) {
                $action = is_array($actionConfig) ? $actionConfig['action'] : $actionConfig;
                $label = is_array($actionConfig) && isset($actionConfig['label'])
                    ? $actionConfig['label']
                    : (self::DEFAULT_ACTION_LABELS[$action] ?? ucfirst($action));
                $routeName = is_array($actionConfig) ? ($actionConfig['route_name'] ?? null) : null;
                $order = is_array($actionConfig) && isset($actionConfig['order'])
                    ? $actionConfig['order']
                    : ($index + 1);
                $permissionName = "{$permissionPrefix}.{$action}";

                MenuAction::query()->updateOrCreate(
                    ['menu_id' => $menu->id, 'action' => $action],
                    [
                        'label' => $label,
                        'permission_name' => $permissionName,
                        'route_name' => $routeName,
                        'order' => $order,
                    ],
                );

                $permission = Permission::query()->updateOrCreate(
                    ['name' => $permissionName, 'guard_name' => 'web'],
                    ['group' => null],
                );

                $createdPermissionIds[] = $permission->id;
            }
        }

        if ($roles !== []) {
            $roleIds = array_map(static fn (Role $role): string => $role->id, $roles);
            $menu->roles()->syncWithoutDetaching($roleIds);

            if ($createdPermissionIds !== []) {
                foreach ($roles as $role) {
                    $role->permissions()->syncWithoutDetaching($createdPermissionIds);
                }
            }
        }

        return $menu;
    }

    /**
     * @param  array<int, Role>  $roles
     */
    protected function seedDivider(string $labelName, int $sortNum, array $roles = []): Menu
    {
        return $this->seedMenuWithActions([
            'label_name' => $labelName,
            'sort_num' => $sortNum,
            'is_divider' => true,
            'is_active' => true,
        ], [], $roles);
    }
}
