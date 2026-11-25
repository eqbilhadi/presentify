<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auth_sso_tokens', function (Blueprint $table) {
            $table->id();
            // Cryptographically random token hash — deleted immediately after use
            $table->string('token_hash')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('target_tenant_id');
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->index(['token_hash', 'expires_at']);

            $table->foreign('target_tenant_id')
                ->references('id')
                ->on('sys_tenants')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_sso_tokens');
    }
};
