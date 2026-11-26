<?php

declare(strict_types=1);

namespace App\Services\Rbac;

use App\Models\Menu;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class MenuService
{
    public function cacheKey(User|int $user): string
    {
        $userId = $user instanceof User ? $user->getKey() : $user;
        $tenant = tenancy()->initialized ? tenancy()->tenant : null;
        $context = $tenant instanceof Tenant
            ? (string) $tenant->getTenantKey()
            : 'central';

        return "menus:{$context}:{$userId}";
    }

    /**
     * @return array<int, array{
     *     id: string,
     *     parent_id: string|null,
     *     sort_num: int,
     *     icon: string|null,
     *     label_name: string,
     *     route_name: string|null,
     *     url: string|null,
     *     permission_prefix: string|null,
     *     is_divider: bool,
     *     children: array<int, array<string, mixed>>,
     * }>
     */
    public function getMenusForUser(User $user): array
    {
        /** @var array<int, array{id: string, parent_id: string|null, sort_num: int, icon: string|null, label_name: string, route_name: string|null, url: string|null, permission_prefix: string|null, is_divider: bool, children: array<int, array<string, mixed>>}> */
        return Cache::remember(
            $this->cacheKey($user),
            now()->addHours(12),
            fn (): array => $this->buildTreeForUser($user),
        );
    }

    public function invalidateForUser(User|int $user): void
    {
        Cache::forget($this->cacheKey($user));
    }

    public function invalidateForRole(Role $role): void
    {
        $connection = $role->getConnectionName() ?? DB::getDefaultConnection();

        $userIds = DB::connection($connection)
            ->table(config('permission.table_names.model_has_roles', 'rbac_model_has_roles'))
            ->where('role_id', $role->getKey())
            ->where('model_type', User::class)
            ->pluck('model_id');

        foreach ($userIds as $userId) {
            $this->invalidateForUser((int) $userId);
        }
    }

    public function invalidateAllInContext(): void
    {
        $connection = DB::getDefaultConnection();

        $userIds = DB::connection($connection)
            ->table(config('permission.table_names.model_has_roles', 'rbac_model_has_roles'))
            ->where('model_type', User::class)
            ->distinct()
            ->pluck('model_id');

        foreach ($userIds as $userId) {
            $this->invalidateForUser((int) $userId);
        }
    }

    /**
     * @return array<int, array{
     *     id: string,
     *     parent_id: string|null,
     *     sort_num: int,
     *     icon: string|null,
     *     label_name: string,
     *     route_name: string|null,
     *     url: string|null,
     *     permission_prefix: string|null,
     *     is_divider: bool,
     *     children: array<int, array<string, mixed>>,
     * }>
     */
    private function buildTreeForUser(User $user): array
    {
        $roleIds = $user->roles()
            ->pluck(config('permission.table_names.roles', 'rbac_roles').'.id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();

        if ($roleIds === []) {
            return [];
        }

        $rootMenus = Menu::query()
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->with([
                'roles:id',
                'activeChildren.roles:id',
            ])
            ->orderBy('sort_num')
            ->get();

        $items = [];

        foreach ($rootMenus as $menu) {
            if ($menu->is_divider) {
                $dividerRoleIds = $menu->roles->pluck('id')->map(static fn (mixed $id): string => (string) $id);

                if ($dividerRoleIds->isEmpty() || $dividerRoleIds->intersect($roleIds)->isNotEmpty()) {
                    $items[] = $this->formatMenuNode($menu, []);
                }

                continue;
            }

            $visibleChildren = $this->filterChildren($menu->activeChildren, $roleIds);
            $hasDirectAccess = $menu->roles
                ->pluck('id')
                ->map(static fn (mixed $id): string => (string) $id)
                ->intersect($roleIds)
                ->isNotEmpty();

            if (! $hasDirectAccess && $visibleChildren === []) {
                continue;
            }

            $items[] = $this->formatMenuNode($menu, $visibleChildren);
        }

        return $this->trimDividers($items);
    }

    /**
     * @param  Collection<int, Menu>  $children
     * @param  array<int, string>  $roleIds
     * @return array<int, array<string, mixed>>
     */
    private function filterChildren(Collection $children, array $roleIds): array
    {
        $result = [];

        foreach ($children as $child) {
            if ($child->is_divider) {
                continue;
            }

            $hasAccess = $child->roles
                ->pluck('id')
                ->map(static fn (mixed $id): string => (string) $id)
                ->intersect($roleIds)
                ->isNotEmpty();

            if ($hasAccess) {
                $result[] = $this->formatMenuNode($child, []);
            }
        }

        return $result;
    }

    /**
     * @param  array<int, array<string, mixed>>  $children
     * @return array{
     *     id: string,
     *     parent_id: string|null,
     *     sort_num: int,
     *     icon: string|null,
     *     label_name: string,
     *     route_name: string|null,
     *     url: string|null,
     *     permission_prefix: string|null,
     *     is_divider: bool,
     *     children: array<int, array<string, mixed>>,
     * }
     */
    private function formatMenuNode(Menu $menu, array $children): array
    {
        return [
            'id' => $menu->id,
            'parent_id' => $menu->parent_id,
            'sort_num' => $menu->sort_num,
            'icon' => $menu->icon,
            'label_name' => $menu->label_name,
            'route_name' => $menu->route_name,
            'url' => $menu->url,
            'permission_prefix' => $menu->permission_prefix,
            'is_divider' => $menu->is_divider,
            'children' => $children,
        ];
    }

    /**
     * Remove leading, trailing, or consecutive dividers so sidebar groups render cleanly.
     *
     * @param  array<int, array{
     *     id: string,
     *     parent_id: string|null,
     *     sort_num: int,
     *     icon: string|null,
     *     label_name: string,
     *     route_name: string|null,
     *     url: string|null,
     *     permission_prefix: string|null,
     *     is_divider: bool,
     *     children: array<int, array<string, mixed>>,
     * }>  $items
     * @return array<int, array{
     *     id: string,
     *     parent_id: string|null,
     *     sort_num: int,
     *     icon: string|null,
     *     label_name: string,
     *     route_name: string|null,
     *     url: string|null,
     *     permission_prefix: string|null,
     *     is_divider: bool,
     *     children: array<int, array<string, mixed>>,
     * }>
     */
    private function trimDividers(array $items): array
    {
        $trimmed = [];

        foreach ($items as $item) {
            if ($item['is_divider'] && ($trimmed === [] || $trimmed[array_key_last($trimmed)]['is_divider'])) {
                continue;
            }

            $trimmed[] = $item;
        }

        while ($trimmed !== [] && $trimmed[array_key_last($trimmed)]['is_divider']) {
            array_pop($trimmed);
        }

        return $trimmed;
    }
}
