<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pivot: member <-> role assignment (polymorphic-style via model_type)
        Schema::create('rbac_model_has_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');

            $table->primary(['role_id', 'model_type', 'model_id']);
            $table->index(['model_type', 'model_id'], 'idx_model_has_roles_model');
            $table->foreign('role_id')->references('id')->on('rbac_roles')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rbac_model_has_roles');
    }
};
