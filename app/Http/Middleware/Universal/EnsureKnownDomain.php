<?php

declare(strict_types=1);

namespace App\Http\Middleware\Universal;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureKnownDomain
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var array<int, string> $centralDomains */
        $centralDomains = config('tenancy.central_domains', []);

        if (in_array($request->getHost(), $centralDomains, true) || tenancy()->initialized) {
            return $next($request);
        }

        abort(404);
    }
}
