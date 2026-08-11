<?php

declare(strict_types=1);

namespace Src\AcademicRisk\RiskBoard\Domain\ValueObjects;

/**
 * The teacher directory the board needs to turn a teacher id into
 * something a coordinator can act on. No workload figure here on
 * purpose: the accumulated workload is derived from the group snapshots
 * inside RiskEvaluator, so the "sum per teacher per term" rule stays in
 * the domain instead of being smuggled in as a pre-computed SQL column.
 */
final readonly class TeacherSnapshot
{
    public function __construct(
        public int $id,
        public string $identityCard,
        public string $name,
    ) {}
}
