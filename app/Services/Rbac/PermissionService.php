<?php

declare(strict_types=1);

namespace App\Services\Rbac;

use App\Models\Member;
use App\Models\Permission;
use App\Models\Tenant;
use App\Models\User;

/**
 * Evaluates 2-tier permissions (menu-bound and global) within an initialized tenant context.
 */
class PermissionService
{
    /** @var array<string, array{permissions: array<string, Permission>, viewByMenu: array<int, string>}> */
    private array $tenantPermissionCatalog = [];

    /** @var array<string, array{isSuperadmin: bool, permissionNames: array<string, true>}> */
    private array $userGrantsCache = [];

    public function flushCache(): void
    {
        $this->tenantPermissionCatalog = [];
        $this->userGrantsCache = [];
    }

    public function resolveActiveMember(User $user): ?Member
    {
        if (! tenancy()->initialized) {
            return null;
        }

        return Member::findActiveForUser($user);
    }

    public function hasKnownPermission(string $permissionName): bool
    {
        if (! tenancy()->initialized) {
            return false;
        }

        $catalog = $this->getTenantCatalog();

        return isset($catalog['permissions'][$permissionName]);
    }

    public function allows(User $user, string $permissionName): bool
    {
        if (! tenancy()->initialized) {
            return false;
        }

        $grants = $this->getUserGrants($user);

        if ($grants === null) {
            return false;
        }

        if ($grants['isSuperadmin']) {
            return true;
        }

        if (! isset($grants['permissionNames'][$permissionName])) {
            return false;
        }

        $catalog = $this->getTenantCatalog();
        $permission = $catalog['permissions'][$permissionName] ?? null;

        if ($permission === null) {
            return false;
        }

        // Tier-1 non-view menu actions require the menu's view permission to be active as well.
        if ($permission->isMenuBound() && $permission->action !== 'view' && $permission->menu_id !== null) {
            $viewPermissionName = $catalog['viewByMenu'][$permission->menu_id] ?? null;

            if ($viewPermissionName !== null && ! isset($grants['permissionNames'][$viewPermissionName])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, string>  $permissions
     */
    public function allowsAny(User $user, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->allows($user, $permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    public function getEffectivePermissionsForUser(User $user): array
    {
        if (! tenancy()->initialized) {
            return [];
        }

        $grants = $this->getUserGrants($user);

        if ($grants === null) {
            return [];
        }

        $catalog = $this->getTenantCatalog();

        if ($grants['isSuperadmin']) {
            return array_keys($catalog['permissions']);
        }

        $effective = [];

        foreach (array_keys($catalog['permissions']) as $permissionName) {
            if ($this->allows($user, $permissionName)) {
                $effective[] = $permissionName;
            }
        }

        return $effective;
    }

    /**
     * @return array{permissions: array<string, Permission>, viewByMenu: array<int, string>}
     */
    private function getTenantCatalog(): array
    {
        $tenantKey = $this->currentTenantKey();

        if (isset($this->tenantPermissionCatalog[$tenantKey])) {
            return $this->tenantPermissionCatalog[$tenantKey];
        }

        $permissions = [];
        $viewByMenu = [];

        foreach (Permission::query()->get() as $permission) {
            $permissions[$permission->name] = $permission;

            if ($permission->menu_id !== null && $permission->action === 'view') {
                $viewByMenu[$permission->menu_id] = $permission->name;
            }
        }

        return $this->tenantPermissionCatalog[$tenantKey] = [
            'permissions' => $permissions,
            'viewByMenu' => $viewByMenu,
        ];
    }

    /**
     * @return array{isSuperadmin: bool, permissionNames: array<string, true>}|null
     */
    private function getUserGrants(User $user): ?array
    {
        $tenantKey = $this->currentTenantKey();
        $cacheKey = "{$tenantKey}:{$user->id}";

        if (isset($this->userGrantsCache[$cacheKey])) {
            return $this->userGrantsCache[$cacheKey];
        }

        $member = Member::query()
            ->where('global_user_id', $user->id)
            ->active()
            ->with('roles.permissions')
            ->first();

        if ($member === null) {
            return null;
        }

        $isSuperadmin = false;
        $permissionNames = [];

        foreach ($member->roles as $role) {
            if ($role->name === 'superadmin_saas') {
                $isSuperadmin = true;
            }

            foreach ($role->permissions as $permission) {
                $permissionNames[$permission->name] = true;
            }
        }

        return $this->userGrantsCache[$cacheKey] = [
            'isSuperadmin' => $isSuperadmin,
            'permissionNames' => $permissionNames,
        ];
    }

    private function currentTenantKey(): string
    {
        $tenant = tenancy()->tenant;

        return $tenant instanceof Tenant ? (string) $tenant->getTenantKey() : 'default';
    }
}
