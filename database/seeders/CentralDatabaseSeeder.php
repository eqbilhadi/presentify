<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CentralDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::factory()->create([
            'name'  => 'Superadmin',
            'email' => 'admin@presentify.test',
        ]);

        $demoUser = User::factory()->create([
            'name'  => 'Demo User',
            'email' => 'demo@presentify.test',
        ]);

        // stancl/tenancy auto-creates the tenant DB and runs migrations on TenantCreated
        $tenantA = Tenant::create([
            'name'          => 'Dinas Kependudukan Kota Contoh',
            'slug'          => 'disdukcapil',
            'is_active'     => true,
            'contact_email' => 'admin@disdukcapil.presentify.test',
            'theme_config'  => [
                'primary' => '220 90% 50%',
                'accent'  => '262 83% 58%',
                'radius'  => '0.5rem',
            ],
            'terminology' => [
                'employee' => 'Pegawai',
                'unit'     => 'Bidang',
            ],
        ]);

        $tenantA->domains()->create(['domain' => 'disdukcapil']);

        $tenantB = Tenant::create([
            'name'          => 'SMA Negeri 1 Contoh',
            'slug'          => 'sman1contoh',
            'is_active'     => true,
            'contact_email' => 'admin@sman1contoh.presentify.test',
            'theme_config'  => [
                'primary' => '142 72% 29%',
                'accent'  => '45 93% 47%',
                'radius'  => '0.75rem',
            ],
            'terminology' => [
                'employee' => 'Guru',
                'unit'     => 'Kelas',
            ],
        ]);

        $tenantB->domains()->create(['domain' => 'sman1contoh']);

        // admin belongs to both tenants; demo user belongs only to tenantA
        DB::table('sys_tenant_user')->insert([
            [
                'tenant_id'        => $tenantA->id,
                'user_id'          => $admin->id,
                'is_active'        => true,
                'last_accessed_at' => null,
                'created_at'       => now(),
                'updated_at'       => now(),
            ],
            [
                'tenant_id'        => $tenantB->id,
                'user_id'          => $admin->id,
                'is_active'        => true,
                'last_accessed_at' => null,
                'created_at'       => now(),
                'updated_at'       => now(),
            ],
            [
                'tenant_id'        => $tenantA->id,
                'user_id'          => $demoUser->id,
                'is_active'        => true,
                'last_accessed_at' => null,
                'created_at'       => now(),
                'updated_at'       => now(),
            ],
        ]);
    }
}
