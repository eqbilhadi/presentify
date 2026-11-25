<?php

namespace App\Services\Auth;

use App\Models\Domain;
use App\Models\SsoToken;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Stancl\Tenancy\Middleware\ScopeSessions;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class SsoService
{
    private const TTL_SECONDS = 60;

    private const TOKEN_BYTES = 32;

    public function issueToken(User $user, Tenant $tenant): string
    {
        $hasAccess = $tenant->is_active && TenantUser::query()
            ->where('tenant_id', $tenant->getTenantKey())
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->exists();

        if (! $hasAccess) {
            throw new AccessDeniedHttpException('Pengguna tidak memiliki akses aktif ke instansi ini.');
        }

        $plainToken = bin2hex(random_bytes(self::TOKEN_BYTES));

        SsoToken::create([
            'token_hash' => hash('sha256', $plainToken),
            'user_id' => $user->id,
            'target_tenant_id' => $tenant->getTenantKey(),
            'expires_at' => now()->addSeconds(self::TTL_SECONDS),
        ]);

        return $plainToken;
    }

    public function createCallbackUrl(User $user, Tenant $tenant, ?Request $request = null): string
    {
        $plainToken = $this->issueToken($user, $tenant);

        return $this->buildCallbackUrl($tenant, $plainToken, $request);
    }

    public function buildCallbackUrl(Tenant $tenant, string $plainToken, ?Request $request = null): string
    {
        $tenantHost = $this->resolveTenantHost($tenant, $request);
        [$scheme, $portSuffix] = $this->resolveSchemeAndPort($request);

        return "{$scheme}://{$tenantHost}{$portSuffix}/sso/callback?token=".urlencode($plainToken);
    }

    public function validateAndLogin(string $plainToken, Tenant $tenant, ?Request $request = null): User
    {
        $centralConnection = config('tenancy.database.central_connection');
        $errorMessage = null;

        $user = DB::connection($centralConnection)->transaction(function () use ($plainToken, $tenant, &$errorMessage): ?User {
            $token = SsoToken::query()
                ->where('token_hash', hash('sha256', $plainToken))
                ->lockForUpdate()
                ->first();

            if (! $token) {
                $errorMessage = 'Token SSO tidak valid atau sudah kedaluwarsa.';

                return null;
            }

            if (! $token->isValid() || (string) $token->target_tenant_id !== (string) $tenant->getTenantKey() || ! $tenant->is_active) {
                // Persist deletion before throwing so expired or cross-tenant tokens cannot be retried.
                $token->delete();
                $errorMessage = 'Token SSO tidak valid atau sudah kedaluwarsa.';

                return null;
            }

            $user = $token->user;
            $membership = TenantUser::query()
                ->where('tenant_id', $tenant->getTenantKey())
                ->where('user_id', $token->user_id)
                ->where('is_active', true)
                ->first();

            // Delete immediately after lookup so the one-time token cannot be replayed.
            $token->delete();

            if (! $user || ! $membership) {
                $errorMessage = 'Pengguna tidak memiliki akses aktif ke instansi ini.';

                return null;
            }

            $membership->update(['last_accessed_at' => now()]);

            return $user;
        });

        if (! $user instanceof User) {
            throw new AccessDeniedHttpException($errorMessage ?? 'Token SSO tidak valid atau sudah kedaluwarsa.');
        }

        Auth::guard('web')->login($user);

        if ($request?->hasSession()) {
            $request->session()->regenerate();
            $request->session()->put(ScopeSessions::$tenantIdKey, $tenant->getTenantKey());
        }

        return $user;
    }

    public function resolvePostLoginResponse(Request $request, User $user): SymfonyResponse
    {
        $activeTenants = $user->activeTenants()
            ->where('sys_tenants.is_active', true)
            ->with('domains')
            ->get();

        if ($activeTenants->count() === 1) {
            $tenant = $activeTenants->first();

            if ($tenant instanceof Tenant) {
                return Inertia::location($this->createCallbackUrl($user, $tenant, $request));
            }
        }

        if ($activeTenants->count() > 1 && Route::has('workspace.picker')) {
            return redirect()->route('workspace.picker');
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    private function resolveTenantHost(Tenant $tenant, ?Request $request): string
    {
        $firstDomain = $tenant->domains->first();
        $domain = $firstDomain instanceof Domain
            ? $firstDomain->domain
            : $tenant->domains()->value('domain');
        $identifier = is_string($domain) && $domain !== '' ? $domain : $tenant->slug;

        if (str_contains($identifier, '.')) {
            return $identifier;
        }

        return "{$identifier}.{$this->resolveCentralRootDomain($request)}";
    }

    private function resolveCentralRootDomain(?Request $request): string
    {
        /** @var array<int, string> $centralDomains */
        $centralDomains = config('tenancy.central_domains', ['localhost']);
        $host = $request?->getHost();

        if (! $host) {
            $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
            $host = is_string($appHost) ? $appHost : 'localhost';
        }

        if (str_starts_with($host, 'app.')) {
            $stripped = Str::after($host, 'app.');

            if (in_array($stripped, $centralDomains, true)) {
                return $stripped;
            }
        }

        if (filter_var($host, FILTER_VALIDATE_IP) === false && in_array($host, $centralDomains, true)) {
            return $host;
        }

        foreach ($centralDomains as $candidate) {
            if (filter_var($candidate, FILTER_VALIDATE_IP) === false) {
                return $candidate;
            }
        }

        return 'localhost';
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveSchemeAndPort(?Request $request): array
    {
        $appUrl = (string) config('app.url');
        $parsedScheme = parse_url($appUrl, PHP_URL_SCHEME);
        $parsedPort = parse_url($appUrl, PHP_URL_PORT);

        $scheme = $request?->getScheme() ?: (is_string($parsedScheme) ? $parsedScheme : 'http');
        $port = $request?->getPort() ?? (is_int($parsedPort) ? $parsedPort : null);

        $isStandardPort = ($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443);
        $portSuffix = ($port !== null && ! $isStandardPort) ? ":{$port}" : '';

        return [$scheme, $portSuffix];
    }
}
