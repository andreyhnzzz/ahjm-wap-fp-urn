<?php

declare(strict_types=1);

namespace Src\AcademicRisk\RiskBoard\Infrastructure\Persistence\Repositories;

use App\Models\Group as GroupModel;
use App\Models\Teacher as TeacherModel;
use Illuminate\Database\Eloquent\Collection;
use Src\Academic\Group\Domain\ValueObjects\GroupStatus;
use Src\AcademicRisk\RiskBoard\Domain\Contracts\RiskSourceInterface;
use Src\AcademicRisk\RiskBoard\Domain\ValueObjects\GroupSnapshot;
use Src\AcademicRisk\RiskBoard\Domain\ValueObjects\TeacherSnapshot;

/**
 * Adapter that turns the academic tables into this context's snapshots.
 *
 * It reads App\Models directly rather than going through the Academic
 * context's repositories, and that is the correct direction of coupling:
 * Eloquent models are shared infrastructure, so depending on them costs
 * nothing architecturally, whereas depending on Academic's *domain*
 * would tie two bounded contexts together at their most volatile point.
 * The single Academic import here is GroupStatus — reading the status
 * column requires knowing what its values mean, and re-declaring a
 * parallel copy of that enum would be a duplication guaranteed to drift.
 *
 * Both queries select only the columns the snapshots need, so this stays
 * a narrow read even once the groups table grows extra fields.
 */
final class EloquentRiskSourceRepository implements RiskSourceInterface
{
    public function groupSnapshots(): array
    {
        /** @var Collection<int, GroupModel> $models */
        $models = GroupModel::query()
            ->select(['id', 'code', 'term', 'teacher_id', 'classroom_id', 'estimated_enrollment', 'assigned_workload', 'status'])
            ->orderBy('code')
            ->get();

        return $models
            ->map(static fn (GroupModel $model): GroupSnapshot => new GroupSnapshot(
                id: $model->id,
                code: $model->code,
                term: $model->term,
                teacherId: $model->teacher_id,
                hasClassroom: $model->classroom_id !== null,
                estimatedEnrollment: $model->estimated_enrollment,
                assignedWorkload: $model->assigned_workload,
                isActive: GroupStatus::from($model->status)->isActive(),
            ))
            ->all();
    }

    public function teacherSnapshots(): array
    {
        /** @var Collection<int, TeacherModel> $models */
        $models = TeacherModel::query()
            ->select(['id', 'identity_card', 'name'])
            ->orderBy('name')
            ->get();

        return $models
            ->map(static fn (TeacherModel $model): TeacherSnapshot => new TeacherSnapshot(
                id: $model->id,
                identityCard: $model->identity_card,
                name: $model->name,
            ))
            ->all();
    }
}
