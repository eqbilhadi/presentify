<?php

declare(strict_types=1);

namespace App\Services\Rbac;

use App\Models\Menu;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Handles CRUD and 2-tier permission matrix resolution for tenant roles.
 */
class RoleBuilderService
{
    private const MATRIX_ACTIONS = ['view', 'create', 'edit', 'delete', 'export', 'import'];

    public function __construct(private readonly PermissionService $permissionService) {}

    /**
     * @return Collection<int, array{
     *     id: int,
     *     name: string,
     *     label: string,
     *     description: string|null,
     *     is_system: bool,
     *     members_count: int,
     *     menu_permissions_count: int,
     *     global_permissions_count: int,
     * }>
     */
    public function listRoles(): Collection
    {
        return Role::query()
            ->withCount([
                'members',
                'menuBoundPermissions as menu_permissions_count',
                'globalPermissions as global_permissions_count',
            ])
            ->orderByDesc('is_system')
            ->orderBy('label')
            ->get()
            ->map(fn (Role $role): array => [
                'id' => $role->id,
                'name' => $role->name,
                'label' => $role->label,
                'description' => $role->description,
                'is_system' => $role->is_system,
                'members_count' => (int) ($role->members_count ?? 0),
                'menu_permissions_count' => (int) ($role->menu_permissions_count ?? 0),
                'global_permissions_count' => (int) ($role->global_permissions_count ?? 0),
            ]);
    }

