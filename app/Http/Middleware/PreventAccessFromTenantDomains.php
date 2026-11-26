<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreventAccessFromTenantDomains
{
    public function handle(Request $request, Closure $next): Response
    {
        if (in_array($request->getHost(), config('tenancy.central_domains', []), true)) {
            return $next($request);
        }

        if (tenancy()->initialized && $request->routeIs(
            'logout',
            'password.confirm',
            'password.confirm.store',
            'password.confirmation',
            'two-factor.enable',
            'two-factor.disable',
            'two-factor.confirm',
            'two-factor.qr-code',
            'two-factor.secret-key',
            'two-factor.recovery-codes',
            'two-factor.regenerate-recovery-codes',
        )) {
            return $next($request);
        }

        abort(404);
    }
}
