<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

/**
 * @property string $id  UUID, primary key managed by stancl/tenancy
 * @property string $name
 * @property string $slug
 * @property bool   $is_active
 * @property string|null $contact_email
 *
 * Virtual columns (stored in JSON `data`):
 * @property array<string, string>|null $theme_config  CSS variable values: primary, accent, radius
 * @property array<string, string>|null $terminology   Custom label map (e.g. "employee" => "Pegawai")
 * @property string|null $logo_path
 * @property string|null $favicon_path
 * @property string|null $db_name  Tenant database name, resolved dynamically by HasDatabase
 */
class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains;

    protected $table = 'sys_tenants';

    /**
     * @return array<int, string>
     */
    public static function getCustomColumns(): array
    {
        return [
            'id',
            'name',
            'slug',
            'is_active',
            'contact_email',
        ];
    }

    /**
     * @return HasMany<Domain, $this>
     */
    public function domains(): HasMany
    {
        return $this->hasMany(Domain::class, 'tenant_id');
    }

    protected function casts(): array
    {
        return [
            'is_active'    => 'boolean',
            'theme_config' => 'array',
            'terminology'  => 'array',
        ];
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'sys_tenant_user')
            ->withPivot(['is_active', 'last_accessed_at'])
            ->withTimestamps();
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function activePivotUsers(): BelongsToMany
    {
        return $this->users()->wherePivot('is_active', true);
    }

    /**
     * @return HasMany<TenantUser, $this>
     */
    public function tenantUsers(): HasMany
    {
        return $this->hasMany(TenantUser::class);
    }
}
