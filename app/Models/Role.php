<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int    $id
 * @property string $name        Slug (unique, immutable for system roles)
 * @property string $label       Display label
 * @property string|null $description
 * @property bool   $is_system   true = cannot be deleted
 */
class Role extends Model
{
    protected $table = 'rbac_roles';

    protected $fillable = [
        'name',
        'label',
        'description',
        'is_system',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    /** @return BelongsToMany<Permission, $this> */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            Permission::class,
            'rbac_role_has_permissions',
            'role_id',
            'permission_id',
        );
    }

    /** @return HasMany<Permission, $this> */
    public function menuBoundPermissions(): HasMany
    {
        return $this->hasMany(Permission::class)->whereNotNull('menu_id');
    }

    /** @return HasMany<Permission, $this> */
    public function globalPermissions(): HasMany
    {
        return $this->hasMany(Permission::class)->whereNull('menu_id');
    }

    public function givePermissions(array $permissionIds): void
    {
        $this->permissions()->syncWithoutDetaching($permissionIds);
    }

    public function syncPermissions(array $permissionIds): void
    {
        $this->permissions()->sync($permissionIds);
    }
}
