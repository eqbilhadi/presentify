<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Traits\HasRoles;

/**
 * Wraps Spatie's HasRoles so role and permission pivots always resolve
 * against the active DB connection (Central or Tenant) even though User
 * itself uses CentralConnection.
 */
trait HasTenantAwareRoles
{
    use HasRoles {
        roles as protected spatieRoles;
        permissions as protected spatiePermissions;
    }

    private ?string $rbacLoadedContext = null;

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        $this->ensureTenantAwareRelationsFresh();

        /** @var BelongsToMany<Role, $this> */
        return $this->spatieRoles();
    }

    /**
     * @return BelongsToMany<Permission, $this>
     */
    public function permissions(): BelongsToMany
    {
        $this->ensureTenantAwareRelationsFresh();

        /** @var BelongsToMany<Permission, $this> */
        return $this->spatiePermissions();
    }

    public function getRelationValue($key)
    {
        if ($key === 'roles' || $key === 'permissions') {
            $this->ensureTenantAwareRelationsFresh();
        }

        return parent::getRelationValue($key);
    }

    /**
     * Override newRelatedInstance so Role and Permission bind to the active default
     * connection instead of inheriting User's CentralConnection.
     *
     * @template TRelatedModel of Model
     *
     * @param  class-string<TRelatedModel>  $class
     * @return TRelatedModel
     */
    protected function newRelatedInstance($class)
    {
        return tap(new $class, function (Model $instance): void {
            if (! $instance->getConnectionName()) {
                $connection = in_array($instance::class, [Role::class, Permission::class], true)
                    ? DB::getDefaultConnection()
                    : $this->getConnectionName();

                $instance->setConnection($connection);
            }
        });
    }

    private function ensureTenantAwareRelationsFresh(): void
    {
        $tenant = tenancy()->initialized ? tenancy()->tenant : null;
        $currentContext = $tenant instanceof Tenant
            ? (string) $tenant->getTenantKey()
            : 'central';

        if ($this->rbacLoadedContext !== $currentContext) {
            $this->unsetRelation('roles');
            $this->unsetRelation('permissions');
            $this->rbacLoadedContext = $currentContext;
        }
    }
}
