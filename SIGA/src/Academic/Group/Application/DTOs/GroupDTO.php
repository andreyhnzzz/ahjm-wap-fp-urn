<?php

declare(strict_types=1);

namespace Src\Academic\Group\Application\DTOs;

use Src\Academic\Group\Domain\ValueObjects\GroupStatus;
use Src\Academic\Group\Domain\ValueObjects\Modality;

/**
 * Immutable data boundary between Presentation and Application.
 *
 * Modality and GroupStatus travel as their enums, not as raw strings:
 * they are Value Objects, not entities, so nothing leaks by carrying
 * them, and the conversion from the `<select>`'s string happens exactly
 * once — at the Presentation edge (GroupForm::toDto()). Past that point
 * an unknown modality is unrepresentable rather than merely unlikely.
 */
final readonly class GroupDTO
{
    public function __construct(
        public string $code,
        public string $courseCode,
        public string $term,
        public ?int $teacherId,
        public ?int $classroomId,
        public int $estimatedEnrollment,
        public float $assignedWorkload,
        public Modality $modality,
        public GroupStatus $status,
    ) {}
}
