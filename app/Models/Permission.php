<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int         $id
 * @property string      $name      Slug permission
 * @property string      $label     Display label
 * @property int|null    $menu_id   NULL = global (Tier-2), set = menu-bound (Tier-1)
 * @property string      $action    view|create|edit|delete|export|import|override|bypass
 */
class Permission extends Model
{
    protected $table = 'rbac_permissions';

    protected $fillable = [
        'name',
        'label',
        'menu_id',
        'action',
    ];

    protected function casts(): array
    {
        return [
            'menu_id' => 'integer',
        ];
    }

    /** @return BelongsTo<Menu, $this> */
    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class, 'menu_id');
    }

    /** @return BelongsToMany<Role, $this> */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            Role::class,
            'rbac_role_has_permissions',
            'permission_id',
            'role_id',
        );
    }

    public function isGlobal(): bool
    {
        return $this->menu_id === null;
    }

    public function isMenuBound(): bool
    {
        return $this->menu_id !== null;
    }
}
