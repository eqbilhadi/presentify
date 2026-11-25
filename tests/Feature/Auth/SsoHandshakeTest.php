<?php

use App\Models\SsoToken;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\Auth\OtpService;
use App\Services\Auth\SsoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Stancl\Tenancy\Middleware\ScopeSessions;

uses(RefreshDatabase::class);

/** @var array<int, Tenant> */
$createdTenants = [];

afterEach(function () use (&$createdTenants) {
    if (tenancy()->initialized) {
        tenancy()->end();
    }

    DB::purge('tenant');

    foreach ($createdTenants as $tenant) {
        $tenant->delete();
    }

    $createdTenants = [];
});

function expectedCallbackPrefix(string $slug): string
{
    $host = parse_url((string) config('app.url'), PHP_URL_HOST);
    $rootHost = is_string($host) && $host !== '' ? preg_replace('/^app\./', '', $host) : 'localhost';
    $port = parse_url((string) config('app.url'), PHP_URL_PORT);
    $portSuffix = is_int($port) ? ":{$port}" : '';

    return "http://{$slug}.{$rootHost}{$portSuffix}/sso/callback?token=";
}

function makeTenantWithSubdomain(string $slug, bool $isActive = true): Tenant
{
    $tenant = Tenant::create([
        'name' => strtoupper($slug).' Instansi',
        'slug' => $slug,
        'is_active' => $isActive,
    ]);

    $tenant->domains()->create(['domain' => $slug]);

    return $tenant;
}

it('keeps users with zero active tenants on central dashboard after password login', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($user);
    $response->assertRedirect(route('dashboard', absolute: false));
    $this->assertDatabaseCount('auth_sso_tokens', 0);
});

it('redirects users with exactly one active tenant to subdomain sso callback on password login', function () use (&$createdTenants) {
    $user = User::factory()->create();
    $tenant = makeTenantWithSubdomain('disdukcapil');
    $createdTenants[] = $tenant;

    TenantUser::create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'is_active' => true,
    ]);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($user);
    $this->assertDatabaseCount('auth_sso_tokens', 1);

    $location = $response->headers->get('Location');
    expect($location)->toStartWith(expectedCallbackPrefix('disdukcapil'));

    $tokenRecord = SsoToken::first();
    parse_str((string) parse_url((string) $location, PHP_URL_QUERY), $query);

    expect($tokenRecord)->not->toBeNull()
        ->and($tokenRecord->token_hash)->toBe(hash('sha256', (string) ($query['token'] ?? '')))
        ->and((string) $tokenRecord->target_tenant_id)->toBe((string) $tenant->id)
        ->and($tokenRecord->expires_at->isFuture())->toBeTrue();
});

it('returns inertia 409 location header when logging in via inertia with one active tenant', function () use (&$createdTenants) {
    $user = User::factory()->create();
    $tenant = makeTenantWithSubdomain('disdukcapil');
    $createdTenants[] = $tenant;

    TenantUser::create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'is_active' => true,
    ]);

    $response = $this->withHeaders(['X-Inertia' => 'true'])->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertStatus(409);
    expect($response->headers->get('X-Inertia-Location'))
        ->toStartWith(expectedCallbackPrefix('disdukcapil'));
});

it('redirects users with one active tenant to subdomain sso callback after otp login', function () use (&$createdTenants) {
    $user = User::factory()->create();
    $tenant = makeTenantWithSubdomain('disdukcapil');
    $createdTenants[] = $tenant;

    TenantUser::create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'is_active' => true,
    ]);

    $otp = app(OtpService::class)->generate($user);

    $response = $this->withSession([
        'otp_email' => $user->email,
        'otp_user_id' => $user->id,
    ])->post(route('auth.otp.store'), [
        'code' => $otp->plain_code,
    ]);

    $this->assertAuthenticatedAs($user);
    expect($response->headers->get('Location'))
        ->toStartWith(expectedCallbackPrefix('disdukcapil'));
});

it('does not redirect to inactive tenant or inactive membership', function () use (&$createdTenants) {
    $user = User::factory()->create();
    $inactiveTenant = makeTenantWithSubdomain('nonaktif', isActive: false);
    $createdTenants[] = $inactiveTenant;

    TenantUser::create([
        'tenant_id' => $inactiveTenant->id,
        'user_id' => $user->id,
        'is_active' => true,
    ]);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('dashboard', absolute: false));
    $this->assertDatabaseCount('auth_sso_tokens', 0);
});

it('redirects multi-tenant users to workspace picker after login', function () use (&$createdTenants) {
    $user = User::factory()->create();
    $tenantA = makeTenantWithSubdomain('disdukcapil');
    $tenantB = makeTenantWithSubdomain('sman1contoh');
    $createdTenants[] = $tenantA;
    $createdTenants[] = $tenantB;

    TenantUser::insert([
        [
            'tenant_id' => $tenantA->id,
            'user_id' => $user->id,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'tenant_id' => $tenantB->id,
            'user_id' => $user->id,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('workspace.picker'));
    $this->assertDatabaseCount('auth_sso_tokens', 0);
});

it('completes sso handshake on tenant subdomain, consumes token, and logs user into tenant dashboard', function () use (&$createdTenants) {
    $user = User::factory()->create();
    $tenant = makeTenantWithSubdomain('disdukcapil');
    $createdTenants[] = $tenant;

    $membership = TenantUser::create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'is_active' => true,
    ]);

    $plainToken = app(SsoService::class)->issueToken($user, $tenant);

    $callbackResponse = $this->get("http://disdukcapil.localhost/sso/callback?token={$plainToken}");

    $callbackResponse->assertRedirect('/dashboard');
    $this->assertAuthenticatedAs($user);
    expect(SsoToken::count())->toBe(0);
    expect(session(ScopeSessions::$tenantIdKey))->toBe($tenant->id);
    expect($membership->fresh()->last_accessed_at)->not->toBeNull();

    $dashboardResponse = $this->withoutVite()->get('http://disdukcapil.localhost/dashboard');
    $dashboardResponse->assertOk();
    expect(tenancy()->initialized)->toBeTrue()
        ->and(tenant()?->slug)->toBe('disdukcapil');

    // Replay with the same token on a clean guest session must fail.
    $this->post(route('logout'));
    $replayResponse = $this->get("http://disdukcapil.localhost/sso/callback?token={$plainToken}");
    $replayResponse->assertForbidden();
    $this->assertGuest();
});

