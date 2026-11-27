<?php

declare(strict_types=1);

namespace App\Services\Rbac;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

class CentralUserManagementService
{
    public function __construct(
        private readonly MenuService $menuService,
        private readonly PermissionRegistrar $permissionRegistrar,
    ) {}

    /**
     * @return array<int, array{
     *     id: int,
     *     name: string,
     *     email: string,
     *     email_verified_at: string|null,
     *     roles: array<int, array{id: string, name: string}>,
     *     tenants_count: int,
     *     created_at: string|null,
     * }>
     */
    public function listUsers(): array
    {
        $rolesByUser = $this->loadRolesByUserId();

        return User::query()
            ->withCount('activeTenants')
            ->orderBy('name')
            ->get()
            ->map(static fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                'roles' => $rolesByUser[$user->id] ?? [],
                'tenants_count' => (int) ($user->active_tenants_count ?? 0),
                'created_at' => $user->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    public function getRoleOptions(): array
    {
        return Role::query()
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(static fn (Role $role): array => [
                'id' => $role->id,
                'name' => $role->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     email: string,
     *     role_ids: array<int, string>,
     * }
     */
    public function getUserDetail(User $user): array
    {
        $roleIds = $user->roles()
            ->pluck(config('permission.table_names.roles', 'rbac_roles').'.id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->values()
            ->all();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role_ids' => $roleIds,
        ];
    }

    /**
     * @param  array{
     *     name: string,
     *     email: string,
     *     password: string,
     *     role_ids: array<int, string>,
     * }  $data
     */
    public function create(array $data): User
    {
        $centralConnection = (string) config('tenancy.database.central_connection', config('database.default'));

        $user = DB::connection($centralConnection)->transaction(function () use ($data): User {
            /** @var User $user */
            $user = User::query()->create([
                'name' => trim($data['name']),
                'email' => strtolower(trim($data['email'])),
                'password' => $data['password'],
            ]);

            $user->forceFill(['email_verified_at' => now()])->save();

            $roles = Role::query()
                ->whereIn('id', array_values(array_unique($data['role_ids'])))
                ->where('guard_name', 'web')
                ->get();

            $user->syncRoles($roles);

            return $user;
        });

        $this->permissionRegistrar->forgetCachedPermissions();
        $this->menuService->invalidateForUser($user);

        return $user->refresh();
    }

    /**
     * @param  array{
     *     name: string,
     *     email: string,
     *     password?: string|null,
     *     role_ids: array<int, string>,
     * }  $data
     */
    public function update(User $user, array $data): User
    {
        $centralConnection = (string) config('tenancy.database.central_connection', config('database.default'));

        DB::connection($centralConnection)->transaction(function () use ($user, $data): void {
            $attributes = [
                'name' => trim($data['name']),
                'email' => strtolower(trim($data['email'])),
            ];

            if (! empty($data['password'])) {
                $attributes['password'] = $data['password'];
            }

            $user->update($attributes);

            $roles = Role::query()
                ->whereIn('id', array_values(array_unique($data['role_ids'])))
                ->where('guard_name', 'web')
                ->get();

            $user->syncRoles($roles);
        });

        $this->permissionRegistrar->forgetCachedPermissions();
        $this->menuService->invalidateForUser($user);

        return $user->refresh();
    }

    public function delete(User $user, User $actor): void
    {
        if ($user->id === $actor->id) {
            throw ValidationException::withMessages([
                'user' => 'Anda tidak dapat menghapus akun Anda sendiri.',
            ]);
        }

        $centralConnection = (string) config('tenancy.database.central_connection', config('database.default'));

        $this->menuService->invalidateForUser($user);

        DB::connection($centralConnection)->transaction(function () use ($user): void {
            $user->syncRoles([]);
            $user->syncPermissions([]);
            $user->delete();
        });

        $this->permissionRegistrar->forgetCachedPermissions();
    }

    /**
     * @return array<int, array<int, array{id: string, name: string}>>
     */
    private function loadRolesByUserId(): array
    {
        $rolesTable = (string) config('permission.table_names.roles', 'rbac_roles');
        $pivotTable = (string) config('permission.table_names.model_has_roles', 'rbac_model_has_roles');
        $rolePivotKey = (string) (config('permission.column_names.role_pivot_key') ?: 'role_id');
        $morphKey = (string) (config('permission.column_names.model_morph_key') ?: 'model_id');

        $rows = DB::table($pivotTable)
            ->join($rolesTable, "{$rolesTable}.id", '=', "{$pivotTable}.{$rolePivotKey}")
            ->where("{$pivotTable}.model_type", User::class)
            ->orderBy("{$rolesTable}.name")
            ->get([
                "{$pivotTable}.{$morphKey} as user_id",
                "{$rolesTable}.id as role_id",
                "{$rolesTable}.name as role_name",
            ]);

        /** @var array<int, array<int, array{id: string, name: string}>> $grouped */
        $grouped = [];

        foreach ($rows as $row) {
            $userId = (int) $row->user_id;
            $grouped[$userId][] = [
                'id' => (string) $row->role_id,
                'name' => (string) $row->role_name,
            ];
        }

        return $grouped;
    }
}
