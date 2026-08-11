<?php

declare(strict_types=1);

namespace Src\Reporting\TeacherLoadReport\Domain\ValueObjects;

use Src\Academic\Group\Domain\ValueObjects\GroupStatus;
use Src\Academic\Group\Domain\ValueObjects\Modality;

/**
 * One group of a teacher's term, as RE-02 lists it. Shared Kernel note
 * on Modality/GroupStatus: see OfferRow — a report about the academic
 * offer speaks the offer's vocabulary rather than re-declaring it.
 */
final readonly class TeacherLoadRow
{
    public function __construct(
        public string $groupCode,
        public string $courseCode,
        public ?string $classroomName,
        public Modality $modality,
        public GroupStatus $status,
        public int $estimatedEnrollment,
        public float $assignedWorkload,
    ) {}
}
