<?php

namespace Tests\Feature\Auth;

use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
        // Clean up tenant databases created by stancl/tenancy during this test.
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

        // Expects a redirect to the tenant subdomain SSO callback.
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
        // User has NO memberships at all.

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
            'is_active' => false, // inactive
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

        // Mark tenant inactive.
        $this->tenantA->update(['is_active' => false]);

        $this->actingAs($user)
            ->get(route('workspace.picker'))
            ->assertInertia(fn ($page) => $page
                ->component('auth/tenant-picker')
                ->has('tenants', 0)
            );
    }
}
