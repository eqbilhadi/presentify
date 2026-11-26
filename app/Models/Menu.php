<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string|null $parent_id
 * @property int $sort_num
 * @property string|null $icon
 * @property string $label_name
 * @property string|null $controller_name
 * @property string|null $route_name
 * @property string|null $url
 * @property string|null $permission_prefix
 * @property bool $is_active
 * @property bool $is_divider
 * @property-read Menu|null $parent
 * @property-read Collection<int, Menu> $children
 * @property-read Collection<int, Menu> $activeChildren
 * @property-read Collection<int, MenuAction> $actions
 * @property-read Collection<int, Role> $roles
 */
class Menu extends Model
{
    use HasUlids;

    protected $table = 'rbac_menus';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'parent_id',
        'sort_num',
        'icon',
        'label_name',
        'controller_name',
        'route_name',
        'url',
        'permission_prefix',
        'is_active',
        'is_divider',
    ];

    protected function casts(): array
    {
        return [
            'sort_num' => 'integer',
            'is_active' => 'boolean',
            'is_divider' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Menu, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Menu::class, 'parent_id');
    }

    /**
     * @return HasMany<Menu, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Menu::class, 'parent_id')->orderBy('sort_num');
    }

    /**
     * @return HasMany<Menu, $this>
     */
    public function activeChildren(): HasMany
    {
        return $this->hasMany(Menu::class, 'parent_id')
            ->where('is_active', true)
            ->orderBy('sort_num');
    }

    /**
     * @return HasMany<MenuAction, $this>
     */
    public function actions(): HasMany
    {
        return $this->hasMany(MenuAction::class, 'menu_id')->orderBy('order');
    }

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'rbac_menu_roles', 'menu_id', 'role_id');
    }
}
