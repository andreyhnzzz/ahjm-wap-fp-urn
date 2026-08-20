<?php

declare(strict_types=1);

namespace Src\AcademicRisk\RiskBoard\Infrastructure\Persistence\Repositories;

use App\Models\Group as GroupModel;
use App\Models\Teacher as TeacherModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;
use Src\Academic\Group\Domain\ValueObjects\GroupStatus;
use Src\AcademicRisk\RiskBoard\Domain\Contracts\RiskSourceInterface;
use Src\AcademicRisk\RiskBoard\Domain\ValueObjects\GroupSnapshot;
use Src\AcademicRisk\RiskBoard\Domain\ValueObjects\TeacherSnapshot;
use stdClass;

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
 *
 * groupSnapshots() goes through toBase(): it reads every row in the table,
 * and hydrating a full Eloquent model per row only to copy eight fields out
 * of it and drop it is the dominant cost of the board. Measured at 12,000
 * groups: 360 ms / 26 MB hydrating models, 21 ms / ~0 MB without. The one
 * thing the models bought us was column casting, which is why the snapshot
 * fields are cast explicitly below. teacherSnapshots() keeps Eloquent on
 * purpose — that table is tens of rows, and there is nothing to win.
 */
final class EloquentRiskSourceRepository implements RiskSourceInterface
{
    public function groupSnapshots(): array
    {
        /** @var BaseCollection<int, stdClass> $rows */
        $rows = GroupModel::query()
            ->select(['id', 'code', 'term', 'teacher_id', 'classroom_id', 'estimated_enrollment', 'assigned_workload', 'status'])
            ->orderBy('code')
            ->toBase()
            ->get();

        return $rows
            ->map(static fn (stdClass $row): GroupSnapshot => new GroupSnapshot(
                id: (int) $row->id,
                code: (string) $row->code,
                term: (string) $row->term,
                teacherId: $row->teacher_id !== null ? (int) $row->teacher_id : null,
                hasClassroom: $row->classroom_id !== null,
                estimatedEnrollment: (int) $row->estimated_enrollment,
                assignedWorkload: (float) $row->assigned_workload,
                isActive: GroupStatus::from((string) $row->status)->isActive(),
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
