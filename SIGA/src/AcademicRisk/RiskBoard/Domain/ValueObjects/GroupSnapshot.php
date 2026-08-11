<?php

declare(strict_types=1);

namespace Src\AcademicRisk\RiskBoard\Domain\ValueObjects;

/**
 * What the risk board needs to know about one group — and nothing else.
 *
 * This is an anti-corruption boundary, not a convenience: the board does
 * NOT import Src\Academic\Group\Domain\Entities\Group. If it did, the
 * two contexts would be welded together and every change to the Group
 * aggregate (a new field, a renamed method, a new invariant) would ripple
 * into the risk rules. Instead AcademicRisk declares the shape it needs,
 * and its own Infrastructure adapter is responsible for producing it.
 *
 * `isActive` travels with the snapshot rather than being filtered out in
 * SQL on purpose: "a cancelled group is not a risk" is a business rule,
 * and business rules belong in the domain where they can be unit tested,
 * not in a WHERE clause.
 */
final readonly class GroupSnapshot
{
    public function __construct(
        public int $id,
        public string $code,
        public string $term,
        public ?int $teacherId,
        public bool $hasClassroom,
        public int $estimatedEnrollment,
        public float $assignedWorkload,
        public bool $isActive,
    ) {}

    public function hasTeacher(): bool
    {
        return $this->teacherId !== null;
    }
}