it('rejects expired sso tokens and removes them', function () use (&$createdTenants) {
    $user = User::factory()->create();
    $tenant = makeTenantWithSubdomain('disdukcapil');
    $createdTenants[] = $tenant;

    TenantUser::create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'is_active' => true,
    ]);

    $plainToken = bin2hex(random_bytes(32));
    SsoToken::create([
        'token_hash' => hash('sha256', $plainToken),
        'user_id' => $user->id,
        'target_tenant_id' => $tenant->id,
        'expires_at' => now()->subSecond(),
    ]);

    $response = $this->get("http://disdukcapil.localhost/sso/callback?token={$plainToken}");

    $response->assertForbidden();
    $this->assertGuest();
    expect(SsoToken::count())->toBe(0);
});

it('rejects sso token issued for a different tenant subdomain', function () use (&$createdTenants) {
    $user = User::factory()->create();
    $tenantA = makeTenantWithSubdomain('disdukcapil');
    $tenantB = makeTenantWithSubdomain('sman1contoh');
    $createdTenants[] = $tenantA;
    $createdTenants[] = $tenantB;

    TenantUser::create([
        'tenant_id' => $tenantB->id,
        'user_id' => $user->id,
        'is_active' => true,
    ]);

    $tokenForB = app(SsoService::class)->issueToken($user, $tenantB);

    $response = $this->get("http://disdukcapil.localhost/sso/callback?token={$tokenForB}");

    $response->assertForbidden();
    $this->assertGuest();
    expect(SsoToken::count())->toBe(0);
});

it('rejects handshake if user membership was deactivated after token issuance', function () use (&$createdTenants) {
    $user = User::factory()->create();
    $tenant = makeTenantWithSubdomain('disdukcapil');
    $createdTenants[] = $tenant;

    $membership = TenantUser::create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'is_active' => true,
    ]);

    $plainToken = app(SsoService::class)->issueToken($user, $tenant);
    $membership->update(['is_active' => false]);

    $response = $this->get("http://disdukcapil.localhost/sso/callback?token={$plainToken}");

    $response->assertForbidden();
    $this->assertGuest();
});

it('blocks accessing tenant sso callback from central domains', function () {
    $token = bin2hex(random_bytes(32));

    $response = $this->get("http://localhost/sso/callback?token={$token}");

    $response->assertNotFound();
});

it('validates sso callback token presence and length', function () use (&$createdTenants) {
    $tenant = makeTenantWithSubdomain('disdukcapil');
    $createdTenants[] = $tenant;

    $missingTokenResponse = $this->get('http://disdukcapil.localhost/sso/callback');
    $missingTokenResponse->assertSessionHasErrors('token');

    $shortTokenResponse = $this->get('http://disdukcapil.localhost/sso/callback?token=short');
    $shortTokenResponse->assertSessionHasErrors('token');
});

it('builds callback url cleanly when accessed from app central subdomain', function () use (&$createdTenants) {
    $user = User::factory()->create();
    $tenant = makeTenantWithSubdomain('disdukcapil');
    $createdTenants[] = $tenant;

    TenantUser::create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'is_active' => true,
    ]);

    $request = Request::create('https://app.presentify.test:8443/login', 'POST');
    $url = app(SsoService::class)->createCallbackUrl($user, $tenant, $request);

    expect($url)->toStartWith('https://disdukcapil.presentify.test:8443/sso/callback?token=');
});

it('blocks accessing central routes from tenant subdomains', function () use (&$createdTenants) {
    $tenant = makeTenantWithSubdomain('disdukcapil');
    $createdTenants[] = $tenant;

    $this->get('http://disdukcapil.localhost/')->assertNotFound();
    $this->get('http://disdukcapil.localhost/login')->assertNotFound();
    $this->get('http://disdukcapil.localhost/register')->assertNotFound();
    $this->get('http://disdukcapil.localhost/auth/otp')->assertNotFound();
});

it('redirects tenant subdomain logout to signed central slo callback and clears central session', function () use (&$createdTenants) {
    $user = User::factory()->create();
    $tenant = makeTenantWithSubdomain('disdukcapil');
    $createdTenants[] = $tenant;

    $response = $this->actingAs($user)
        ->withHeaders(['X-Inertia' => 'true'])
        ->post('http://disdukcapil.presentify.test/logout');

    $response->assertStatus(409);
    $location = (string) $response->headers->get('X-Inertia-Location');
    expect($location)->toStartWith('http://presentify.test/sso/logout?');
    $this->assertGuest();

    // Simulate central session still present when browser follows the signed SLO callback.
    $this->flushHeaders()
        ->actingAs($user)
        ->get($location)
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

it('rejects unsigned central slo callback requests', function () {
    $this->get('http://presentify.test/sso/logout')->assertForbidden();
});
