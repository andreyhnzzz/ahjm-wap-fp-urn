<?php

declare(strict_types=1);

namespace Src\Reporting\OfferReport\Infrastructure\Persistence\Repositories;

use App\Models\Group as GroupModel;
use Illuminate\Database\Eloquent\Collection;
use Src\Academic\Group\Domain\ValueObjects\GroupStatus;
use Src\Academic\Group\Domain\ValueObjects\Modality;
use Src\Reporting\OfferReport\Domain\Contracts\OfferSourceInterface;
use Src\Reporting\OfferReport\Domain\ValueObjects\OfferRow;

/**
 * Adapter that reads the academic tables and speaks back in the
 * Reporting context's own Value Objects.
 *
 * `with(['teacher', 'classroom'])` is what keeps RE-01 inside its 30
 * second budget: without it, a term with 33 groups would fire 67
 * queries (the classic N+1) instead of 3, and the generation time would
 * grow with the size of the offer rather than staying flat.
 */
final class EloquentOfferSourceRepository implements OfferSourceInterface
{
    public function rowsForTerm(string $term): array
    {
        /** @var Collection<int, GroupModel> $models */
        $models = GroupModel::query()
            ->with(['teacher', 'classroom'])
            ->where('term', $term)
            ->orderBy('course_code')
            ->orderBy('code')
            ->get();

        return $models
            ->map(static fn (GroupModel $model): OfferRow => new OfferRow(
                groupId: $model->id,
                groupCode: $model->code,
                courseCode: $model->course_code,
                teacherName: $model->teacher?->name,
                classroomName: $model->classroom?->name,
                modality: Modality::from($model->modality),
                status: GroupStatus::from($model->status),
                estimatedEnrollment: $model->estimated_enrollment,
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
}
