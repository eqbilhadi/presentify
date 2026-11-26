<?php

declare(strict_types=1);

namespace App\Http\Controllers\Universal\Rbac;

use App\Http\Controllers\Controller;
use App\Http\Requests\Universal\Rbac\MenuActionRequest;
use App\Models\Menu;
use App\Models\MenuAction;
use App\Services\Rbac\MenuActionManagementService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class MenuActionController extends Controller
{
    public function __construct(private readonly MenuActionManagementService $menuActionManagementService) {}

    public function store(MenuActionRequest $request, Menu $menu): RedirectResponse
    {
        /** @var array{action: string, label: string, route_name?: string|null, order: int} $payload */
        $payload = $request->validated();
        $menuAction = $this->menuActionManagementService->create($menu, $payload);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Aksi \"{$menuAction->permission_name}\" berhasil ditambahkan.",
        ]);

        return back();
    }

    public function update(MenuActionRequest $request, MenuAction $menuAction): RedirectResponse
    {
        /** @var array{action: string, label: string, route_name?: string|null, order: int} $payload */
        $payload = $request->validated();
        $updated = $this->menuActionManagementService->update($menuAction, $payload);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Aksi \"{$updated->permission_name}\" berhasil diperbarui.",
        ]);

        return back();
    }

    public function destroy(MenuAction $menuAction): RedirectResponse
    {
        $permissionName = $menuAction->permission_name;
        $this->menuActionManagementService->delete($menuAction);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Aksi \"{$permissionName}\" berhasil dihapus.",
        ]);

        return back();
    }
}
