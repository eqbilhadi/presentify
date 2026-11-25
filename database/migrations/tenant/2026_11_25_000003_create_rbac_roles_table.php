<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rbac_roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique()->comment('Slug role (tidak berubah, dipakai di kode)');
            $table->string('label')->comment('Label tampilan role');
            $table->string('description')->nullable();
            $table->boolean('is_system')->default(false)->comment('true = role bawaan, tidak bisa dihapus');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rbac_roles');
    }
};
