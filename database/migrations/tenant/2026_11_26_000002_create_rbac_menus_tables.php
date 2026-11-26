<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rbac_menus', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('parent_id')->nullable()->index();
            $table->integer('sort_num')->default(0)->index();
            $table->string('icon')->nullable();
            $table->string('label_name');
            $table->string('controller_name')->nullable();
            $table->string('route_name')->nullable();
            $table->string('url')->nullable();
            $table->string('permission_prefix')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_divider')->default(false);
            $table->timestamps();

            $table->foreign('parent_id')
                ->references('id')
                ->on('rbac_menus')
                ->nullOnDelete();
        });

        Schema::create('rbac_menu_roles', function (Blueprint $table) {
            $table->ulid('menu_id');
            $table->ulid('role_id');

            $table->foreign('menu_id')
                ->references('id')
                ->on('rbac_menus')
                ->cascadeOnDelete();

            $table->foreign('role_id')
                ->references('id')
                ->on('rbac_roles')
                ->cascadeOnDelete();

            $table->primary(['menu_id', 'role_id']);
        });

        Schema::create('rbac_menu_actions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('menu_id');
            $table->string('action');
            $table->string('label');
            $table->string('permission_name')->unique();
            $table->string('route_name')->nullable();
            $table->integer('order')->default(0);
            $table->timestamps();

            $table->foreign('menu_id')
                ->references('id')
                ->on('rbac_menus')
                ->cascadeOnDelete();

            $table->unique(['menu_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rbac_menu_actions');
        Schema::dropIfExists('rbac_menu_roles');
        Schema::dropIfExists('rbac_menus');
    }
};
