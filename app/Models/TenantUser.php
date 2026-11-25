<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * @property int         $id
 * @property string      $tenant_id  UUID, matches sys_tenants.id
 * @property int         $user_id
 * @property bool        $is_active
 * @property Carbon|null $last_accessed_at
 */
class TenantUser extends Model
{
    use CentralConnection;

    protected $table = 'sys_tenant_user';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'is_active',
        'last_accessed_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active'        => 'boolean',
            'last_accessed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
