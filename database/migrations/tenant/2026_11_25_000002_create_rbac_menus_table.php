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
            $table->id();
            $table->string('key')->unique()->comment('System key dari master registry (tidak berubah)');
            $table->string('label')->comment('Label bawaan sistem');
            $table->string('custom_label')->nullable()->comment('Label kustom oleh Admin Tenant');
            $table->string('icon')->nullable()->comment('Icon bawaan (lucide name)');
            $table->string('route_name')->nullable()->comment('Named route Laravel');
            $table->unsignedBigInteger('parent_id')->nullable()->comment('Hierarki menu induk');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->timestamps();

            $table->index('parent_id');
            $table->index('sort_order');
            $table->foreign('parent_id')->references('id')->on('rbac_menus')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rbac_menus');
    }
};
