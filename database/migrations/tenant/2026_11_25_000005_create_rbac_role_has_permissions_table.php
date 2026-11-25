<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pivot: role <-> permission (many-to-many)
        Schema::create('rbac_role_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('permission_id');

            $table->primary(['role_id', 'permission_id']);
            $table->foreign('role_id')->references('id')->on('rbac_roles')->cascadeOnDelete();
            $table->foreign('permission_id')->references('id')->on('rbac_permissions')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rbac_role_has_permissions');
    }
};
