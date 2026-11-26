<?php

namespace App\Services\Auth;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class WorkspaceService
{
    public function __construct(private readonly SsoService $ssoService) {}

    /**
     * @return Collection<int, Tenant>
     */
    public function getActiveTenants(User $user): Collection
    {
        /** @var Collection<int, Tenant> */
        return $user->activeTenants()
            ->where('sys_tenants.is_active', true)
            ->with('domains')
            ->orderBy('sys_tenants.name')
            ->get();
    }

    public function switchTenant(User $user, string $tenantId, Request $request): SymfonyResponse
    {
        /** @var Tenant $tenant */
        $tenant = Tenant::query()->where('id', $tenantId)->firstOrFail();

        $callbackUrl = $this->ssoService->createCallbackUrl($user, $tenant, $request);

        return Inertia::location($callbackUrl);
    }

    public function singleLogout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return redirect()->route('login');
    }
}
