<?php

declare(strict_types=1);

namespace Src\Academic\Group\Infrastructure\Persistence\Repositories;

use App\Models\Group as GroupModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Src\Academic\Group\Domain\Contracts\GroupRepositoryInterface;
use Src\Academic\Group\Domain\Entities\Group;
use Src\Academic\Group\Domain\ValueObjects\GroupStatus;
use Src\Academic\Group\Domain\ValueObjects\Modality;

final class EloquentGroupRepository implements GroupRepositoryInterface
{
    /**
     * @var array<int, string>
     */
    private const SORTABLE_COLUMNS = ['code', 'course_code', 'term', 'estimated_enrollment', 'assigned_workload', 'status', 'modality'];

    /**
     * @var array<int, string>
     */
    private const SEARCHABLE_COLUMNS = ['code', 'course_code', 'term'];

    public function find(int $id): ?Group
    {
        $model = GroupModel::query()->find($id);

        return $model ? $this->toDomain($model) : null;
    }

    public function all(?string $search = null, ?string $sortBy = null, string $sortDir = 'asc'): array
    {
        /** @var Collection<int, GroupModel> $models */
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

    public function save(Group $group): Group
    {
        $model = $group->id()
            ? GroupModel::query()->findOrFail($group->id())
            : new GroupModel;

        $model->code = $group->code();
        $model->course_code = $group->courseCode();
        $model->term = $group->term();
        $model->teacher_id = $group->teacherId();
        $model->classroom_id = $group->classroomId();
        $model->estimated_enrollment = $group->estimatedEnrollment();
        $model->assigned_workload = $group->assignedWorkload();
        $model->modality = $group->modality()->value;
        $model->status = $group->status()->value;
        $model->save();

        return $this->toDomain($model);
    }

    public function delete(int $id): void
    {
        GroupModel::query()->whereKey($id)->delete();
    }

    /**
     * @return Builder<GroupModel>
     */
    private function baseQuery(?string $search, ?string $sortBy, string $sortDir): Builder
    {
        $query = GroupModel::query();

        if (filled($search)) {
            $query->whereAny(self::SEARCHABLE_COLUMNS, 'like', "%{$search}%");
        }

        return $query
            ->orderBy($this->sortColumn($sortBy), $sortDir === 'desc' ? 'desc' : 'asc')
            // Deterministic tie-breaker: several groups share a term or a
            // course code, and without this the order of equal keys is
            // whatever the driver felt like, which makes the table appear
            // to reshuffle itself between renders.
            ->orderBy('code');
    }

    private function toDomain(GroupModel $model): Group
    {
        return Group::reconstitute(
            id: $model->id,
            code: $model->code,
            courseCode: $model->course_code,
            term: $model->term,
            teacherId: $model->teacher_id,
            classroomId: $model->classroom_id,
            estimatedEnrollment: $model->estimated_enrollment,
            assignedWorkload: $model->assigned_workload,
            // from() rather than tryFrom(): a value outside the enum means
            // the table was written by something that bypassed the domain,
            // and failing loudly here beats rendering a silently wrong
            // offer report.
            modality: Modality::from($model->modality),
            status: GroupStatus::from($model->status),
        );
    }

    private function sortColumn(?string $sortBy): string
    {
        $column = match ($sortBy) {
            'courseCode' => 'course_code',
            'estimatedEnrollment' => 'estimated_enrollment',
            'assignedWorkload' => 'assigned_workload',
            default => $sortBy,
        };

        return in_array($column, self::SORTABLE_COLUMNS, true) ? $column : 'code';
    }
}
