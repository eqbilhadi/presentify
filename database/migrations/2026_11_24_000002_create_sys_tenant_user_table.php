<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sys_tenant_user', function (Blueprint $table) {
            $table->id();
            // tenant_id is a UUID string matching sys_tenants.id
            $table->string('tenant_id');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_accessed_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'user_id']);

            $table->foreign('tenant_id')
                ->references('id')
                ->on('sys_tenants')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sys_tenant_user');
    }
};
