<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Role\Infrastructure\Persistence\Repositories;

use App\Models\Permission as PermissionModel;
use App\Models\Role as RoleModel;
use Src\IdentityAccess\Role\Domain\Contracts\RoleRepositoryInterface;
use Src\IdentityAccess\Role\Domain\Entities\Role;

final class EloquentRoleRepository implements RoleRepositoryInterface
{
    /**
     * Explicit allow-list — $sortBy ultimately reaches raw SQL via orderBy(),
     * and Livewire action arguments are client-controllable, so this is not
     * optional hardening.
     *
     * @var array<int, string>
     */
    private const SORTABLE_COLUMNS = ['name'];

    public function find(int $id): ?Role
    {
        $model = RoleModel::query()->with('permissions')->find($id);

        return $model ? $this->toDomain($model) : null;
    }

    public function all(?string $search = null, ?string $sortBy = null, string $sortDir = 'asc'): array
    {
        $query = RoleModel::query()->with('permissions');

        if (filled($search)) {
            $query->where('name', 'like', "%{$search}%");
        }

        $column = in_array($sortBy, self::SORTABLE_COLUMNS, true) ? $sortBy : 'name';
        $direction = $sortDir === 'desc' ? 'desc' : 'asc';

        return $query
            ->orderBy($column, $direction)
            ->get()
            ->map($this->toDomain(...))
            ->all();
    }

    public function paginate(?string $search, int $perPage, int $page, ?string $sortBy = null, string $sortDir = 'asc'): array
    {
        $query = RoleModel::query()->with('permissions');

        if (filled($search)) {
            $query->where('name', 'like', "%{$search}%");
        }

        $column = in_array($sortBy, self::SORTABLE_COLUMNS, true) ? $sortBy : 'name';
        $direction = $sortDir === 'desc' ? 'desc' : 'asc';

        $paginator = $query->orderBy($column, $direction)->paginate(perPage: $perPage, page: $page);

        return [
            'items' => array_map($this->toDomain(...), $paginator->items()),
            'total' => $paginator->total(),
        ];
    }

    public function save(Role $role): Role
    {
        $model = $role->id()
            ? RoleModel::query()->findOrFail($role->id())
            : new RoleModel();

        $model->name = $role->name();
        $model->save();

        $permissionIds = PermissionModel::query()
            ->whereIn('name', $role->permissions())
            ->pluck('id');

        $model->permissions()->sync($permissionIds);

        return $this->toDomain($model->load('permissions'));
    }

    public function delete(int $id): void
    {
        RoleModel::query()->whereKey($id)->delete();
    }

    private function toDomain(RoleModel $model): Role
    {
        return Role::reconstitute(
            id: $model->id,
            name: $model->name,
            permissions: $model->permissions->pluck('name')->all(),
        );
    }
}
