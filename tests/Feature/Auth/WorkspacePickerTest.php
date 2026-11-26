<?php

namespace Tests\Feature\Auth;

use App\Models\SsoToken;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\Auth\SsoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Stancl\Tenancy\Middleware\ScopeSessions;
use Tests\TestCase;

class WorkspacePickerTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::create([
            'name' => 'Instansi A',
            'slug' => 'instansi-a',
            'is_active' => true,
        ]);
        $this->tenantA->domains()->create(['domain' => 'instansi-a']);

        $this->tenantB = Tenant::create([
            'name' => 'Instansi B',
            'slug' => 'instansi-b',
            'is_active' => true,
        ]);
        $this->tenantB->domains()->create(['domain' => 'instansi-b']);
    }

    protected function tearDown(): void
    {
        foreach ([$this->tenantA, $this->tenantB] as $tenant) {
            try {
                $tenant->database()->manager()->deleteDatabase($tenant);
            } catch (\Throwable) {
            }
        }

        if (DB::getDefaultConnection() !== config('tenancy.database.central_connection')) {
            tenancy()->end();
            DB::purge('tenant');
        }

        parent::tearDown();
    }

    private function createUserWithTenants(int $count = 2): User
    {
        $user = User::factory()->create();
        $tenants = [$this->tenantA, $this->tenantB];

        for ($i = 0; $i < $count; $i++) {
            TenantUser::create([
                'tenant_id' => $tenants[$i]->id,
                'user_id' => $user->id,
                'is_active' => true,
            ]);
        }

        return $user;
    }

    public function test_multi_tenant_user_sees_workspace_picker(): void
    {
        $user = $this->createUserWithTenants(2);

        $this->actingAs($user)
            ->get(route('workspace.picker'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('auth/tenant-picker')
                ->has('tenants', 2)
            );
    }

    public function test_picker_includes_tenant_name_and_slug(): void
    {
        $user = $this->createUserWithTenants(2);

        $this->actingAs($user)
            ->get(route('workspace.picker'))
            ->assertInertia(fn ($page) => $page
                ->component('auth/tenant-picker')
                ->where('tenants.0.name', 'Instansi A')
                ->where('tenants.0.slug', 'instansi-a')
            );
    }

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $this->get(route('workspace.picker'))
            ->assertRedirect(route('login'));
    }

    public function test_picker_is_inaccessible_from_tenant_subdomain(): void
    {
        $user = $this->createUserWithTenants(2);

        $this->actingAs($user)
            ->get('http://instansi-a.presentify.test/workspace')
            ->assertNotFound();
    }

    public function test_switch_tenant_issues_sso_redirect(): void
    {
        $user = $this->createUserWithTenants(2);

        $response = $this->actingAs($user)
            ->post(route('workspace.switch'), ['tenant_id' => $this->tenantA->id]);

        $response->assertRedirect();
        $this->assertStringContainsString('/sso/callback?token=', $response->headers->get('Location') ?? '');
    }

    public function test_switch_tenant_returns_inertia_location_for_inertia_requests(): void
    {
        $user = $this->createUserWithTenants(2);

        $response = $this->actingAs($user)
            ->withHeaders(['X-Inertia' => 'true'])
            ->post(route('workspace.switch'), ['tenant_id' => $this->tenantA->id]);

        $response->assertStatus(409);
        $this->assertStringContainsString('/sso/callback?token=', $response->headers->get('X-Inertia-Location') ?? '');
    }

    public function test_switch_tenant_rejects_unauthorized_tenant(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('workspace.switch'), ['tenant_id' => $this->tenantA->id])
            ->assertSessionHasErrors('tenant_id');
    }

    public function test_switch_tenant_rejects_inactive_membership(): void
    {
        $user = User::factory()->create();
        TenantUser::create([
            'tenant_id' => $this->tenantA->id,
            'user_id' => $user->id,
            'is_active' => false,
        ]);

        $this->actingAs($user)
            ->post(route('workspace.switch'), ['tenant_id' => $this->tenantA->id])
            ->assertSessionHasErrors('tenant_id');
    }

    public function test_single_logout_clears_session_and_redirects_to_login(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('logout.slo'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_single_logout_requires_authentication(): void
    {
        $this->post(route('logout.slo'))
            ->assertRedirect(route('login'));
    }

    public function test_switch_requires_authentication(): void
    {
        $this->post(route('workspace.switch'), ['tenant_id' => $this->tenantA->id])
            ->assertRedirect(route('login'));
    }

    public function test_inactive_tenant_is_excluded_from_picker(): void
    {
        $user = User::factory()->create();
        TenantUser::create([
            'tenant_id' => $this->tenantA->id,
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        $this->tenantA->update(['is_active' => false]);

        $this->actingAs($user)
            ->get(route('workspace.picker'))
            ->assertInertia(fn ($page) => $page
                ->component('auth/tenant-picker')
                ->has('tenants', 0)
            );
    }

    public function test_guest_visiting_tenant_subdomain_is_redirected_to_tenant_sso_login(): void
    {
        $this->get('http://instansi-a.presentify.test/dashboard')
            ->assertRedirect('http://instansi-a.presentify.test/sso/login');
    }

    public function test_tenant_sso_login_stores_state_and_redirects_to_central_authorize(): void
    {
        $response = $this->get('http://instansi-a.presentify.test/sso/login');

        $response->assertRedirect();
        $state = session('sso_state');
        $this->assertIsString($state);
        $this->assertSame(40, strlen($state));

        $location = (string) $response->headers->get('Location');
        $this->assertStringStartsWith('http://presentify.test/auth/sso/authorize?', $location);

        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $this->assertSame('instansi-a.presentify.test', $query['domain'] ?? null);
        $this->assertSame($state, $query['state'] ?? null);
    }

    public function test_central_sso_authorize_issues_token_and_preserves_state(): void
    {
        $user = $this->createUserWithTenants(1);
        $state = Str::random(40);

        $response = $this->actingAs($user)
            ->get('http://presentify.test/auth/sso/authorize?'.http_build_query([
                'domain' => 'instansi-a.presentify.test',
                'state' => $state,
            ]));

        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $this->assertStringStartsWith('http://instansi-a.presentify.test/sso/callback?', $location);

        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $this->assertSame($state, $query['state'] ?? null);
        $this->assertSame(64, strlen((string) ($query['token'] ?? '')));
    }

    public function test_central_sso_authorize_rejects_unauthorized_tenant_or_unknown_domain(): void
    {
        $user = User::factory()->create();
        $state = Str::random(40);

        $this->actingAs($user)
            ->get('http://presentify.test/auth/sso/authorize?'.http_build_query([
                'domain' => 'instansi-a.presentify.test',
                'state' => $state,
            ]))
            ->assertForbidden();

        $this->actingAs($user)
            ->get('http://presentify.test/auth/sso/authorize?'.http_build_query([
                'domain' => 'evil.presentify.test',
                'state' => $state,
            ]))
            ->assertForbidden();
    }

    public function test_sp_initiated_sso_preserves_intended_authorize_url_across_central_login(): void
    {
        $user = $this->createUserWithTenants(2);
        $state = Str::random(40);
        $authorizeUrl = 'http://presentify.test/auth/sso/authorize?'.http_build_query([
            'domain' => 'instansi-a.presentify.test',
            'state' => $state,
        ]);

        // Unauthenticated visit to /auth/sso/authorize stores intended URL and redirects to login.
        $this->get($authorizeUrl)->assertRedirect(route('login'));

        $loginResponse = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $loginResponse->assertRedirect($authorizeUrl);
    }

    public function test_sp_initiated_sso_callback_validates_state_and_blocks_login_csrf(): void
    {
        $user = $this->createUserWithTenants(1);
        $expectedState = Str::random(40);
        $wrongState = Str::random(40);
        $token = app(SsoService::class)->issueToken($user, $this->tenantA);

        // Wrong state must be rejected without authenticating.
        $this->withSession(['sso_state' => $expectedState])
            ->get('http://instansi-a.presentify.test/sso/callback?'.http_build_query([
                'token' => $token,
                'state' => $wrongState,
            ]))
            ->assertForbidden();

        $this->assertGuest();

        // Missing state when sso_state exists in session must also be rejected.
        $token2 = app(SsoService::class)->issueToken($user, $this->tenantA);
        $this->withSession(['sso_state' => $expectedState])
            ->get('http://instansi-a.presentify.test/sso/callback?'.http_build_query([
                'token' => $token2,
            ]))
            ->assertForbidden();

        $this->assertGuest();

        // Matching state succeeds and logs user in.
        $token3 = app(SsoService::class)->issueToken($user, $this->tenantA);
        $this->withSession(['sso_state' => $expectedState])
            ->get('http://instansi-a.presentify.test/sso/callback?'.http_build_query([
                'token' => $token3,
                'state' => $expectedState,
            ]))
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
        $this->assertFalse(SsoToken::query()->where('token_hash', hash('sha256', $token3))->exists());
        $this->assertSame(2, SsoToken::count());
    }

    public function test_tenant_subdomain_switch_endpoint_redirects_to_target_tenant_sso_callback(): void
    {
        $user = $this->createUserWithTenants(2);

        $response = $this->actingAs($user)
            ->withSession([ScopeSessions::$tenantIdKey => $this->tenantA->id])
            ->withHeaders(['X-Inertia' => 'true'])
            ->post('http://instansi-a.presentify.test/sso/switch', [
                'tenant_id' => $this->tenantB->id,
            ]);

        $response->assertStatus(409);
        $location = (string) $response->headers->get('X-Inertia-Location');
        $this->assertStringStartsWith('http://instansi-b.presentify.test/sso/callback?token=', $location);
    }

    public function test_tenant_sso_logout_triggers_signed_central_slo_and_rejects_tampered_or_expired_urls(): void
    {
        $user = $this->createUserWithTenants(1);

        $response = $this->actingAs($user)
            ->withSession([ScopeSessions::$tenantIdKey => $this->tenantA->id])
            ->withHeaders(['X-Inertia' => 'true'])
            ->post('http://instansi-a.presentify.test/sso/logout');

        $response->assertStatus(409);
        $this->assertGuest();

        $location = (string) $response->headers->get('X-Inertia-Location');
        $this->assertStringStartsWith('http://presentify.test/auth/sso/logout?', $location);

        // Following the signed URL clears the Central session.
        $this->flushHeaders()
            ->actingAs($user)
            ->get($location)
            ->assertRedirect(route('login'));

        $this->assertGuest();

        // Unsigned and expired SLO URLs must be rejected with 403.
        $this->get('http://presentify.test/auth/sso/logout')->assertForbidden();

        $expiredSignedUrl = 'http://presentify.test'.URL::temporarySignedRoute(
            'central.sso.logout',
            now()->subMinute(),
            [],
            false,
        );
        $this->get($expiredSignedUrl)->assertForbidden();
    }

    public function test_inertia_shared_props_include_tenancy_context_and_active_tenants(): void
    {
        $user = $this->createUserWithTenants(2);

        $this->actingAs($user)
            ->withoutVite()
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('tenancy.current', null)
                ->has('tenancy.tenants', 2)
            );

        $this->actingAs($user)
            ->withSession([ScopeSessions::$tenantIdKey => $this->tenantA->id])
            ->withoutVite()
            ->get('http://instansi-a.presentify.test/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('tenancy.current.id', $this->tenantA->id)
                ->where('tenancy.current.slug', 'instansi-a')
                ->has('tenancy.tenants', 2)
            );
    }
}
