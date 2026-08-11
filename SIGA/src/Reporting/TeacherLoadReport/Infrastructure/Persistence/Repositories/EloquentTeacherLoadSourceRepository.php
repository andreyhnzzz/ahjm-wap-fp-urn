<?php

declare(strict_types=1);

namespace Src\Reporting\TeacherLoadReport\Infrastructure\Persistence\Repositories;

use App\Models\Group as GroupModel;
use App\Models\Teacher as TeacherModel;
use Illuminate\Database\Eloquent\Collection;
use Src\Academic\Group\Domain\ValueObjects\GroupStatus;
use Src\Academic\Group\Domain\ValueObjects\Modality;
use Src\Reporting\TeacherLoadReport\Domain\Contracts\TeacherLoadSourceInterface;
use Src\Reporting\TeacherLoadReport\Domain\ValueObjects\TeacherLoadRow;
use Src\Reporting\TeacherLoadReport\Domain\ValueObjects\TeacherReference;

final class EloquentTeacherLoadSourceRepository implements TeacherLoadSourceInterface
{
    public function availableTeachers(): array
    {
        /** @var Collection<int, TeacherModel> $models */
        $models = TeacherModel::query()->orderBy('name')->get();

        return $models->map($this->toReference(...))->all();
    }

    public function findTeacher(int $teacherId): ?TeacherReference
    {
        $model = TeacherModel::query()->find($teacherId);

        return $model ? $this->toReference($model) : null;
    }

    public function rowsFor(int $teacherId, string $term): array
    {
        /** @var Collection<int, GroupModel> $models */
        $models = GroupModel::query()
            ->with('classroom')
            ->where('teacher_id', $teacherId)
            ->where('term', $term)
            ->orderBy('course_code')
            ->orderBy('code')
            ->get();

        return $models
            ->map(static fn (GroupModel $model): TeacherLoadRow => new TeacherLoadRow(
                groupCode: $model->code,
                courseCode: $model->course_code,
                classroomName: $model->classroom?->name,
                modality: Modality::from($model->modality),
                status: GroupStatus::from($model->status),
                estimatedEnrollment: $model->estimated_enrollment,
                assignedWorkload: $model->assigned_workload,
            ))
            ->all();
    }

    public function availableTerms(): array
    {
        /** @var array<int, string> $terms */
        $terms = GroupModel::query()
            ->select('term')
            ->distinct()
            ->orderByDesc('term')
            ->pluck('term')
            ->all();

        return $terms;
    }

    private function toReference(TeacherModel $model): TeacherReference
    {
        return new TeacherReference(
            id: $model->id,
            identityCard: $model->identity_card,
            name: $model->name,
            referenceWorkload: $model->reference_workload,
        );
    }
}
