<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int         $id
 * @property int         $global_user_id  References central DB users.id
 * @property string|null $nip
 * @property string      $name
 * @property string|null $position
 * @property int|null    $unit_id
 * @property string      $status          active | inactive | suspended
 * @property array|null  $meta
 */
class Member extends Model
{
    use SoftDeletes;

    protected $table = 'org_members';

    protected $fillable = [
        'global_user_id',
        'nip',
        'name',
        'position',
        'unit_id',
        'status',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    /** @return BelongsTo<\App\Models\Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    /** @return BelongsToMany<Role, $this> */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            Role::class,
            'rbac_model_has_roles',
            'model_id',
            'role_id',
        )->wherePivot('model_type', self::class);
    }

    public function hasRole(string $roleName): bool
    {
        return $this->roles()->where('name', $roleName)->exists();
    }

    public function hasPermission(string $permissionName): bool
    {
        return $this->roles()
            ->whereHas('permissions', fn ($q) => $q->where('name', $permissionName))
            ->exists();
    }
}
