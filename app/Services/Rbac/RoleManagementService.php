<?php

declare(strict_types=1);

namespace App\Services\Rbac;

use App\Models\Menu;
use App\Models\MenuAction;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

class RoleManagementService
{
    public function __construct(
        private readonly MenuService $menuService,
        private readonly PermissionRegistrar $permissionRegistrar,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listRoles(): array
    {
        return Role::query()
            ->withCount(['menus', 'permissions'])
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role): array => [
                'id' => $role->id,
                'name' => $role->name,
                'guard_name' => $role->guard_name,
                'users_count' => $role->assignedUsersCount(),
                'menus_count' => (int) ($role->menus_count ?? 0),
                'permissions_count' => (int) ($role->permissions_count ?? 0),
                'is_protected' => $role->name === 'superadmin_saas',
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{
     *     menus: array<int, array<string, mixed>>,
     *     standalone_groups: array<int, array{group: string, permissions: array<int, array{id: string, name: string, group: string|null}>}>,
     * }
     */
    public function getMatrixData(): array
    {
        $permissionsByName = Permission::query()
            ->where('guard_name', 'web')
            ->pluck('id', 'name');

        $roots = Menu::query()
            ->whereNull('parent_id')
            ->where('is_divider', false)
            ->with([
                'actions' => fn ($query) => $query->orderBy('order')->orderBy('action'),
                'children' => fn ($query) => $query
                    ->where('is_divider', false)
                    ->with(['actions' => fn ($aq) => $aq->orderBy('order')->orderBy('action')])
                    ->orderBy('sort_num')
                    ->orderBy('label_name'),
            ])
            ->orderBy('sort_num')
            ->orderBy('label_name')
            ->get();

        $formatMenu = static function (Menu $menu) use ($permissionsByName): array {
            return [
                'id' => $menu->id,
                'label_name' => $menu->label_name,
                'icon' => $menu->icon,
                'is_active' => $menu->is_active,
                'actions' => $menu->actions->map(static fn (MenuAction $action): array => [
                    'id' => $action->id,
                    'action' => $action->action,
                    'label' => $action->label,
                    'permission_name' => $action->permission_name,
                    'permission_id' => $permissionsByName->get($action->permission_name),
                ])->values()->all(),
            ];
        };

        $menuTree = $roots->map(static function (Menu $root) use ($formatMenu): array {
            $node = $formatMenu($root);
            $node['children'] = $root->children
                ->map(static fn (Menu $child): array => $formatMenu($child))
                ->values()
                ->all();

            return $node;
        })->values()->all();

        $standalonePermissions = Permission::query()
            ->standalone()
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->get();

        $standaloneGroups = $standalonePermissions
            ->groupBy(static fn (Permission $permission): string => $permission->group ?: 'Umum')
            ->map(static fn ($items, string $group): array => [
                'group' => $group,
                'permissions' => $items->map(static fn (Permission $permission): array => [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'group' => $permission->group,
                ])->values()->all(),
            ])
            ->values()
            ->all();

        return [
            'menus' => $menuTree,
            'standalone_groups' => $standaloneGroups,
        ];
    }

    /**
     * @return array{
     *     id: string,
     *     name: string,
     *     is_protected: bool,
     *     menu_ids: array<int, string>,
     *     menu_action_ids: array<int, string>,
     *     permission_ids: array<int, string>,
     * }
     */
    public function getRoleDetail(Role $role): array
    {
        $role->load(['menus:id', 'permissions:id,name']);

        $permissionNames = $role->permissions->pluck('name')->all();

        $menuActionIds = $permissionNames === []
            ? []
            : MenuAction::query()
                ->whereIn('permission_name', $permissionNames)
                ->pluck('id')
                ->values()
                ->all();

        return [
            'id' => $role->id,
            'name' => $role->name,
            'is_protected' => $role->name === 'superadmin_saas',
            'menu_ids' => $role->menus->pluck('id')->values()->all(),
            'menu_action_ids' => $menuActionIds,
            'permission_ids' => $role->permissions->pluck('id')->values()->all(),
        ];
    }

    /**
     * @param  array{
     *     name: string,
     *     menu_ids?: array<int, string>|null,
     *     menu_action_ids?: array<int, string>|null,
     *     permission_ids?: array<int, string>|null,
     * }  $data
     */
    public function create(array $data): Role
    {
        $role = DB::transaction(function () use ($data): Role {
            /** @var Role $role */
            $role = Role::query()->create([
                'name' => trim($data['name']),
                'guard_name' => 'web',
            ]);

            $this->syncRoleRelations($role, $data);

            return $role;
        });

        $this->permissionRegistrar->forgetCachedPermissions();
        $this->menuService->invalidateForRole($role);

        return $role->refresh();
    }

    /**
     * @param  array{
     *     name: string,
     *     menu_ids?: array<int, string>|null,
     *     menu_action_ids?: array<int, string>|null,
     *     permission_ids?: array<int, string>|null,
     * }  $data
     */
    public function update(Role $role, array $data): Role
    {
        $newName = trim($data['name']);

        if ($role->name === 'superadmin_saas' && $newName !== 'superadmin_saas') {
            throw ValidationException::withMessages([
                'name' => 'Nama peran bawaan superadmin_saas tidak dapat diubah.',
            ]);
        }

        DB::transaction(function () use ($role, $newName, $data): void {
            $role->update(['name' => $newName]);
            $this->syncRoleRelations($role, $data);
        });

        $this->permissionRegistrar->forgetCachedPermissions();
        $this->menuService->invalidateForRole($role);

        return $role->refresh();
    }

    public function delete(Role $role): void
    {
        if ($role->name === 'superadmin_saas') {
            throw ValidationException::withMessages([
                'role' => 'Peran bawaan superadmin_saas tidak dapat dihapus.',
            ]);
        }

        $this->menuService->invalidateForRole($role);

        DB::transaction(function () use ($role): void {
            $role->menus()->detach();
            $role->permissions()->detach();
            $role->delete();
        });

        $this->permissionRegistrar->forgetCachedPermissions();
    }

    /**
     * @param  array{
     *     menu_ids?: array<int, string>|null,
     *     menu_action_ids?: array<int, string>|null,
     *     permission_ids?: array<int, string>|null,
     * }  $data
     */
    private function syncRoleRelations(Role $role, array $data): void
    {
        $menuIds = array_values(array_unique($data['menu_ids'] ?? []));
        $role->menus()->sync($menuIds);

        $permissionIds = $data['permission_ids'] ?? [];
        $menuActionIds = $data['menu_action_ids'] ?? [];

        if ($menuActionIds !== []) {
            $actionPermissionNames = MenuAction::query()
                ->whereIn('id', $menuActionIds)
                ->pluck('permission_name')
                ->all();

            if ($actionPermissionNames !== []) {
                $actionPermissionIds = Permission::query()
                    ->whereIn('name', $actionPermissionNames)
                    ->where('guard_name', 'web')
                    ->pluck('id')
                    ->all();

                $permissionIds = array_merge($permissionIds, $actionPermissionIds);
            }
        }

        $uniquePermissionIds = array_values(array_unique($permissionIds));
        $permissions = $uniquePermissionIds === []
            ? []
            : Permission::query()
                ->whereIn('id', $uniquePermissionIds)
                ->where('guard_name', 'web')
                ->get();

        $role->syncPermissions($permissions);
    }
}
