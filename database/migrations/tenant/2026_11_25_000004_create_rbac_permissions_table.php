<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rbac_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('Slug permission (unik per menu_id)');
            $table->string('label')->comment('Label tampilan permission');
            // Tier-1: menu-bound (menu_id != null) | Tier-2: global (menu_id = null)
            $table->unsignedBigInteger('menu_id')->nullable()->comment('NULL = global permission lintas modul');
            $table->string('action')->comment('view | create | edit | delete | export | import | override | bypass');
            $table->timestamps();

            $table->unique(['name', 'menu_id']);
            $table->index('menu_id');
            $table->foreign('menu_id')->references('id')->on('rbac_menus')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rbac_permissions');
    }
};
