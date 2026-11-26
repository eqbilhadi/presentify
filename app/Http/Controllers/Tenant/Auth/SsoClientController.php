<?php

namespace App\Http\Controllers\Tenant\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Auth\SwitchTenantRequest;
use App\Models\User;
use App\Services\Auth\SsoService;
use App\Services\Auth\WorkspaceService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class SsoClientController extends Controller
{
    public function __construct(
        private readonly SsoService $ssoService,
        private readonly WorkspaceService $workspaceService,
    ) {}

    public function login(Request $request): SymfonyResponse
    {
        return $this->ssoService->initiateSpLogin($request);
    }

    public function switch(SwitchTenantRequest $request): SymfonyResponse
    {
        /** @var User $user */
        $user = $request->user();

        return $this->workspaceService->switchTenant($user, $request->string('tenant_id')->toString(), $request);
    }

    public function logout(Request $request): SymfonyResponse
    {
        return $this->ssoService->logoutTenantAndRedirectToCentralSlo($request);
    }
}
