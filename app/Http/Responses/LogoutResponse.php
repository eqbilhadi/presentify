<?php

namespace App\Http\Responses;

use App\Services\Auth\SsoService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;
use Symfony\Component\HttpFoundation\Response;

class LogoutResponse implements LogoutResponseContract
{
    public function __construct(private readonly SsoService $ssoService) {}

    public function toResponse($request): Response
    {
        if (! $request->hasHeader('X-Inertia') && $request->wantsJson()) {
            return response()->json([], 204);
        }

        if ($this->isTenantDomain($request)) {
            return Inertia::location($this->ssoService->createCentralSloUrl($request));
        }

        return redirect('/');
    }

    private function isTenantDomain(Request $request): bool
    {
        /** @var array<int, string> $centralDomains */
        $centralDomains = config('tenancy.central_domains', []);

        return ! in_array($request->getHost(), $centralDomains, true);
    }
}
