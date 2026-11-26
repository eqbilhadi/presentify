<?php

declare(strict_types=1);

namespace App\Services\Rbac;

use App\Models\Permission;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

class PermissionManagementService
{
    public function __construct(
        private readonly PermissionRegistrar $permissionRegistrar,
    ) {}

    /**
     * @return LengthAwarePaginator<int, Permission>
     */
    public function paginateStandalone(?string $search = null, ?string $group = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = Permission::query()
            ->standalone()
            ->orderByRaw('COALESCE("group", \'\') asc')
            ->orderBy('name');

        if ($search !== null && trim($search) !== '') {
            $term = '%'.strtolower(trim($search)).'%';
            $query->where(function ($q) use ($term): void {
                $q->whereRaw('LOWER(name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(COALESCE("group", \'\')) LIKE ?', [$term]);
            });
        }

        if ($group !== null && trim($group) !== '') {
            $query->where('group', trim($group));
        }

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * @return array<int, string>
     */
    public function listGroups(): array
    {
        return Permission::query()
            ->standalone()
            ->whereNotNull('group')
            ->distinct()
            ->orderBy('group')
            ->pluck('group')
            ->filter(static fn (mixed $value): bool => is_string($value) && $value !== '')
            ->values()
            ->all();
    }

    /**
     * @param  array{name: string, group?: string|null}  $data
     */
    public function create(array $data): Permission
    {
        $permission = Permission::query()->create([
            'name' => trim($data['name']),
            'guard_name' => 'web',
            'group' => isset($data['group']) && trim((string) $data['group']) !== ''
                ? trim((string) $data['group'])
                : null,
        ]);

        $this->permissionRegistrar->forgetCachedPermissions();

        return $permission;
    }

    /**
     * @param  array{name: string, group?: string|null}  $data
     */
    public function update(Permission $permission, array $data): Permission
    {
        $this->assertStandalone($permission);

        $permission->update([
            'name' => trim($data['name']),
            'group' => isset($data['group']) && trim((string) $data['group']) !== ''
                ? trim((string) $data['group'])
                : null,
        ]);

        $this->permissionRegistrar->forgetCachedPermissions();

        return $permission->refresh();
    }

    public function delete(Permission $permission): void
    {
        $this->assertStandalone($permission);

        DB::transaction(function () use ($permission): void {
            $permission->delete();
        });

        $this->permissionRegistrar->forgetCachedPermissions();
    }

    private function assertStandalone(Permission $permission): void
    {
        if ($permission->menuAction()->exists()) {
            throw ValidationException::withMessages([
                'permission' => 'Izin ini terikat pada menu action. Kelola melalui tab Menu Actions.',
            ]);
        }
    }
}
