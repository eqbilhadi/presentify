<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use App\Services\Rbac\MenuService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

class CentralDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CentralRolesAndPermissionsSeeder::class,
            CentralMenuSeeder::class,
        ]);

        // Akun Central SaaS (tidak terhubung ke tenant manapun; langsung masuk ke Central Dashboard).
        $superadmin = User::factory()->create([
            'name' => 'Superadmin',
            'email' => 'admin@presentify.test',
        ]);
        $superadmin->assignRole('superadmin_saas');

        // Akun Admin Tenant contoh (terhubung ke 2 tenant untuk menguji Workspace Picker & Instansi Switcher).
        $tenantAdmin = User::factory()->create([
            'name' => 'Admin Instansi',
            'email' => 'admin.tenant@presentify.test',
        ]);

        // Akun Pegawai contoh (terhubung ke 1 tenant; langsung redirect SSO ke disdukcapil).
        $demoUser = User::factory()->create([
            'name' => 'Demo User',
            'email' => 'demo@presentify.test',
        ]);

        // stancl/tenancy auto-creates, migrates, and seeds each tenant DB via TenantCreated
        $tenantA = Tenant::create([
            'name' => 'Dinas Kependudukan Kota Contoh',
            'slug' => 'disdukcapil',
            'is_active' => true,
            'contact_email' => 'admin@disdukcapil.presentify.test',
            'theme_config' => [
                'primary' => '220 90% 50%',
                'accent' => '262 83% 58%',
                'radius' => '0.5rem',
            ],
            'terminology' => [
                'employee' => 'Pegawai',
                'unit' => 'Bidang',
            ],
        ]);

        $tenantA->domains()->create(['domain' => 'disdukcapil']);

        $tenantB = Tenant::create([
            'name' => 'SMA Negeri 1 Contoh',
            'slug' => 'sman1contoh',
            'is_active' => true,
            'contact_email' => 'admin@sman1contoh.presentify.test',
            'theme_config' => [
                'primary' => '142 72% 29%',
                'accent' => '45 93% 47%',
                'radius' => '0.75rem',
            ],
            'terminology' => [
                'employee' => 'Guru',
                'unit' => 'Kelas',
            ],
        ]);

        $tenantB->domains()->create(['domain' => 'sman1contoh']);

        DB::table('sys_tenant_user')->insert([
            [
                'tenant_id' => $tenantA->id,
                'user_id' => $tenantAdmin->id,
                'is_active' => true,
                'last_accessed_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'tenant_id' => $tenantB->id,
                'user_id' => $tenantAdmin->id,
                'is_active' => true,
                'last_accessed_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'tenant_id' => $tenantA->id,
                'user_id' => $demoUser->id,
                'is_active' => true,
                'last_accessed_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->assignTenantRole($tenantA, $tenantAdmin, 'admin_tenant');
        $this->assignTenantRole($tenantB, $tenantAdmin, 'admin_tenant');
        $this->assignTenantRole($tenantA, $demoUser, 'member');
    }

    private function assignTenantRole(Tenant $tenant, User $user, string $roleName): void
    {
        tenancy()->initialize($tenant);

        try {
            $user->assignRole($roleName);
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            app(MenuService::class)->invalidateForUser($user);
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }
    }
}
