<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('org_members', function (Blueprint $table) {
            $table->id();
            // References central DB users.id — no FK constraint across DB connections
            $table->unsignedBigInteger('global_user_id');
            $table->string('nip', 50)->nullable()->unique()->comment('Nomor Induk Pegawai/Identitas lokal unik');
            $table->string('name');
            $table->string('position')->nullable()->comment('Jabatan/posisi lokal di instansi');
            $table->unsignedBigInteger('unit_id')->nullable()->comment('Penempatan unit kerja saat ini');
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
            $table->json('meta')->nullable()->comment('Data tambahan: foto, kontak, dsb.');
            $table->timestamps();
            $table->softDeletes();

            $table->index('global_user_id');
            $table->index('unit_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('org_members');
    }
};