    /**
     * Builds the 2-tier permission catalog: ordered menu rows for Tier-1 and global permissions for Tier-2.
     *
     * @return array{
     *     actions: array<int, array{key: string, label: string}>,
     *     menu_rows: array<int, array<string, mixed>>,
     *     global_permissions: array<int, array<string, mixed>>,
     * }
     */
    public function getPermissionMatrix(): array
    {
        $menus = Menu::query()
            ->with('permissions')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $menusByParent = $menus->groupBy(fn (Menu $menu): string => (string) ($menu->parent_id ?? 'root'));
        $menuRows = [];

        foreach ($menusByParent->get('root', collect()) as $parentMenu) {
            /** @var Collection<int, Menu> $children */
            $children = $menusByParent->get((string) $parentMenu->id, collect());

            if ($parentMenu->permissions->isNotEmpty() || $children->isNotEmpty()) {
                $menuRows[] = $this->formatMenuRow($parentMenu, null);
            }

            foreach ($children as $childMenu) {
                if ($childMenu->permissions->isNotEmpty()) {
                    $menuRows[] = $this->formatMenuRow($childMenu, $parentMenu->display_label);
                }
            }
        }

        $globalPermissions = Permission::query()
            ->whereNull('menu_id')
            ->orderBy('id')
            ->get()
            ->map(fn (Permission $permission): array => [
                'id' => $permission->id,
                'name' => $permission->name,
                'label' => $permission->label,
                'action' => $permission->action,
            ])
            ->values()
            ->all();

        return [
            'actions' => [
                ['key' => 'view', 'label' => 'Lihat'],
                ['key' => 'create', 'label' => 'Tambah'],
                ['key' => 'edit', 'label' => 'Ubah'],
                ['key' => 'delete', 'label' => 'Hapus'],
                ['key' => 'export', 'label' => 'Ekspor'],
                ['key' => 'import', 'label' => 'Impor'],
            ],
            'menu_rows' => $menuRows,
            'global_permissions' => $globalPermissions,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getRoleForEditor(Role $role): array
    {
        $role->load('permissions');

        return [
            'id' => $role->id,
            'name' => $role->name,
            'label' => $role->label,
            'description' => $role->description,
            'is_system' => $role->is_system,
            'permission_ids' => $role->permissions->pluck('id')->map(fn ($id): int => (int) $id)->values()->all(),
        ];
    }

    /**
     * @param  array{label: string, name?: string|null, description?: string|null, permission_ids?: array<int, int|string>}  $data
     */
    public function createRole(array $data): Role
    {
        return DB::transaction(function () use ($data): Role {
            $slug = $this->resolveUniqueSlug($data['name'] ?? null, $data['label']);

            $role = Role::create([
                'name' => $slug,
                'label' => trim($data['label']),
                'description' => isset($data['description']) && trim($data['description']) !== ''
                    ? trim($data['description'])
                    : null,
                'is_system' => false,
            ]);

            $resolvedPermissionIds = $this->resolveTwoTierPermissions(
                array_map('intval', $data['permission_ids'] ?? [])
            );

            $role->syncPermissions($resolvedPermissionIds);
            $this->permissionService->flushCache();

            return $role->load('permissions');
        });
    }

    /**
     * @param  array{label?: string, description?: string|null, permission_ids: array<int, int|string>}  $data
     */
    public function updateRole(Role $role, array $data): Role
    {
        return DB::transaction(function () use ($role, $data): Role {
            $updates = [];

            if (isset($data['label'])) {
                $updates['label'] = trim($data['label']);
            }

            if (array_key_exists('description', $data)) {
                $updates['description'] = $data['description'] !== null && trim($data['description']) !== ''
                    ? trim($data['description'])
                    : null;
            }

            if ($updates !== []) {
                $role->update($updates);
            }

            $resolvedPermissionIds = $this->resolveTwoTierPermissions(
                array_map('intval', $data['permission_ids'])
            );

            $role->syncPermissions($resolvedPermissionIds);
            $this->permissionService->flushCache();

            return $role->refresh()->load('permissions');
        });
    }

    /**
     * @param  array<int, int|string>  $permissionIds
     */
    public function updatePermissions(Role $role, array $permissionIds): Role
    {
        return DB::transaction(function () use ($role, $permissionIds): Role {
            $resolvedPermissionIds = $this->resolveTwoTierPermissions(
                array_map('intval', $permissionIds)
            );

            $role->syncPermissions($resolvedPermissionIds);
            $this->permissionService->flushCache();

            return $role->refresh()->load('permissions');
        });
    }

    public function deleteRole(Role $role): void
    {
        if ($role->is_system) {
            throw ValidationException::withMessages([
                'role' => 'Peran bawaan sistem tidak dapat dihapus.',
            ]);
        }

        DB::transaction(function () use ($role): void {
            $role->permissions()->detach();
            $role->members()->detach();
            $role->delete();
        });

        $this->permissionService->flushCache();
    }

    /**
     * Enforces the PRD 3.2 dependency rule: selecting any non-view action on a menu
     * automatically includes that menu's `view` permission.
     *
     * @param  array<int, int>  $permissionIds
     * @return array<int, int>
     */
    public function resolveTwoTierPermissions(array $permissionIds): array
    {
        $uniqueIds = array_values(array_unique(array_filter($permissionIds, fn (int $id): bool => $id > 0)));

        if ($uniqueIds === []) {
            return [];
        }

        $selectedPermissions = Permission::query()
            ->whereIn('id', $uniqueIds)
            ->get();

        $resolvedIds = $selectedPermissions->pluck('id')->map(fn ($id): int => (int) $id)->all();

        $menuIdsNeedingView = $selectedPermissions
            ->filter(fn (Permission $permission): bool => $permission->isMenuBound() && $permission->action !== 'view')
            ->pluck('menu_id')
            ->filter()
            ->unique()
            ->values();

        if ($menuIdsNeedingView->isNotEmpty()) {
            $viewIds = Permission::query()
                ->whereIn('menu_id', $menuIdsNeedingView->all())
                ->where('action', 'view')
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            $resolvedIds = array_merge($resolvedIds, $viewIds);
        }

        $resolvedIds = array_values(array_unique($resolvedIds));
        sort($resolvedIds);

        return $resolvedIds;
    }

    /**
     * @return array<string, mixed>
     */
    private function formatMenuRow(Menu $menu, ?string $parentLabel): array
    {
        $permissionsByAction = [];

        foreach ($menu->permissions as $permission) {
            if (in_array($permission->action, self::MATRIX_ACTIONS, true)) {
                $permissionsByAction[$permission->action] = [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'label' => $permission->label,
                    'action' => $permission->action,
                ];
            }
        }

        return [
            'id' => $menu->id,
            'key' => $menu->key,
            'label' => $menu->display_label,
            'parent_id' => $menu->parent_id,
            'parent_label' => $parentLabel,
            'is_group' => $menu->route_name === null && $menu->permissions->isEmpty(),
            'permissions' => $permissionsByAction,
        ];
    }

    private function resolveUniqueSlug(?string $requestedName, string $label): string
    {
        $base = $requestedName !== null && trim($requestedName) !== ''
            ? Str::slug(trim($requestedName), '_')
            : Str::slug($label, '_');

        if ($base === '') {
            $base = 'custom_role';
        }

        $candidate = $base;
        $counter = 2;

        while (Role::query()->where('name', $candidate)->exists()) {
            $candidate = "{$base}_{$counter}";
            $counter++;
        }

        return $candidate;
    }
}
