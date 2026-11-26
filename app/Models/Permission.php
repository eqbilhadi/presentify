<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * @property string $id
 * @property string $name
 * @property string $guard_name
 * @property string|null $group
 * @property-read MenuAction|null $menuAction
 */
class Permission extends SpatiePermission
{
    use HasUlids;

    protected $table = 'rbac_permissions';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return HasOne<MenuAction, $this>
     */
    public function menuAction(): HasOne
    {
        return $this->hasOne(MenuAction::class, 'permission_name', 'name');
    }

    /**
     * @param  Builder<Permission>  $query
     * @return Builder<Permission>
     */
    public function scopeStandalone(Builder $query): Builder
    {
        return $query->doesntHave('menuAction');
    }
}
