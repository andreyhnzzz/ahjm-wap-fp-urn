<?php

declare(strict_types=1);

namespace Src\Reporting\OfferReport\Infrastructure\Persistence\Repositories;

use App\Models\Group as GroupModel;
use Illuminate\Support\Collection;
use Src\Academic\Group\Domain\ValueObjects\GroupStatus;
use Src\Academic\Group\Domain\ValueObjects\Modality;
use Src\Reporting\OfferReport\Domain\Contracts\OfferSourceInterface;
use Src\Reporting\OfferReport\Domain\ValueObjects\OfferRow;
use stdClass;

/**
 * Adapter that reads the academic tables and speaks back in the
 * Reporting context's own Value Objects.
 *
 * The two left joins are what keep RE-01 inside its 30 second budget:
 * read row by row, a term with 33 groups would fire 67 queries (the
 * classic N+1) instead of 1, and the generation time would grow with the
 * size of the offer rather than staying flat.
 *
 * They replace an eager load (`with(['teacher', 'classroom'])`), which
 * solved the N+1 just as well but still built a full Eloquent model per
 * group in order to read two names off it and drop it. Only the two names
 * are ever used, so the join hands them over already flattened and the
 * query returns plain rows. Measured on a term with 11 992 groups:
 * 575 ms / 36 MB eager loading, 35 ms / 4 MB joined — and the OfferRow
 * list that comes out compares identical.
 *
 * Casting is explicit because plain rows arrive untyped from the driver;
 * that is the one thing the discarded models were still doing for us.
 */
final class EloquentOfferSourceRepository implements OfferSourceInterface
{
    public function rowsForTerm(string $term): array
    {
        /** @var Collection<int, stdClass> $rows */
        $rows = GroupModel::query()
            ->leftJoin('teachers', 'teachers.id', '=', 'groups.teacher_id')
            ->leftJoin('classrooms', 'classrooms.id', '=', 'groups.classroom_id')
            ->where('groups.term', $term)
            ->orderBy('groups.course_code')
            ->orderBy('groups.code')
            ->select([
                'groups.id',
                'groups.code',
                'groups.course_code',
                'groups.modality',
                'groups.status',
                'groups.estimated_enrollment',
                'teachers.name as teacher_name',
                'classrooms.name as classroom_name',
            ])
            ->toBase()
            ->get();

        return $rows
            ->map(static fn (stdClass $row): OfferRow => new OfferRow(
                groupId: (int) $row->id,
                groupCode: (string) $row->code,
                courseCode: (string) $row->course_code,
                teacherName: $row->teacher_name,
                classroomName: $row->classroom_name,
                modality: Modality::from((string) $row->modality),
                status: GroupStatus::from((string) $row->status),
                estimatedEnrollment: (int) $row->estimated_enrollment,
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
