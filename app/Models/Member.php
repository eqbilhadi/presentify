<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $global_user_id References central DB users.id
 * @property string|null $nip
 * @property string $name
 * @property string|null $position
 * @property int|null $unit_id
 * @property string $status active | inactive | suspended
 * @property array<string, mixed>|null $meta
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

    /** @param Builder<Member> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', 'active');
    }

    public static function findActiveForUser(User|int $user): ?self
    {
        $userId = $user instanceof User ? $user->id : $user;

        return self::query()
            ->where('global_user_id', $userId)
            ->active()
            ->first();
    }
}
