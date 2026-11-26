<?php

declare(strict_types=1);

namespace App\Services\Rbac;

use App\Models\Menu;
use App\Models\MenuAction;
use App\Models\Permission;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

class MenuActionManagementService
{
    public function __construct(
        private readonly PermissionRegistrar $permissionRegistrar,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listGroupedByMenu(): array
    {
        return Menu::query()
            ->where('is_divider', false)
            ->with([
                'parent:id,label_name',
                'actions' => fn ($query) => $query->orderBy('order')->orderBy('action'),
            ])
            ->orderBy('sort_num')
            ->orderBy('label_name')
            ->get()
            ->map(fn (Menu $menu): array => [
                'id' => $menu->id,
                'label_name' => $menu->label_name,
                'parent_label' => $menu->parent?->label_name,
                'permission_prefix' => $menu->permission_prefix,
                'is_active' => $menu->is_active,
                'actions' => $menu->actions->map(fn (MenuAction $action): array => [
                    'id' => $action->id,
                    'menu_id' => $action->menu_id,
                    'action' => $action->action,
                    'label' => $action->label,
                    'permission_name' => $action->permission_name,
                    'route_name' => $action->route_name,
                    'order' => $action->order,
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array{action: string, label: string, route_name?: string|null, order: int}  $data
     */
    public function create(Menu $menu, array $data): MenuAction
    {
        $prefix = trim((string) ($menu->permission_prefix ?? ''));

        if ($prefix === '') {
            throw ValidationException::withMessages([
                'action' => 'Menu ini belum memiliki permission_prefix. Atur prefix izin pada menu terlebih dahulu.',
            ]);
        }

        $actionSlug = trim($data['action']);
        $permissionName = "{$prefix}.{$actionSlug}";

        $menuAction = DB::transaction(function () use ($menu, $data, $actionSlug, $permissionName): MenuAction {
            $exists = Permission::query()
                ->where('name', $permissionName)
                ->where('guard_name', 'web')
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'action' => "Izin \"{$permissionName}\" sudah terdaftar di sistem.",
                ]);
            }

            Permission::query()->create([
                'name' => $permissionName,
                'guard_name' => 'web',
                'group' => null,
            ]);

            return MenuAction::query()->create([
                'menu_id' => $menu->id,
                'action' => $actionSlug,
                'label' => trim($data['label']),
                'permission_name' => $permissionName,
                'route_name' => isset($data['route_name']) && trim((string) $data['route_name']) !== ''
                    ? trim((string) $data['route_name'])
                    : null,
                'order' => (int) $data['order'],
            ]);
        });

        $this->permissionRegistrar->forgetCachedPermissions();

        return $menuAction;
    }

    /**
     * @param  array{action: string, label: string, route_name?: string|null, order: int}  $data
     */
    public function update(MenuAction $menuAction, array $data): MenuAction
    {
        $menuAction->loadMissing('menu');
        $prefix = trim((string) ($menuAction->menu->permission_prefix ?? ''));

        if ($prefix === '') {
            throw ValidationException::withMessages([
                'action' => 'Menu induk tidak memiliki permission_prefix yang valid.',
            ]);
        }

        $actionSlug = trim($data['action']);
        $newPermissionName = "{$prefix}.{$actionSlug}";

        $updated = DB::transaction(function () use ($menuAction, $data, $actionSlug, $newPermissionName): MenuAction {
            if ($newPermissionName !== $menuAction->permission_name) {
                $collision = Permission::query()
                    ->where('name', $newPermissionName)
                    ->where('guard_name', 'web')
                    ->exists();

                if ($collision) {
                    throw ValidationException::withMessages([
                        'action' => "Izin \"{$newPermissionName}\" sudah digunakan.",
                    ]);
                }

                $updatedCount = Permission::query()
                    ->where('name', $menuAction->permission_name)
                    ->where('guard_name', 'web')
                    ->update(['name' => $newPermissionName]);

                if ($updatedCount === 0) {
                    Permission::query()->create([
                        'name' => $newPermissionName,
                        'guard_name' => 'web',
                        'group' => null,
                    ]);
                }
            }

            $menuAction->update([
                'action' => $actionSlug,
                'label' => trim($data['label']),
                'permission_name' => $newPermissionName,
                'route_name' => isset($data['route_name']) && trim((string) $data['route_name']) !== ''
                    ? trim((string) $data['route_name'])
                    : null,
                'order' => (int) $data['order'],
            ]);

            return $menuAction->refresh();
        });

        $this->permissionRegistrar->forgetCachedPermissions();

        return $updated;
    }

    public function delete(MenuAction $menuAction): void
    {
        DB::transaction(function () use ($menuAction): void {
            Permission::query()
                ->where('name', $menuAction->permission_name)
                ->where('guard_name', 'web')
                ->delete();

            $menuAction->delete();
        });

        $this->permissionRegistrar->forgetCachedPermissions();
    }
}
