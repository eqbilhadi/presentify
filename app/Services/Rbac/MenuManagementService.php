<?php

declare(strict_types=1);

namespace App\Services\Rbac;

use App\Models\Menu;
use App\Models\Permission;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

class MenuManagementService
{
    public function __construct(
        private readonly MenuService $menuService,
        private readonly PermissionRegistrar $permissionRegistrar,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listForIndex(?string $search = null, ?string $status = null): array
    {
        $query = Menu::query()
            ->with('parent:id,label_name')
            ->withCount(['actions', 'roles'])
            ->orderBy('sort_num')
            ->orderBy('label_name');

        if ($search !== null && trim($search) !== '') {
            $term = '%'.strtolower(trim($search)).'%';
            $query->where(function ($q) use ($term): void {
                $q->whereRaw('LOWER(label_name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(COALESCE(route_name, \'\')) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(COALESCE(url, \'\')) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(COALESCE(permission_prefix, \'\')) LIKE ?', [$term]);
            });
        }

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        return $query->get()
            ->map(fn (Menu $menu): array => [
                'id' => $menu->id,
                'parent_id' => $menu->parent_id,
                'parent_label' => $menu->parent?->label_name,
                'sort_num' => $menu->sort_num,
                'icon' => $menu->icon,
                'label_name' => $menu->label_name,
                'controller_name' => $menu->controller_name,
                'route_name' => $menu->route_name,
                'url' => $menu->url,
                'permission_prefix' => $menu->permission_prefix,
                'is_active' => $menu->is_active,
                'is_divider' => $menu->is_divider,
                'actions_count' => (int) ($menu->actions_count ?? 0),
                'roles_count' => (int) ($menu->roles_count ?? 0),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: string, label_name: string}>
     */
    public function getParentOptions(?string $excludeMenuId = null): array
    {
        $query = Menu::query()
            ->whereNull('parent_id')
            ->where('is_divider', false)
            ->orderBy('sort_num')
            ->orderBy('label_name');

        if ($excludeMenuId !== null) {
            $query->where('id', '!=', $excludeMenuId);
        }

        return $query->get(['id', 'label_name'])
            ->map(fn (Menu $menu): array => [
                'id' => $menu->id,
                'label_name' => $menu->label_name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getSortTree(): array
    {
        $roots = Menu::query()
            ->whereNull('parent_id')
            ->with(['children' => fn ($query) => $query->orderBy('sort_num')->orderBy('label_name')])
            ->orderBy('sort_num')
            ->orderBy('label_name')
            ->get();

        return $roots->map(fn (Menu $root): array => [
            'id' => $root->id,
            'parent_id' => $root->parent_id,
            'sort_num' => $root->sort_num,
            'icon' => $root->icon,
            'label_name' => $root->label_name,
            'is_active' => $root->is_active,
            'is_divider' => $root->is_divider,
            'children' => $root->children->map(fn (Menu $child): array => [
                'id' => $child->id,
                'parent_id' => $child->parent_id,
                'sort_num' => $child->sort_num,
                'icon' => $child->icon,
                'label_name' => $child->label_name,
                'is_active' => $child->is_active,
                'is_divider' => $child->is_divider,
                'children' => [],
            ])->values()->all(),
        ])->values()->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Menu
    {
        $menu = DB::transaction(function () use ($data): Menu {
            $payload = $this->normalizePayload($data);

            return Menu::query()->create($payload);
        });

        $this->menuService->invalidateAllInContext();

        return $menu;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Menu $menu, array $data): Menu
    {
        $payload = $this->normalizePayload($data);
        $this->assertValidParent($menu, $payload['parent_id']);

        $updatedMenu = DB::transaction(function () use ($menu, $payload): Menu {
            $oldPrefix = $menu->permission_prefix;
            $newPrefix = $payload['permission_prefix'];

            $menu->update($payload);

            if ($oldPrefix !== $newPrefix && is_string($newPrefix) && $newPrefix !== '') {
                $this->syncPrefixedActions($menu, $newPrefix);
            }

            return $menu->refresh();
        });

        $this->permissionRegistrar->forgetCachedPermissions();
        $this->menuService->invalidateAllInContext();

        return $updatedMenu;
    }

    public function toggleStatus(Menu $menu): Menu
    {
        $menu->update(['is_active' => ! $menu->is_active]);
        $this->menuService->invalidateAllInContext();

        return $menu->refresh();
    }

    /**
     * @param  array<int, array{id: string, parent_id: string|null, sort_num: int}>  $items
     */
    public function reorder(array $items): void
    {
        $parentById = [];

        foreach ($items as $item) {
            if ($item['parent_id'] === $item['id']) {
                throw ValidationException::withMessages([
                    'items' => 'Menu tidak boleh menjadi induk bagi dirinya sendiri.',
                ]);
            }

            $parentById[$item['id']] = $item['parent_id'];
        }

        foreach ($parentById as $id => $parentId) {
            $visited = [$id => true];
            $cursor = $parentId;

            while ($cursor !== null) {
                if (isset($visited[$cursor])) {
                    throw ValidationException::withMessages([
                        'items' => 'Urutan menu mengandung relasi induk-anak melingkar.',
                    ]);
                }

                $visited[$cursor] = true;
                $cursor = $parentById[$cursor] ?? null;
            }
        }

        DB::transaction(function () use ($items): void {
            foreach ($items as $item) {
                Menu::query()
                    ->where('id', $item['id'])
                    ->update([
                        'parent_id' => $item['parent_id'],
                        'sort_num' => $item['sort_num'],
                    ]);
            }
        });

        $this->menuService->invalidateAllInContext();
    }

    public function delete(Menu $menu): void
    {
        DB::transaction(function () use ($menu): void {
            $childActionNames = $menu->children()
                ->with('actions')
                ->get()
                ->flatMap(fn (Menu $child): Collection => $child->actions->pluck('permission_name'));

            $ownActionNames = $menu->actions()->pluck('permission_name');
            $allPermissionNames = $ownActionNames->merge($childActionNames)->unique()->values()->all();

            if ($allPermissionNames !== []) {
                Permission::query()
                    ->whereIn('name', $allPermissionNames)
                    ->where('guard_name', 'web')
                    ->delete();
            }

            $menu->delete();
        });

        $this->permissionRegistrar->forgetCachedPermissions();
        $this->menuService->invalidateAllInContext();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{
     *     parent_id: string|null,
     *     sort_num: int,
     *     icon: string|null,
     *     label_name: string,
     *     controller_name: string|null,
     *     route_name: string|null,
     *     url: string|null,
     *     permission_prefix: string|null,
     *     is_active: bool,
     *     is_divider: bool,
     * }
     */
    private function normalizePayload(array $data): array
    {
        $isDivider = (bool) ($data['is_divider'] ?? false);

        return [
            'parent_id' => $isDivider ? null : $this->nullableTrim($data['parent_id'] ?? null),
            'sort_num' => (int) ($data['sort_num'] ?? 0),
            'icon' => $isDivider ? null : $this->nullableTrim($data['icon'] ?? null),
            'label_name' => trim((string) $data['label_name']),
            'controller_name' => $isDivider ? null : $this->nullableTrim($data['controller_name'] ?? null),
            'route_name' => $isDivider ? null : $this->nullableTrim($data['route_name'] ?? null),
            'url' => $isDivider ? null : $this->nullableTrim($data['url'] ?? null),
            'permission_prefix' => $isDivider ? null : $this->nullableTrim($data['permission_prefix'] ?? null),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'is_divider' => $isDivider,
        ];
    }

    private function nullableTrim(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function assertValidParent(Menu $menu, ?string $parentId): void
    {
        if ($parentId === null) {
            return;
        }

        if ($parentId === $menu->id) {
            throw ValidationException::withMessages([
                'parent_id' => 'Menu tidak boleh menjadi induk bagi dirinya sendiri.',
            ]);
        }

        $cursorId = $parentId;
        $visited = [$menu->id => true];

        while ($cursorId !== null) {
            if (isset($visited[$cursorId])) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Menu induk yang dipilih akan membuat hierarki melingkar.',
                ]);
            }

            $visited[$cursorId] = true;
            $cursorId = Menu::query()->where('id', $cursorId)->value('parent_id');
        }
    }

    private function syncPrefixedActions(Menu $menu, string $newPrefix): void
    {
        foreach ($menu->actions()->get() as $action) {
            $newPermissionName = "{$newPrefix}.{$action->action}";

            if ($newPermissionName === $action->permission_name) {
                continue;
            }

            $collision = Permission::query()
                ->where('name', $newPermissionName)
                ->where('guard_name', 'web')
                ->exists();

            if ($collision) {
                throw ValidationException::withMessages([
                    'permission_prefix' => "Izin \"{$newPermissionName}\" sudah digunakan oleh modul lain.",
                ]);
            }

            Permission::query()
                ->where('name', $action->permission_name)
                ->where('guard_name', 'web')
                ->update(['name' => $newPermissionName]);

            $action->update(['permission_name' => $newPermissionName]);
        }
    }
}
