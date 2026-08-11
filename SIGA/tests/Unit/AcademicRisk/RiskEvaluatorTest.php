<?php

declare(strict_types=1);

namespace Tests\Unit\AcademicRisk;

use PHPUnit\Framework\TestCase;
use Src\AcademicRisk\RiskBoard\Domain\Services\RiskEvaluator;
use Src\AcademicRisk\RiskBoard\Domain\ValueObjects\GroupSnapshot;
use Src\AcademicRisk\RiskBoard\Domain\ValueObjects\RiskLevel;
use Src\AcademicRisk\RiskBoard\Domain\ValueObjects\RiskThresholds;
use Src\AcademicRisk\RiskBoard\Domain\ValueObjects\RiskType;
use Src\AcademicRisk\RiskBoard\Domain\ValueObjects\TeacherSnapshot;

/**
 * RE-04's four detection rules and their Alto/Medio/Bajo classification.
 *
 * Extends PHPUnit's TestCase, not Laravel's: RiskEvaluator is pure PHP
 * with no container, no database and no framework, so these tests need
 * none either. If any of them ever starts requiring `CreatesApplication`,
 * something has leaked into the domain that does not belong there.
 */
final class RiskEvaluatorTest extends TestCase
{
    private const TERM = '2026-II';

    public function test_it_reports_a_group_without_a_teacher_as_a_high_risk(): void
    {
        $board = $this->evaluator()->evaluate(
            [$this->group(id: 1, code: 'ISW-521-G01', teacherId: null)],
            [],
        );

        $this->assertSame(1, $board->total());

        $item = $board->itemsAt(RiskLevel::High)[0];
        $this->assertSame(RiskType::GroupWithoutTeacher, $item->type);
        $this->assertSame('ISW-521-G01', $item->subjectCode);
    }

    public function test_it_reports_a_group_without_a_classroom_as_a_high_risk(): void
    {
        $board = $this->evaluator()->evaluate(
            [$this->group(id: 1, code: 'ISW-521-G01', hasClassroom: false)],
            [$this->teacher()],
        );

        $this->assertSame(1, $board->countAt(RiskLevel::High));
        $this->assertSame(RiskType::GroupWithoutClassroom, $board->itemsAt(RiskLevel::High)[0]->type);
    }

    public function test_it_reports_a_group_below_the_enrollment_threshold_as_a_low_risk(): void
    {
        $board = $this->evaluator()->evaluate(
            [$this->group(id: 1, code: 'ISW-521-G01', enrollment: 4)],
            [$this->teacher()],
        );

        $item = $board->itemsAt(RiskLevel::Low)[0];

        $this->assertSame(RiskType::GroupBelowEnrollmentThreshold, $item->type);
        $this->assertSame(4.0, $item->measuredValue);
        $this->assertSame(5.0, $item->thresholdValue);
    }

    /**
     * The threshold is "below", not "below or equal" — a group sitting
     * exactly on it is not a risk. This is the boundary the acceptance
     * criteria hinge on, so it gets its own test.
     */
    public function test_a_group_exactly_on_the_enrollment_threshold_is_not_a_risk(): void
    {
        $board = $this->evaluator()->evaluate(
            [$this->group(id: 1, code: 'ISW-521-G01', enrollment: 5)],
            [$this->teacher()],
        );

        $this->assertTrue($board->isEmpty());
    }

    public function test_it_reports_a_teacher_over_the_workload_ceiling_as_a_medium_risk(): void
    {
        $board = $this->evaluator()->evaluate(
            [
                $this->group(id: 1, code: 'ISW-521-G01', workload: 0.5),
                $this->group(id: 2, code: 'ISW-521-G02', workload: 0.5),
                $this->group(id: 3, code: 'ISW-411-G01', workload: 0.25),
            ],
            [$this->teacher()],
        );

        $item = $board->itemsAt(RiskLevel::Medium)[0];

        $this->assertSame(RiskType::TeacherWorkloadConflict, $item->type);
        $this->assertSame(1.25, $item->measuredValue);
        $this->assertSame(1.0, $item->thresholdValue);
        $this->assertSame('1-1111-1111', $item->subjectCode);
    }

    public function test_a_teacher_exactly_at_the_ceiling_is_not_in_conflict(): void
    {
        $board = $this->evaluator()->evaluate(
            [
                $this->group(id: 1, code: 'ISW-521-G01', workload: 0.5),
                $this->group(id: 2, code: 'ISW-521-G02', workload: 0.5),
            ],
            [$this->teacher()],
        );

        $this->assertSame(0, $board->countAt(RiskLevel::Medium));
    }

    /**
     * Two full loads in two different terms is a career, not a conflict.
     */
    public function test_workload_is_accumulated_per_term_not_across_terms(): void
    {
        $board = $this->evaluator()->evaluate(
            [
                $this->group(id: 1, code: 'ISW-521-G01', workload: 1.0, term: '2026-I'),
                $this->group(id: 2, code: 'ISW-521-G02', workload: 1.0, term: '2026-II'),
            ],
            [$this->teacher()],
        );

        $this->assertSame(0, $board->countAt(RiskLevel::Medium));
    }

    public function test_a_cancelled_group_produces_no_risk_at_all(): void
    {
        $board = $this->evaluator()->evaluate(
            [$this->group(id: 1, code: 'ISW-521-G01', teacherId: null, hasClassroom: false, enrollment: 1, isActive: false)],
            [],
        );

        $this->assertTrue($board->isEmpty());
    }

