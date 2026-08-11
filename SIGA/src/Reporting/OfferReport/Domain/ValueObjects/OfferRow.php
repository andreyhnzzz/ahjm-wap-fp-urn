<?php

declare(strict_types=1);

namespace Src\Reporting\OfferReport\Domain\ValueObjects;

use Src\Academic\Group\Domain\ValueObjects\GroupStatus;
use Src\Academic\Group\Domain\ValueObjects\Modality;

/**
 * One line of the academic offer report: exactly the columns RE-01
 * requires (group, teacher, classroom, modality and group status), plus
 * the estimated enrollment a coordinator reads alongside them.
 *
 * Teacher and classroom are nullable strings rather than "N/A" already
 * substituted in: a report row is data, and "there is no teacher" is a
 * fact about the offer, not a piece of text. What that renders as —
 * "Sin asignar" on screen, an empty cell in the spreadsheet — is the
 * caller's decision.
 *
 * Modality and GroupStatus are imported from the Academic context as a
 * deliberate Shared Kernel: a report *about* the academic offer has to
 * speak its vocabulary, and both are tiny, stable, behaviour-free
 * classifications. Re-declaring parallel copies here would guarantee the
 * two drift the day someone adds a modality — a far worse coupling than
 * sharing the enum itself.
 */
final readonly class OfferRow
{
    public function __construct(
        public int $groupId,
        public string $groupCode,
        public string $courseCode,
        public ?string $teacherName,
        public ?string $classroomName,
        public Modality $modality,
        public GroupStatus $status,
        public int $estimatedEnrollment,
    ) {}

    public function hasTeacher(): bool
    {
        return $this->teacherName !== null;
    }

    public function hasClassroom(): bool
    {
        return $this->classroomName !== null;
    }
}
