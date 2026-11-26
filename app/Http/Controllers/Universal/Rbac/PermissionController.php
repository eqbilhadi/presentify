<?php

declare(strict_types=1);

namespace App\Http\Controllers\Universal\Rbac;

use App\Http\Controllers\Controller;
use App\Http\Requests\Universal\Rbac\PermissionRequest;
use App\Models\Permission;
use App\Services\Rbac\MenuActionManagementService;
use App\Services\Rbac\PermissionManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PermissionController extends Controller
{
    public function __construct(
        private readonly PermissionManagementService $permissionManagementService,
        private readonly MenuActionManagementService $menuActionManagementService,
    ) {}

    public function index(Request $request): Response
    {
        $search = $request->query('search');
        $group = $request->query('group');

        return Inertia::render('universal/rbac/permission/index', [
            'menuGroups' => $this->menuActionManagementService->listGroupedByMenu(),
            'standalonePermissions' => $this->permissionManagementService->paginateStandalone(
                is_string($search) ? $search : null,
                is_string($group) ? $group : null,
            ),
            'groups' => $this->permissionManagementService->listGroups(),
            'filters' => [
                'search' => is_string($search) ? $search : '',
                'group' => is_string($group) ? $group : '',
            ],
        ]);
    }

    public function store(PermissionRequest $request): RedirectResponse
    {
        /** @var array{name: string, group?: string|null} $payload */
        $payload = $request->validated();
        $permission = $this->permissionManagementService->create($payload);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Hak akses \"{$permission->name}\" berhasil dibuat.",
        ]);

        return back();
    }

    public function update(PermissionRequest $request, Permission $permission): RedirectResponse
    {
        /** @var array{name: string, group?: string|null} $payload */
        $payload = $request->validated();
        $updated = $this->permissionManagementService->update($permission, $payload);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Hak akses \"{$updated->name}\" berhasil diperbarui.",
        ]);

        return back();
    }

    public function destroy(Permission $permission): RedirectResponse
    {
        $name = $permission->name;
        $this->permissionManagementService->delete($permission);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Hak akses \"{$name}\" berhasil dihapus.",
        ]);

        return back();
    }
}
