<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');

        if (empty($tableNames)) {
            throw new Exception('Error: config/permission.php not loaded.');
        }

        Schema::create($tableNames['permissions'], function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('guard_name');
            $table->string('group')->nullable()->index();
            $table->timestamps();

            $table->unique(['name', 'guard_name']);
        });

        Schema::create($tableNames['roles'], function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();

            $table->unique(['name', 'guard_name']);
        });

        Schema::create($tableNames['model_has_permissions'], function (Blueprint $table) use ($tableNames, $columnNames) {
            $table->ulid('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger($columnNames['model_morph_key']);

            $table->index([$columnNames['model_morph_key'], 'model_type'], 'rbac_model_has_permissions_model_id_model_type_index');

            $table->foreign('permission_id')
                ->references('id')
                ->on($tableNames['permissions'])
                ->cascadeOnDelete();

            $table->primary(
                ['permission_id', $columnNames['model_morph_key'], 'model_type'],
                'rbac_model_has_permissions_primary',
            );
        });

        Schema::create($tableNames['model_has_roles'], function (Blueprint $table) use ($tableNames, $columnNames) {
            $table->ulid('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger($columnNames['model_morph_key']);

            $table->index([$columnNames['model_morph_key'], 'model_type'], 'rbac_model_has_roles_model_id_model_type_index');

            $table->foreign('role_id')
                ->references('id')
                ->on($tableNames['roles'])
                ->cascadeOnDelete();

            $table->primary(
                ['role_id', $columnNames['model_morph_key'], 'model_type'],
                'rbac_model_has_roles_primary',
            );
        });

        Schema::create($tableNames['role_has_permissions'], function (Blueprint $table) use ($tableNames) {
            $table->ulid('permission_id');
            $table->ulid('role_id');

            $table->foreign('permission_id')
                ->references('id')
                ->on($tableNames['permissions'])
                ->cascadeOnDelete();

            $table->foreign('role_id')
                ->references('id')
                ->on($tableNames['roles'])
                ->cascadeOnDelete();

            $table->primary(['permission_id', 'role_id'], 'rbac_role_has_permissions_primary');
        });

        app('cache')
            ->store(config('permission.cache.store') !== 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        $tableNames = config('permission.table_names');

        if (empty($tableNames)) {
            throw new Exception('Error: config/permission.php not found and defaults could not be merged.');
        }

        Schema::dropIfExists($tableNames['role_has_permissions']);
        Schema::dropIfExists($tableNames['model_has_roles']);
        Schema::dropIfExists($tableNames['model_has_permissions']);
        Schema::dropIfExists($tableNames['roles']);
        Schema::dropIfExists($tableNames['permissions']);
    }
};
