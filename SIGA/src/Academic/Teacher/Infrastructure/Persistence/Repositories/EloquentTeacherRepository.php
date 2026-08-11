<?php

declare(strict_types=1);

namespace Src\Academic\Teacher\Infrastructure\Persistence\Repositories;

use App\Models\Teacher as TeacherModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Src\Academic\Teacher\Domain\Contracts\TeacherRepositoryInterface;
use Src\Academic\Teacher\Domain\Entities\Teacher;

/**
 * The only class in this module allowed to know Eloquent exists. It
 * translates rows into Domain Entities in one direction and Entities
 * into rows in the other; nothing above the port can tell which ORM —
 * or whether an ORM at all — is behind it.
 */
final class EloquentTeacherRepository implements TeacherRepositoryInterface
{
    /**
     * Explicit allow-list — $sortBy ends up in raw SQL through orderBy()
     * and Livewire action arguments are client-controllable, so this is
     * not optional hardening.
     *
     * @var array<int, string>
     */
    private const SORTABLE_COLUMNS = ['name', 'identity_card', 'reference_workload'];

    /**
     * Columns a free-text search scans.
     *
     * @var array<int, string>
     */
    private const SEARCHABLE_COLUMNS = ['name', 'identity_card'];

    public function find(int $id): ?Teacher
    {
        $model = TeacherModel::query()->find($id);

        return $model ? $this->toDomain($model) : null;
    }

    public function all(?string $search = null, ?string $sortBy = null, string $sortDir = 'asc'): array
    {
        /** @var Collection<int, TeacherModel> $models */
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

    public function save(Teacher $teacher): Teacher
    {
        $model = $teacher->id()
            ? TeacherModel::query()->findOrFail($teacher->id())
            : new TeacherModel;

        $model->identity_card = $teacher->identityCard();
        $model->name = $teacher->name();
        $model->reference_workload = $teacher->referenceWorkload();
        $model->save();

        return $this->toDomain($model);
    }

    public function delete(int $id): void
    {
        TeacherModel::query()->whereKey($id)->delete();
    }

    /**
     * One place builds the filtered + sorted query, so `all()` and
     * `paginate()` can never drift apart on what "search" means.
     *
     * @return Builder<TeacherModel>
     */
    private function baseQuery(?string $search, ?string $sortBy, string $sortDir): Builder
    {
        $query = TeacherModel::query();

        if (filled($search)) {
            // whereAny() groups the OR conditions in their own parentheses,
            // so adding any further filter later cannot silently widen the
            // result set the way a bare where()->orWhere() chain would.
            $query->whereAny(self::SEARCHABLE_COLUMNS, 'like', "%{$search}%");
        }

        return $query->orderBy($this->sortColumn($sortBy), $this->sortDirection($sortDir));
    }

    private function toDomain(TeacherModel $model): Teacher
    {
        return Teacher::reconstitute(
            id: $model->id,
            identityCard: $model->identity_card,
            name: $model->name,
            referenceWorkload: $model->reference_workload,
        );
    }

    private function sortColumn(?string $sortBy): string
    {
        // The UI sorts by the camelCase keys of the row array it renders;
        // the table stores snake_case columns. Translating here keeps that
        // mapping inside the single layer that knows columns exist at all.
        $column = match ($sortBy) {
            'identityCard' => 'identity_card',
            'referenceWorkload' => 'reference_workload',
            default => $sortBy,
        };

        return in_array($column, self::SORTABLE_COLUMNS, true) ? $column : 'name';
    }

    /**
     * The literal return type is not decoration: orderBy() accepts only
     * 'asc' or 'desc', and a plain `string` here would let any
     * client-supplied value through the type system untouched.
     *
     * @return 'asc'|'desc'
     */
    private function sortDirection(string $sortDir): string
    {
        return $sortDir === 'desc' ? 'desc' : 'asc';
    }
}
