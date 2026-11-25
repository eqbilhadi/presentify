<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $name Slug (unique, immutable for system roles)
 * @property string $label Display label
 * @property string|null $description
 * @property bool $is_system true = cannot be deleted
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

    /** @return BelongsToMany<Permission, $this> */
    public function menuBoundPermissions(): BelongsToMany
    {
        return $this->permissions()->whereNotNull('menu_id');
    }

    /** @return BelongsToMany<Permission, $this> */
    public function globalPermissions(): BelongsToMany
    {
        return $this->permissions()->whereNull('menu_id');
    }

    /** @return BelongsToMany<Member, $this> */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(
            Member::class,
            'rbac_model_has_roles',
            'role_id',
            'model_id',
        )->wherePivot('model_type', Member::class);
    }

    /** @param array<int, int> $permissionIds */
    public function givePermissions(array $permissionIds): void
    {
        $this->permissions()->syncWithoutDetaching($permissionIds);
    }

    /** @param array<int, int> $permissionIds */
    public function syncPermissions(array $permissionIds): void
    {
        $this->permissions()->sync($permissionIds);
    }
}
