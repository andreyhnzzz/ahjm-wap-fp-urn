<?php

declare(strict_types=1);

namespace Tests\Unit\Reporting;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Src\Academic\Group\Domain\ValueObjects\GroupStatus;
use Src\Academic\Group\Domain\ValueObjects\Modality;
use Src\Reporting\TeacherLoadReport\Domain\Entities\TeacherLoadReport;
use Src\Reporting\TeacherLoadReport\Domain\ValueObjects\TeacherLoadRow;
use Src\Reporting\TeacherLoadReport\Domain\ValueObjects\TeacherReference;
use Src\Reporting\TeacherLoadReport\Domain\ValueObjects\WorkloadStatus;

/**
 * RE-02's comparison rules: assigned versus estimated workload, and the
 * alert each outcome deserves.
 */
final class TeacherLoadReportTest extends TestCase
{
    private const UNDER_LOAD_RATIO = 0.8;

    public function test_assigned_workload_is_the_sum_of_the_groups(): void
    {
        $report = $this->report([0.25, 0.5, 0.25]);

        $this->assertSame(1.0, $report->assignedWorkload());
        $this->assertSame(3, $report->groupCount());
    }

    public function test_assigning_more_than_the_reference_is_over_contracting(): void
    {
        $report = $this->report([0.75, 0.5]);

        $this->assertSame(WorkloadStatus::OverContracted, $report->status());
        $this->assertTrue($report->status()->isAlert());
    }

    public function test_assigning_below_eighty_percent_of_the_reference_is_under_contracting(): void
    {
        $report = $this->report([0.5]);

        $this->assertSame(WorkloadStatus::UnderContracted, $report->status());
    }

    /**
     * Exactly 80% is the boundary the requirement draws: "menor a 80%"
     * excludes 80% itself, so this teacher carries no alert.
     */
    public function test_exactly_eighty_percent_of_the_reference_raises_no_alert(): void
    {
        $report = $this->report([0.8]);

        $this->assertSame(WorkloadStatus::Balanced, $report->status());
        $this->assertFalse($report->status()->isAlert());
    }

    public function test_matching_the_reference_exactly_raises_no_alert(): void
    {
        $report = $this->report([0.5, 0.5]);

        $this->assertSame(WorkloadStatus::Balanced, $report->status());
    }

    public function test_a_teacher_with_no_groups_is_under_contracted(): void
    {
        $report = $this->report([]);

        $this->assertSame(0.0, $report->assignedWorkload());
        $this->assertSame(WorkloadStatus::UnderContracted, $report->status());
        $this->assertTrue($report->isEmpty());
    }

    public function test_utilization_is_the_assigned_share_of_the_reference(): void
    {
        $report = $this->report([0.25], reference: 0.5);

        $this->assertSame(0.5, $report->utilization());
    }

    public function test_the_under_load_ratio_is_configurable(): void
    {
        $lenient = $this->report([0.5], underLoadRatio: 0.4);

        $this->assertSame(WorkloadStatus::Balanced, $lenient->status());
    }

    /**
     * The report is the single source of its own totals: a caller cannot
     * hand it a sum that disagrees with the rows it prints.
     */
    public function test_totals_are_derived_from_the_rows(): void
    {
        $report = $this->report([0.25, 0.25]);

        $this->assertSame(0.5, $report->assignedWorkload());
        $this->assertSame(60, $report->totalEstimatedEnrollment());
    }

    public function test_a_reference_workload_of_zero_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new TeacherReference(id: 1, identityCard: '1-1111-1111', name: 'Ana', referenceWorkload: 0.0);
    }

    /**
     * @param  array<int, float>  $workloads
     */
    private function report(array $workloads, float $reference = 1.0, float $underLoadRatio = self::UNDER_LOAD_RATIO): TeacherLoadReport
    {
        $rows = [];

        foreach ($workloads as $index => $workload) {
            $rows[] = new TeacherLoadRow(
                groupCode: 'ISW-521-G'.($index + 1),
                courseCode: 'ISW-521',
                classroomName: 'Aula 101',
                modality: Modality::InPerson,
                status: GroupStatus::Open,
                estimatedEnrollment: 30,
                assignedWorkload: $workload,
            );
        }

        return TeacherLoadReport::assemble(
            teacher: new TeacherReference(id: 1, identityCard: '1-1111-1111', name: 'Ana Rodríguez', referenceWorkload: $reference),
            term: '2026-II',
            rows: $rows,
            underLoadRatio: $underLoadRatio,
            generatedAt: new DateTimeImmutable('2026-08-10 09:00:00'),
        );
    }
}
