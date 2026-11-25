<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int         $id
 * @property string      $key          System key (immutable)
 * @property string      $label        Default system label
 * @property string|null $custom_label Tenant-customized label
 * @property string|null $icon         Lucide icon name
 * @property string|null $route_name   Named Laravel route
 * @property int|null    $parent_id
 * @property int         $sort_order
 * @property bool        $is_visible
 */
class Menu extends Model
{
    protected $table = 'rbac_menus';

    protected $fillable = [
        'key',
        'label',
        'custom_label',
        'icon',
        'route_name',
        'parent_id',
        'sort_order',
        'is_visible',
    ];

    protected function casts(): array
    {
        return [
            'is_visible'  => 'boolean',
            'sort_order'  => 'integer',
        ];
    }

    /** @return BelongsTo<Menu, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Menu::class, 'parent_id');
    }

    /** @return HasMany<Menu, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(Menu::class, 'parent_id')->orderBy('sort_order');
    }

    /** @return HasMany<Permission, $this> */
    public function permissions(): HasMany
    {
        return $this->hasMany(Permission::class, 'menu_id');
    }

    /** Resolved display label — custom_label takes precedence over label */
    public function getDisplayLabelAttribute(): string
    {
        return $this->custom_label ?? $this->label;
    }
}