    /**
     * The acceptance criteria describe an item that disappears once the
     * underlying data is corrected. Since the board is recomputed from
     * scratch rather than stored, "correcting the data" is simply
     * evaluating a fixed snapshot — and nothing survives from before.
     */
    public function test_an_item_disappears_once_its_cause_is_corrected(): void
    {
        $evaluator = $this->evaluator();

        $before = $evaluator->evaluate([$this->group(id: 1, code: 'ISW-521-G01', teacherId: null)], []);
        $this->assertSame(1, $before->total());

        $after = $evaluator->evaluate([$this->group(id: 1, code: 'ISW-521-G01')], [$this->teacher()]);
        $this->assertTrue($after->isEmpty());
    }

    /**
     * One group can be broken in several independent ways, and each one
     * is a separate thing to fix.
     */
    public function test_one_group_can_raise_several_risks_at_once(): void
    {
        $board = $this->evaluator()->evaluate(
            [$this->group(id: 1, code: 'ADM-101-G01', teacherId: null, hasClassroom: false, enrollment: 2)],
            [],
        );

        $this->assertSame(3, $board->total());
        $this->assertSame(2, $board->countAt(RiskLevel::High));
        $this->assertSame(1, $board->countAt(RiskLevel::Low));
    }

    public function test_the_enrollment_threshold_is_configurable(): void
    {
        $strict = new RiskEvaluator(new RiskThresholds(minimumEnrollment: 15, maximumWorkload: 1.0));

        $board = $strict->evaluate(
            [$this->group(id: 1, code: 'ISW-521-G01', enrollment: 10)],
            [$this->teacher()],
        );

        $this->assertSame(1, $board->countAt(RiskLevel::Low));
    }

    /**
     * Rounding to the two decimals the column actually stores keeps
     * binary floating-point noise from inventing a conflict: 0.35 three
     * times is 1.05 in decimal but 1.0499999999999998 in binary, and
     * 0.1 + 0.2 + 0.7 is 1.0 in decimal but 0.9999999999999999 in
     * binary. Both must be judged on their decimal value.
     */
    public function test_workload_totals_are_compared_at_the_stored_precision(): void
    {
        $noisyUnder = $this->evaluator()->evaluate(
            [
                $this->group(id: 1, code: 'A-G01', workload: 0.1),
                $this->group(id: 2, code: 'A-G02', workload: 0.2),
                $this->group(id: 3, code: 'A-G03', workload: 0.7),
            ],
            [$this->teacher()],
        );

        $this->assertSame(0, $noisyUnder->countAt(RiskLevel::Medium), 'A decimal total of exactly 1.00 is not a conflict.');

        $noisyOver = $this->evaluator()->evaluate(
            [
                $this->group(id: 1, code: 'A-G01', workload: 0.35),
                $this->group(id: 2, code: 'A-G02', workload: 0.35),
                $this->group(id: 3, code: 'A-G03', workload: 0.35),
            ],
            [$this->teacher()],
        );

        $this->assertSame(1, $noisyOver->countAt(RiskLevel::Medium), 'A decimal total of 1.05 is a conflict.');
    }

    /**
     * An alert nobody can act on is worse than no alert: if the two
     * snapshot queries disagree about which teachers exist, the evaluator
     * stays silent rather than pointing at a record that is not there.
     */
    public function test_it_skips_a_conflict_whose_teacher_is_missing_from_the_directory(): void
    {
        $board = $this->evaluator()->evaluate(
            [
                $this->group(id: 1, code: 'A-G01', workload: 0.8),
                $this->group(id: 2, code: 'A-G02', workload: 0.8),
            ],
            [],
        );

        $this->assertSame(0, $board->countAt(RiskLevel::Medium));
    }

    public function test_the_board_is_ordered_by_urgency(): void
    {
        $board = $this->evaluator()->evaluate(
            [
                $this->group(id: 1, code: 'B-G01', enrollment: 1),
                $this->group(id: 2, code: 'A-G01', teacherId: null),
            ],
            [$this->teacher()],
        );

        $levels = array_map(
            static fn ($item): string => $item->level()->value,
            $board->all(),
        );

        $this->assertSame(['high', 'low'], $levels);
    }

    private function evaluator(): RiskEvaluator
    {
        return new RiskEvaluator(new RiskThresholds(minimumEnrollment: 5, maximumWorkload: 1.0));
    }

    /**
     * A healthy group by default; each test overrides only the one thing
     * it is about, so the intent of every case is visible at its call
     * site instead of buried in a wall of arguments.
     */
    private function group(
        int $id,
        string $code,
        ?int $teacherId = 10,
        bool $hasClassroom = true,
        int $enrollment = 25,
        float $workload = 0.25,
        string $term = self::TERM,
        bool $isActive = true,
    ): GroupSnapshot {
        return new GroupSnapshot(
            id: $id,
            code: $code,
            term: $term,
            teacherId: $teacherId,
            hasClassroom: $hasClassroom,
            estimatedEnrollment: $enrollment,
            assignedWorkload: $workload,
            isActive: $isActive,
        );
    }

    private function teacher(): TeacherSnapshot
    {
        return new TeacherSnapshot(id: 10, identityCard: '1-1111-1111', name: 'Ana Rodríguez');
    }
}
