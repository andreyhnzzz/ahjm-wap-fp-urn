<?php

declare(strict_types=1);

namespace Src\AcademicRisk\RiskBoard\Domain\Contracts;

use Src\AcademicRisk\RiskBoard\Domain\ValueObjects\GroupSnapshot;
use Src\AcademicRisk\RiskBoard\Domain\ValueObjects\TeacherSnapshot;

/**
 * The read port of the AcademicRisk context.
 *
 * Deliberately NOT a repository of an AcademicRisk aggregate — nothing
 * in this context is persisted. It is the seam through which the board
 * observes the academic offer, expressed entirely in this context's own
 * Value Objects. The adapter behind it happens to read the same tables
 * the Academic context writes, and that is the only place the two
 * contexts touch.
 */
interface RiskSourceInterface
{
    /**
     * Every group in the system, cancelled ones included — deciding what
     * a cancelled group means is the evaluator's job, not the adapter's.
     *
     * @return array<int, GroupSnapshot>
     */
    public function groupSnapshots(): array;

    /**
     * @return array<int, TeacherSnapshot>
     */
    public function teacherSnapshots(): array;
}
