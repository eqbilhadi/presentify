<?php

namespace App\Http\Controllers\Central\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Auth\SwitchTenantRequest;
use App\Models\User;
use App\Services\Auth\WorkspaceService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class WorkspaceController extends Controller
{
    public function __construct(private readonly WorkspaceService $workspaceService) {}

    public function index(Request $request): InertiaResponse
    {
        /** @var User $user */
        $user = $request->user();

        $tenants = $this->workspaceService->getActiveTenants($user);

        return Inertia::render('auth/tenant-picker', [
            'tenants' => $tenants->map(fn ($tenant) => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'terminology' => $tenant->terminology,
                'theme_config' => $tenant->theme_config,
            ])->values(),
        ]);
    }

    public function switch(SwitchTenantRequest $request): SymfonyResponse
    {
        /** @var User $user */
        $user = $request->user();

        return $this->workspaceService->switchTenant($user, $request->string('tenant_id')->toString(), $request);
    }

    public function logout(Request $request): SymfonyResponse
    {
        return $this->workspaceService->singleLogout($request);
    }
}
