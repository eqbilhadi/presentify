<?php

declare(strict_types=1);

namespace App\Services\Rbac;

use App\Models\Member;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

class TenantUserManagementService
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
     *     nip: string|null,
     *     position: string|null,
     *     is_active: bool,
     *     last_accessed_at: string|null,
     *     roles: array<int, array{id: string, name: string}>,
     * }>
     */
    public function listUsers(Tenant $tenant): array
    {
        $memberships = TenantUser::query()
            ->where('tenant_id', $tenant->getTenantKey())
            ->with('user')
            ->get()
            ->filter(static fn (TenantUser $membership): bool => $membership->user instanceof User)
            ->sortBy(static fn (TenantUser $membership): string => strtolower((string) $membership->user?->name))
            ->values();

        $userIds = $memberships->pluck('user_id')->map(static fn (mixed $id): int => (int) $id)->all();

        $rolesByUser = $this->loadTenantRolesByUserId($userIds);
        $membersByUser = $userIds === []
            ? collect()
            : Member::query()
                ->whereIn('global_user_id', $userIds)
                ->get()
                ->keyBy('global_user_id');

        return $memberships->map(static function (TenantUser $membership) use ($rolesByUser, $membersByUser): array {
            /** @var User $user */
            $user = $membership->user;
            /** @var Member|null $member */
            $member = $membersByUser->get($user->id);

            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'nip' => $member?->nip,
                'position' => $member?->position,
                'is_active' => $membership->is_active,
                'last_accessed_at' => $membership->last_accessed_at?->toIso8601String(),
                'roles' => $rolesByUser[$user->id] ?? [],
            ];
        })->all();
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
     *     nip: string|null,
     *     position: string|null,
     *     is_active: bool,
     *     role_ids: array<int, string>,
     * }
     */
    public function getUserDetail(Tenant $tenant, User $user): array
    {
        $membership = $this->findMembershipOrFail($tenant, $user);
        $member = Member::query()->where('global_user_id', $user->id)->first();

        $roleIds = $user->roles()
            ->pluck(config('permission.table_names.roles', 'rbac_roles').'.id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->values()
            ->all();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'nip' => $member?->nip,
            'position' => $member?->position,
            'is_active' => $membership->is_active,
            'role_ids' => $roleIds,
        ];
    }

    /**
     * @param  array{
     *     name?: string|null,
     *     email: string,
     *     password?: string|null,
     *     nip?: string|null,
     *     position?: string|null,
     *     is_active?: bool,
     *     role_ids: array<int, string>,
     * }  $data
     * @return array{user: User, attached_existing: bool}
     */
    public function createOrAttach(Tenant $tenant, array $data): array
    {
        $email = strtolower(trim($data['email']));
        $isActive = (bool) ($data['is_active'] ?? true);
        $nip = isset($data['nip']) && trim((string) $data['nip']) !== '' ? trim((string) $data['nip']) : null;
        $position = isset($data['position']) && trim((string) $data['position']) !== '' ? trim((string) $data['position']) : null;

        $existingUser = User::query()->where('email', $email)->first();
        $attachedExisting = $existingUser instanceof User;

        if ($existingUser instanceof User) {
            $alreadyMember = TenantUser::query()
                ->where('tenant_id', $tenant->getTenantKey())
                ->where('user_id', $existingUser->id)
                ->exists();

            if ($alreadyMember) {
                throw ValidationException::withMessages([
                    'email' => 'Pengguna dengan email ini sudah terdaftar di instansi ini.',
                ]);
            }
        }

        if ($nip !== null && Member::query()->where('nip', $nip)->exists()) {
            throw ValidationException::withMessages([
                'nip' => 'NIP tersebut sudah digunakan oleh pegawai lain di instansi ini.',
            ]);
        }

        $centralConnection = (string) config('tenancy.database.central_connection', config('database.default'));

        $user = DB::connection($centralConnection)->transaction(function () use ($tenant, $existingUser, $data, $email, $isActive): User {
            if ($existingUser instanceof User) {
                $user = $existingUser;
            } else {
                /** @var User $user */
                $user = User::query()->create([
                    'name' => trim((string) ($data['name'] ?? '')),
                    'email' => $email,
                    'password' => (string) ($data['password'] ?? ''),
                ]);
                $user->forceFill(['email_verified_at' => now()])->save();
            }

            TenantUser::query()->create([
                'tenant_id' => $tenant->getTenantKey(),
                'user_id' => $user->id,
                'is_active' => $isActive,
            ]);

            return $user;
        });

        DB::transaction(function () use ($user, $data, $nip, $position, $isActive): void {
            $roles = Role::query()
                ->whereIn('id', array_values(array_unique($data['role_ids'])))
                ->where('guard_name', 'web')
                ->get();

            $user->syncRoles($roles);

            Member::withTrashed()->updateOrCreate(
                ['global_user_id' => $user->id],
                [
                    'name' => $user->name,
                    'nip' => $nip,
                    'position' => $position,
                    'status' => $isActive ? 'active' : 'inactive',
                    'deleted_at' => null,
                ],
            );
        });

        $this->permissionRegistrar->forgetCachedPermissions();
        $this->menuService->invalidateForUser($user);

        return [
            'user' => $user->refresh(),
            'attached_existing' => $attachedExisting,
        ];
    }

    /**
     * @param  array{
     *     nip?: string|null,
     *     position?: string|null,
     *     is_active: bool,
     *     role_ids: array<int, string>,
     * }  $data
     */
    public function update(Tenant $tenant, User $user, array $data, User $actor): User
    {
        $membership = $this->findMembershipOrFail($tenant, $user);

        if ($user->id === $actor->id && ! $data['is_active']) {
            throw ValidationException::withMessages([
                'is_active' => 'Anda tidak dapat menonaktifkan keanggotaan Anda sendiri.',
            ]);
        }

        $nip = isset($data['nip']) && trim((string) $data['nip']) !== '' ? trim((string) $data['nip']) : null;
        $position = isset($data['position']) && trim((string) $data['position']) !== '' ? trim((string) $data['position']) : null;

        if ($nip !== null) {
            $nipUsedByOther = Member::query()
                ->where('nip', $nip)
                ->where('global_user_id', '!=', $user->id)
                ->exists();

            if ($nipUsedByOther) {
                throw ValidationException::withMessages([
                    'nip' => 'NIP tersebut sudah digunakan oleh pegawai lain di instansi ini.',
                ]);
            }
        }

        $membership->update(['is_active' => (bool) $data['is_active']]);

        DB::transaction(function () use ($user, $data, $nip, $position): void {
            $roles = Role::query()
                ->whereIn('id', array_values(array_unique($data['role_ids'])))
                ->where('guard_name', 'web')
                ->get();

            $user->syncRoles($roles);

            Member::withTrashed()->updateOrCreate(
                ['global_user_id' => $user->id],
                [
                    'name' => $user->name,
                    'nip' => $nip,
                    'position' => $position,
                    'status' => $data['is_active'] ? 'active' : 'inactive',
                    'deleted_at' => null,
                ],
            );
        });

        $this->permissionRegistrar->forgetCachedPermissions();
        $this->menuService->invalidateForUser($user);

        return $user->refresh();
    }

    public function toggleStatus(Tenant $tenant, User $user, User $actor): bool
    {
        $membership = $this->findMembershipOrFail($tenant, $user);

        if ($user->id === $actor->id && $membership->is_active) {
            throw ValidationException::withMessages([
                'user' => 'Anda tidak dapat menonaktifkan keanggotaan Anda sendiri.',
            ]);
        }

        $nextStatus = ! $membership->is_active;
        $membership->update(['is_active' => $nextStatus]);

        Member::query()
            ->where('global_user_id', $user->id)
            ->update(['status' => $nextStatus ? 'active' : 'inactive']);

        $this->menuService->invalidateForUser($user);

        return $nextStatus;
    }

    private function findMembershipOrFail(Tenant $tenant, User $user): TenantUser
    {
        $membership = TenantUser::query()
            ->where('tenant_id', $tenant->getTenantKey())
            ->where('user_id', $user->id)
            ->first();

        abort_unless($membership instanceof TenantUser, 404, 'Pengguna tidak terdaftar pada instansi ini.');

        return $membership;
    }

    /**
     * @param  array<int, int>  $userIds
     * @return array<int, array<int, array{id: string, name: string}>>
     */
    private function loadTenantRolesByUserId(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $rolesTable = (string) config('permission.table_names.roles', 'rbac_roles');
        $pivotTable = (string) config('permission.table_names.model_has_roles', 'rbac_model_has_roles');
        $rolePivotKey = (string) (config('permission.column_names.role_pivot_key') ?: 'role_id');
        $morphKey = (string) (config('permission.column_names.model_morph_key') ?: 'model_id');

        $rows = DB::table($pivotTable)
            ->join($rolesTable, "{$rolesTable}.id", '=', "{$pivotTable}.{$rolePivotKey}")
            ->where("{$pivotTable}.model_type", User::class)
            ->whereIn("{$pivotTable}.{$morphKey}", $userIds)
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
