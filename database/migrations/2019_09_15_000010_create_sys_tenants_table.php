<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSysTenantsTable extends Migration
{
    public function up(): void
    {
        Schema::create('sys_tenants', function (Blueprint $table) {
            // stancl/tenancy uses a string (UUID) primary key
            $table->string('id')->primary();

            $table->string('name');
            $table->string('slug')->unique();

            // JSON blob used by stancl VirtualColumn for unmapped attributes.
            // theme_config, terminology, logo_path, etc. live here.
            $table->json('data')->nullable();

            $table->boolean('is_active')->default(true);
            $table->string('contact_email')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sys_tenants');
    }
}
