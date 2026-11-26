<?php

declare(strict_types=1);

namespace App\Http\Controllers\Universal\Rbac;

use App\Http\Controllers\Controller;
use App\Http\Requests\Universal\Rbac\RoleRequest;
use App\Models\Role;
use App\Services\Rbac\RoleManagementService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    public function __construct(private readonly RoleManagementService $roleManagementService) {}

    public function index(): Response
    {
        return Inertia::render('universal/rbac/role/index', [
            'roles' => $this->roleManagementService->listRoles(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('universal/rbac/role/form', [
            'role' => null,
            'matrix' => $this->roleManagementService->getMatrixData(),
        ]);
    }

    public function store(RoleRequest $request): RedirectResponse
    {
        /** @var array{name: string, menu_ids?: array<int, string>|null, menu_action_ids?: array<int, string>|null, permission_ids?: array<int, string>|null} $payload */
        $payload = $request->validated();
        $role = $this->roleManagementService->create($payload);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Peran \"{$role->name}\" berhasil dibuat.",
        ]);

        return to_route('rbac.roles.index');
    }

    public function edit(Role $role): Response
    {
        return Inertia::render('universal/rbac/role/form', [
            'role' => $this->roleManagementService->getRoleDetail($role),
            'matrix' => $this->roleManagementService->getMatrixData(),
        ]);
    }

    public function update(RoleRequest $request, Role $role): RedirectResponse
    {
        /** @var array{name: string, menu_ids?: array<int, string>|null, menu_action_ids?: array<int, string>|null, permission_ids?: array<int, string>|null} $payload */
        $payload = $request->validated();
        $updated = $this->roleManagementService->update($role, $payload);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Peran \"{$updated->name}\" berhasil diperbarui.",
        ]);

        return to_route('rbac.roles.index');
    }

    public function destroy(Role $role): RedirectResponse
    {
        $name = $role->name;
        $this->roleManagementService->delete($role);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Peran \"{$name}\" berhasil dihapus.",
        ]);

        return to_route('rbac.roles.index');
    }
}
