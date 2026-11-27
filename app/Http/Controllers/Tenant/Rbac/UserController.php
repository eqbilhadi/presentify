<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Rbac;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Rbac\StoreTenantUserRequest;
use App\Http\Requests\Tenant\Rbac\UpdateTenantUserRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Rbac\TenantUserManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function __construct(
        private readonly TenantUserManagementService $tenantUserManagementService,
    ) {}

    public function index(): Response
    {
        $tenant = $this->currentTenant();

        return Inertia::render('tenant/rbac/user/index', [
            'users' => $this->tenantUserManagementService->listUsers($tenant),
            'roleOptions' => $this->tenantUserManagementService->getRoleOptions(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('tenant/rbac/user/form', [
            'user' => null,
            'roleOptions' => $this->tenantUserManagementService->getRoleOptions(),
        ]);
    }

    public function store(StoreTenantUserRequest $request): RedirectResponse
    {
        $tenant = $this->currentTenant();

        /** @var array{name?: string|null, email: string, password?: string|null, nip?: string|null, position?: string|null, is_active?: bool, role_ids: array<int, string>} $payload */
        $payload = $request->validated();
        $result = $this->tenantUserManagementService->createOrAttach($tenant, $payload);

        $message = $result['attached_existing']
            ? "Akun \"{$result['user']->name}\" berhasil ditautkan ke instansi ini."
            : "Pengguna \"{$result['user']->name}\" berhasil ditambahkan ke instansi ini.";

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $message,
        ]);

        return to_route('tenant.rbac.users.index');
    }

    public function edit(User $user): Response
    {
        $tenant = $this->currentTenant();

        return Inertia::render('tenant/rbac/user/form', [
            'user' => $this->tenantUserManagementService->getUserDetail($tenant, $user),
            'roleOptions' => $this->tenantUserManagementService->getRoleOptions(),
        ]);
    }

    public function update(UpdateTenantUserRequest $request, User $user): RedirectResponse
    {
        $tenant = $this->currentTenant();

        /** @var User $actor */
        $actor = $request->user();

        /** @var array{nip?: string|null, position?: string|null, is_active: bool, role_ids: array<int, string>} $payload */
        $payload = $request->validated();
        $updated = $this->tenantUserManagementService->update($tenant, $user, $payload, $actor);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Akses pengguna \"{$updated->name}\" berhasil diperbarui.",
        ]);

        return to_route('tenant.rbac.users.index');
    }

    public function toggleStatus(Request $request, User $user): RedirectResponse
    {
        $tenant = $this->currentTenant();

        /** @var User $actor */
        $actor = $request->user();
        $isActive = $this->tenantUserManagementService->toggleStatus($tenant, $user, $actor);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $isActive
                ? "Keanggotaan \"{$user->name}\" diaktifkan."
                : "Keanggotaan \"{$user->name}\" dinonaktifkan.",
        ]);

        return back();
    }

    private function currentTenant(): Tenant
    {
        $tenant = tenancy()->tenant;
        abort_unless($tenant instanceof Tenant, 404);

        return $tenant;
    }
}
