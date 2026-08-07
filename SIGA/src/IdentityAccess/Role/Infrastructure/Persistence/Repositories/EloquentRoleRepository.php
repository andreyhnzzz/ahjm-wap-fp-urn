<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Role\Infrastructure\Persistence\Repositories;

use App\Models\Permission as PermissionModel;
use App\Models\Role as RoleModel;
use Src\IdentityAccess\Role\Domain\Contracts\RoleRepositoryInterface;
use Src\IdentityAccess\Role\Domain\Entities\Role;

/**
 * Only class in this module allowed to know about Eloquent. Unknown
 * permission names passed via syncPermissions() are silently ignored on
 * sync — the Presentation layer is expected to only ever offer permission
 * names that actually exist (populated from PermissionRepositoryInterface).
 */
final class EloquentRoleRepository implements RoleRepositoryInterface
{
    public function find(int $id): ?Role
    {
        $model = RoleModel::query()->with('permissions')->find($id);

        return $model ? $this->toDomain($model) : null;
    }

    public function paginate(?string $search, int $perPage, int $page): array
    {
        $query = RoleModel::query()->with('permissions');

        if (filled($search)) {
            $query->where('name', 'like', "%{$search}%");
        }

        $paginator = $query->orderBy('name')->paginate(perPage: $perPage, page: $page);

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
