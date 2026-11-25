<?php

namespace App\Http\Responses;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;
use Symfony\Component\HttpFoundation\Response;

class LogoutResponse implements LogoutResponseContract
{
    public function toResponse($request): Response
    {
        if (! $request->hasHeader('X-Inertia') && $request->wantsJson()) {
            return response()->json([], 204);
        }

        if ($this->isTenantDomain($request)) {
            return Inertia::location($this->resolveCentralLogoutUrl($request));
        }

        return redirect('/');
    }

    private function isTenantDomain(Request $request): bool
    {
        /** @var array<int, string> $centralDomains */
        $centralDomains = config('tenancy.central_domains', []);

        return ! in_array($request->getHost(), $centralDomains, true);
    }

    private function resolveCentralLogoutUrl(Request $request): string
    {
        $relativeSignedPath = URL::temporarySignedRoute(
            'logout.slo.callback',
            now()->addMinutes(2),
            [],
            false,
        );

        /** @var array<int, string> $centralDomains */
        $centralDomains = config('tenancy.central_domains', []);
        $host = $request->getHost();

        $centralHost = null;

        foreach ($centralDomains as $candidate) {
            if (filter_var($candidate, FILTER_VALIDATE_IP) === false && str_ends_with($host, ".{$candidate}")) {
                $centralHost = $candidate;
                break;
            }
        }

        if ($centralHost === null) {
            return rtrim((string) config('app.url'), '/').$relativeSignedPath;
        }

        $scheme = $request->getScheme();
        $port = $request->getPort();
        $isStandardPort = ($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443);
        $portSuffix = ($port !== null && ! $isStandardPort) ? ":{$port}" : '';

        return "{$scheme}://{$centralHost}{$portSuffix}{$relativeSignedPath}";
    }
}
