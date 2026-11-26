<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $menu_id
 * @property string $action
 * @property string $label
 * @property string $permission_name
 * @property string|null $route_name
 * @property int $order
 * @property-read Menu $menu
 * @property-read Permission|null $permission
 */
class MenuAction extends Model
{
    use HasUlids;

    protected $table = 'rbac_menu_actions';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'menu_id',
        'action',
        'label',
        'permission_name',
        'route_name',
        'order',
    ];

    protected function casts(): array
    {
        return [
            'order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Menu, $this>
     */
    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class, 'menu_id');
    }

    /**
     * @return BelongsTo<Permission, $this>
     */
    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class, 'permission_name', 'name');
    }
}
