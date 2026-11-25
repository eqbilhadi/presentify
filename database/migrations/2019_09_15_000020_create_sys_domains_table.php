<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSysDomainsTable extends Migration
{
    public function up(): void
    {
        Schema::create('sys_domains', function (Blueprint $table) {
            $table->increments('id');
            $table->string('domain', 255)->unique();
            // tenant_id references sys_tenants.id (UUID string)
            $table->string('tenant_id');

            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('sys_tenants')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sys_domains');
    }
}
