<?php

declare(strict_types=1);

namespace Src\Academic\Classroom\Infrastructure\Persistence\Repositories;

use App\Models\Classroom as ClassroomModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Src\Academic\Classroom\Domain\Contracts\ClassroomRepositoryInterface;
use Src\Academic\Classroom\Domain\Entities\Classroom;

final class EloquentClassroomRepository implements ClassroomRepositoryInterface
{
    /**
     * $sortBy reaches raw SQL through orderBy() and Livewire action
     * arguments are client-controllable — allow-list, not optional.
     *
     * @var array<int, string>
     */
    private const SORTABLE_COLUMNS = ['name', 'capacity'];

    public function find(int $id): ?Classroom
    {
        $model = ClassroomModel::query()->find($id);

        return $model ? $this->toDomain($model) : null;
    }

    public function all(?string $search = null, ?string $sortBy = null, string $sortDir = 'asc'): array
    {
        /** @var Collection<int, ClassroomModel> $models */
        $models = $this->baseQuery($search, $sortBy, $sortDir)->get();

        return $models->map($this->toDomain(...))->all();
    }

    public function paginate(?string $search, int $perPage, int $page, ?string $sortBy = null, string $sortDir = 'asc'): array
    {
        $paginator = $this->baseQuery($search, $sortBy, $sortDir)->paginate(perPage: $perPage, page: $page);

        return [
            'items' => array_map($this->toDomain(...), $paginator->items()),
            'total' => $paginator->total(),
        ];
    }

    public function save(Classroom $classroom): Classroom
    {
        $model = $classroom->id()
            ? ClassroomModel::query()->findOrFail($classroom->id())
            : new ClassroomModel;

        $model->name = $classroom->name();
        $model->capacity = $classroom->capacity();
        $model->save();

        return $this->toDomain($model);
    }

    public function delete(int $id): void
    {
        ClassroomModel::query()->whereKey($id)->delete();
    }

    /**
     * @return Builder<ClassroomModel>
     */
    private function baseQuery(?string $search, ?string $sortBy, string $sortDir): Builder
    {
        $query = ClassroomModel::query();

        if (filled($search)) {
            $query->where('name', 'like', "%{$search}%");
        }

        $column = in_array($sortBy, self::SORTABLE_COLUMNS, true) ? $sortBy : 'name';
        $direction = $sortDir === 'desc' ? 'desc' : 'asc';

        return $query->orderBy($column, $direction);
    }

    private function toDomain(ClassroomModel $model): Classroom
    {
        return Classroom::reconstitute(
            id: $model->id,
            name: $model->name,
            capacity: $model->capacity,
        );
    }
}
