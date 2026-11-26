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
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Stancl\Tenancy\Middleware\ScopeSessions;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class SsoService
{
    private const TTL_SECONDS = 60;

    private const TOKEN_BYTES = 32;

    private const STATE_LENGTH = 40;

    private const STATE_SESSION_KEY = 'sso_state';

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

    public function createCallbackUrl(
        User $user,
        Tenant $tenant,
        ?Request $request = null,
        ?string $state = null,
    ): string {
        $plainToken = $this->issueToken($user, $tenant);

        return $this->buildCallbackUrl($tenant, $plainToken, $request, $state);
    }

    public function buildCallbackUrl(
        Tenant $tenant,
        string $plainToken,
        ?Request $request = null,
        ?string $state = null,
    ): string {
        $tenantHost = $this->resolveTenantHost($tenant, $request);
        [$scheme, $portSuffix] = $this->resolveSchemeAndPort($request);

        $query = ['token' => $plainToken];

        if (is_string($state) && $state !== '') {
            $query['state'] = $state;
        }

        return "{$scheme}://{$tenantHost}{$portSuffix}/sso/callback?".http_build_query($query);
    }

    public function initiateSpLogin(Request $request): SymfonyResponse
    {
        $state = Str::random(self::STATE_LENGTH);
        $request->session()->put(self::STATE_SESSION_KEY, $state);

        $authorizeUrl = $this->buildCentralUrl(
            $request,
            '/auth/sso/authorize',
            [
                'domain' => $request->getHost(),
                'state' => $state,
            ],
        );

        return Inertia::location($authorizeUrl);
    }

    public function authorizeSpInitiated(User $user, string $domain, string $state, Request $request): SymfonyResponse
    {
        $tenant = $this->resolveActiveTenantByDomain($domain);

        if (! $tenant instanceof Tenant) {
            throw new AccessDeniedHttpException('Instansi tujuan tidak ditemukan atau tidak aktif.');
        }

        $callbackUrl = $this->createCallbackUrl($user, $tenant, $request, $state);

        return Inertia::location($callbackUrl);
    }

    public function validateAndLogin(
        string $plainToken,
        Tenant $tenant,
        ?Request $request = null,
        ?string $state = null,
    ): User {
        $this->assertValidCallbackState($request, $state);

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
        // Preserve SP-initiated SSO authorize flow (including its anti-CSRF state) when interrupted by login.
        $intendedUrl = $request->session()->get('url.intended');

        if (is_string($intendedUrl) && parse_url($intendedUrl, PHP_URL_PATH) === '/auth/sso/authorize') {
            return redirect()->intended(route('dashboard', absolute: false));
        }

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

    public function logoutTenantAndRedirectToCentralSlo(Request $request): SymfonyResponse
    {
        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return Inertia::location($this->createCentralSloUrl($request));
    }

    public function createCentralSloUrl(Request $request): string
    {
        $relativeSignedPath = URL::temporarySignedRoute(
            'central.sso.logout',
            now()->addMinute(),
            [],
            false,
        );

        $centralHost = $this->resolveCentralRootDomain($request);
        [$scheme, $portSuffix] = $this->resolveSchemeAndPort($request);

        return "{$scheme}://{$centralHost}{$portSuffix}{$relativeSignedPath}";
    }

    private function assertValidCallbackState(?Request $request, ?string $state): void
    {
        $expectedState = $request?->hasSession()
            ? $request->session()->pull(self::STATE_SESSION_KEY)
            : null;

        if (is_string($expectedState) && $expectedState !== '') {
            if (! is_string($state) || ! hash_equals($expectedState, $state)) {
                throw new AccessDeniedHttpException('Parameter state SSO tidak valid.');
            }

            return;
        }

        if (is_string($state) && $state !== '') {
            throw new AccessDeniedHttpException('Sesi state SSO tidak ditemukan.');
        }
    }

    private function resolveActiveTenantByDomain(string $domain): ?Tenant
    {
        $normalized = strtolower(trim($domain));

        if ($normalized === '') {
            return null;
        }

        $domainRecord = Domain::query()
            ->where('domain', $normalized)
            ->with('tenant')
            ->first();

        if ($domainRecord?->tenant instanceof Tenant && $domainRecord->tenant->is_active) {
            return $domainRecord->tenant;
        }

        /** @var array<int, string> $centralDomains */
        $centralDomains = config('tenancy.central_domains', []);

        foreach ($centralDomains as $centralDomain) {
            if (filter_var($centralDomain, FILTER_VALIDATE_IP) !== false) {
                continue;
            }

            if (str_ends_with($normalized, ".{$centralDomain}")) {
                $subdomain = Str::beforeLast($normalized, ".{$centralDomain}");

                if ($subdomain !== '' && ! str_contains($subdomain, '.')) {
                    $subdomainRecord = Domain::query()
                        ->where('domain', $subdomain)
                        ->with('tenant')
                        ->first();

                    if ($subdomainRecord?->tenant instanceof Tenant && $subdomainRecord->tenant->is_active) {
                        return $subdomainRecord->tenant;
                    }
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, string>  $query
     */
    private function buildCentralUrl(Request $request, string $path, array $query = []): string
    {
        $centralHost = $this->resolveCentralRootDomain($request);
        [$scheme, $portSuffix] = $this->resolveSchemeAndPort($request);
        $queryString = $query !== [] ? '?'.http_build_query($query) : '';

        return "{$scheme}://{$centralHost}{$portSuffix}{$path}{$queryString}";
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
            if (filter_var($candidate, FILTER_VALIDATE_IP) === false && str_ends_with($host, ".{$candidate}")) {
                return $candidate;
            }
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
