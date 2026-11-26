<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Stancl\Tenancy\Contracts\TenantCouldNotBeIdentifiedException;
use Stancl\Tenancy\Exceptions\NotASubdomainException;
use Stancl\Tenancy\Middleware\InitializeTenancyBySubdomain;
use Stancl\Tenancy\Middleware\ScopeSessions;
use Symfony\Component\HttpFoundation\Response;

class InitializeTenancyBySubdomainIfApplicable
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->isCentralOrIpRequest($request->getHost())) {
            if (tenancy()->initialized) {
                tenancy()->end();
            }

            return $next($request);
        }

        $afterInit = function (Request $tenantRequest) use ($next): Response {
            if ($tenantRequest->hasSession() && ! $tenantRequest->routeIs('tenant.sso.callback')) {
                return app(ScopeSessions::class)->handle($tenantRequest, $next);
            }

            return $next($tenantRequest);
        };

        $route = $request->route();
        $alreadyScopedByTenantMiddleware = $route instanceof Route
            && in_array(InitializeTenancyBySubdomain::class, $route->gatherMiddleware(), true);

        if ($alreadyScopedByTenantMiddleware && tenancy()->initialized) {
            return $afterInit($request);
        }

        try {
            return app(InitializeTenancyBySubdomain::class)->handle($request, $afterInit);
        } catch (TenantCouldNotBeIdentifiedException|NotASubdomainException) {
            if (tenancy()->initialized) {
                tenancy()->end();
            }

            // Defer unknown-domain rejection to EnsureKnownDomain / PreventAccessFromTenantDomains.
            return $next($request);
        }
    }

    private function isCentralOrIpRequest(string $hostname): bool
    {
        if (in_array($hostname, config('tenancy.central_domains', []), true)) {
            return true;
        }

        return ! str_contains($hostname, '.') || filter_var($hostname, FILTER_VALIDATE_IP) !== false;
    }
}
