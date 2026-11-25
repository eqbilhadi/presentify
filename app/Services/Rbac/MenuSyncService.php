<?php

declare(strict_types=1);

namespace App\Services\Rbac;

use App\Models\Menu;
use App\Models\Permission;
use Illuminate\Support\Facades\DB;

/**
 * MenuSyncService — Non-destructive sync of system menus into the tenant DB.
 *
 * Rules:
 * 1. Only upserts rows that don't exist yet or whose system fields changed.
 * 2. NEVER overwrites tenant-customized fields: `custom_label`, `icon`, `sort_order`.
 * 3. Upserts menu-bound permissions for each menu entry (action list from config).
 * 4. Does NOT delete menus or permissions removed from the registry (safe for production).
 */
class MenuSyncService
{
    /** @var array<string, Menu> cache key → model for parent resolution */
    private array $menuCache = [];

    /**
     * Sync all menus from the master registry into the current tenant DB.
     * Must be called within an active tenant context (tenancy initialized).
     */
    public function sync(): void
    {
        $registry = config('presentify-menus', []);

        DB::transaction(function () use ($registry) {
            // Pass 1: upsert top-level menus (parent = null)
            foreach ($registry as $entry) {
                if ($entry['parent'] === null) {
                    $this->upsertMenu($entry);
                }
            }

            // Pass 2: upsert child menus (parent resolved from cache)
            foreach ($registry as $entry) {
                if ($entry['parent'] !== null) {
                    $this->upsertMenu($entry);
                }
            }
        });
    }

    /**
     * @param array{
     *   key: string,
     *   label: string,
     *   icon: string,
     *   route_name: string|null,
     *   parent: string|null,
     *   sort_order: int,
     *   permissions: string[],
     * } $entry
     */
    private function upsertMenu(array $entry): void
    {
        $parentId = null;
        if ($entry['parent'] !== null) {
            $parentId = isset($this->menuCache[$entry['parent']])
                ? $this->menuCache[$entry['parent']]->id
                : null;
        }

        // Find existing menu by key
        $menu = Menu::where('key', $entry['key'])->first();

        if ($menu === null) {
            // Create brand-new entry — all fields from registry
            $menu = Menu::create([
                'key' => $entry['key'],
                'label' => $entry['label'],
                'icon' => $entry['icon'],
                'route_name' => $entry['route_name'],
                'parent_id' => $parentId,
                'sort_order' => $entry['sort_order'],
                'is_visible' => true,
                // custom_label intentionally left null — tenant fills this later
            ]);
        } else {
            // Update only system-owned fields; skip tenant-customizable ones
            $menu->update([
                'label' => $entry['label'],
                'route_name' => $entry['route_name'],
                'parent_id' => $parentId,
                // NOT updated: custom_label, icon, sort_order
            ]);
        }

        $this->menuCache[$entry['key']] = $menu;

        // Sync menu-bound permissions for this menu
        $this->syncMenuPermissions($menu, $entry['permissions']);
    }

    /**
     * Upsert Tier-1 (menu-bound) permissions for the given menu.
     *
     * @param  string[]  $actions
     */
    private function syncMenuPermissions(Menu $menu, array $actions): void
    {
        foreach ($actions as $action) {
            $permName = "{$menu->key}.{$action}";

            Permission::updateOrCreate(
                ['name' => $permName, 'menu_id' => $menu->id],
                [
                    'label' => $this->buildPermissionLabel($menu->label, $action),
                    'action' => $action,
                ],
            );
        }
    }

    private function buildPermissionLabel(string $menuLabel, string $action): string
    {
        $actionLabels = [
            'view' => 'Lihat',
            'create' => 'Tambah',
            'edit' => 'Ubah',
            'delete' => 'Hapus',
            'export' => 'Ekspor',
            'import' => 'Impor',
        ];

        $verb = $actionLabels[$action] ?? ucfirst($action);

        return "{$verb} {$menuLabel}";
    }
}
