<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Permission\Infrastructure\Persistence\Repositories;

use App\Models\Permission as PermissionModel;
use Src\IdentityAccess\Permission\Domain\Contracts\PermissionRepositoryInterface;
use Src\IdentityAccess\Permission\Domain\Entities\Permission;

final class EloquentPermissionRepository implements PermissionRepositoryInterface
{
    /** @var array<int, string> */
    private const SORTABLE_COLUMNS = ['module', 'action'];

    public function find(int $id): ?Permission
    {
        $model = PermissionModel::query()->find($id);

        return $model ? $this->toDomain($model) : null;
    }

    public function all(?string $search = null, ?string $sortBy = null, string $sortDir = 'asc'): array
    {
        $query = PermissionModel::query();

        if (filled($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('action', 'like', "%{$search}%");
            });
        }

        $column = in_array($sortBy, self::SORTABLE_COLUMNS, true) ? $sortBy : 'module';
        $direction = $sortDir === 'desc' ? 'desc' : 'asc';

        return $query
            ->orderBy($column, $direction)
            ->orderBy('action')
            ->get()
            ->map($this->toDomain(...))
            ->all();
    }

    public function paginate(?string $search, ?string $module, int $perPage, int $page, ?string $sortBy = null, string $sortDir = 'asc'): array
    {
        $query = PermissionModel::query();

        if (filled($module)) {
            $query->where('module', $module);
        }

        if (filled($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('action', 'like', "%{$search}%");
            });
        }

        $column = in_array($sortBy, self::SORTABLE_COLUMNS, true) ? $sortBy : 'module';
        $direction = $sortDir === 'desc' ? 'desc' : 'asc';

        $paginator = $query->orderBy($column, $direction)->orderBy('action')->paginate(perPage: $perPage, page: $page);

        return [
            'items' => array_map($this->toDomain(...), $paginator->items()),
            'total' => $paginator->total(),
        ];
    }

    public function save(Permission $permission): Permission
    {
        $model = $permission->id()
            ? PermissionModel::query()->findOrFail($permission->id())
            : new PermissionModel();

        $model->module = $permission->module();
        $model->action = $permission->action();
        $model->name = $permission->name();
        $model->save();

        return $this->toDomain($model);
    }

    public function delete(int $id): void
    {
        PermissionModel::query()->whereKey($id)->delete();
    }

    private function toDomain(PermissionModel $model): Permission
    {
        return Permission::reconstitute($model->id, $model->module, $model->action);
    }
}
