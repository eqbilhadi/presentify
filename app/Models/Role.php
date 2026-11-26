<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * @property string $id
 * @property string $name
 * @property string $guard_name
 * @property-read Collection<int, Menu> $menus
 * @property-read Collection<int, Permission> $permissions
 */
class Role extends SpatieRole
{
    use HasUlids;

    protected $table = 'rbac_roles';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return BelongsToMany<Menu, $this>
     */
    public function menus(): BelongsToMany
    {
        return $this->belongsToMany(Menu::class, 'rbac_menu_roles', 'role_id', 'menu_id');
    }

    public function assignedUsersCount(): int
    {
        $connection = $this->getConnectionName() ?? DB::getDefaultConnection();

        return DB::connection($connection)
            ->table(config('permission.table_names.model_has_roles', 'rbac_model_has_roles'))
            ->where('role_id', $this->getKey())
            ->where('model_type', User::class)
            ->count();
    }
}
