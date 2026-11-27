<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central\Rbac;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Rbac\StoreCentralUserRequest;
use App\Http\Requests\Central\Rbac\UpdateCentralUserRequest;
use App\Models\User;
use App\Services\Rbac\CentralUserManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function __construct(
        private readonly CentralUserManagementService $centralUserManagementService,
    ) {}

    public function index(): Response
    {
        return Inertia::render('central/rbac/user/index', [
            'users' => $this->centralUserManagementService->listUsers(),
            'roleOptions' => $this->centralUserManagementService->getRoleOptions(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('central/rbac/user/form', [
            'user' => null,
            'roleOptions' => $this->centralUserManagementService->getRoleOptions(),
        ]);
    }

    public function store(StoreCentralUserRequest $request): RedirectResponse
    {
        /** @var array{name: string, email: string, password: string, role_ids: array<int, string>} $payload */
        $payload = $request->validated();
        $created = $this->centralUserManagementService->create($payload);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Pengguna \"{$created->name}\" berhasil ditambahkan.",
        ]);

        return to_route('central.rbac.users.index');
    }

    public function edit(User $user): Response
    {
        return Inertia::render('central/rbac/user/form', [
            'user' => $this->centralUserManagementService->getUserDetail($user),
            'roleOptions' => $this->centralUserManagementService->getRoleOptions(),
        ]);
    }

    public function update(UpdateCentralUserRequest $request, User $user): RedirectResponse
    {
        /** @var array{name: string, email: string, password?: string|null, role_ids: array<int, string>} $payload */
        $payload = $request->validated();
        $updated = $this->centralUserManagementService->update($user, $payload);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Pengguna \"{$updated->name}\" berhasil diperbarui.",
        ]);

        return to_route('central.rbac.users.index');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $name = $user->name;

        $this->centralUserManagementService->delete($user, $actor);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Pengguna \"{$name}\" berhasil dihapus.",
        ]);

        return to_route('central.rbac.users.index');
    }
}
