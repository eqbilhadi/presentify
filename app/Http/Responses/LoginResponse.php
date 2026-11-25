<?php

namespace App\Http\Responses;

use App\Models\User;
use App\Services\Auth\SsoService;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

class LoginResponse implements LoginResponseContract, TwoFactorLoginResponseContract
{
    public function __construct(private readonly SsoService $ssoService) {}

    public function toResponse($request): Response
    {
        if (! $request->hasHeader('X-Inertia') && $request->wantsJson()) {
            return response()->json(['two_factor' => false]);
        }

        $user = $request->user();

        if (! $user instanceof User) {
            return redirect()->intended(route('dashboard', absolute: false));
        }

        return $this->ssoService->resolvePostLoginResponse($request, $user);
    }
}
