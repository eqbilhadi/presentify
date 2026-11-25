<?php

declare(strict_types=1);

namespace App\Http\Controllers\Rbac;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rbac\StoreRoleRequest;
use App\Http\Requests\Rbac\UpdateRolePermissionsRequest;
use App\Models\Role;
use App\Services\Rbac\RoleBuilderService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    public function __construct(private readonly RoleBuilderService $roleBuilderService) {}

    public function index(): Response
    {
        return Inertia::render('settings/roles/index', [
            'roles' => $this->roleBuilderService->listRoles()->values(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('settings/roles/role-editor', [
            'role' => null,
            'matrix' => $this->roleBuilderService->getPermissionMatrix(),
        ]);
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $role = $this->roleBuilderService->createRole($request->payload());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Peran \"{$role->label}\" berhasil dibuat.",
        ]);

        return to_route('settings.roles');
    }

    public function edit(Role $role): Response
    {
        return Inertia::render('settings/roles/role-editor', [
            'role' => $this->roleBuilderService->getRoleForEditor($role),
            'matrix' => $this->roleBuilderService->getPermissionMatrix(),
        ]);
    }

    public function update(UpdateRolePermissionsRequest $request, Role $role): RedirectResponse
    {
        $updatedRole = $this->roleBuilderService->updateRole($role, $request->payload());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Izin untuk peran \"{$updatedRole->label}\" berhasil diperbarui.",
        ]);

        return to_route('settings.roles');
    }

    public function destroy(Role $role): RedirectResponse
    {
        $label = $role->label;
        $this->roleBuilderService->deleteRole($role);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Peran \"{$label}\" berhasil dihapus.",
        ]);

        return to_route('settings.roles');
    }
}
