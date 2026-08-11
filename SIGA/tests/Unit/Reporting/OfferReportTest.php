<?php

declare(strict_types=1);

namespace Tests\Unit\Reporting;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Src\Academic\Group\Domain\ValueObjects\GroupStatus;
use Src\Academic\Group\Domain\ValueObjects\Modality;
use Src\Reporting\OfferReport\Domain\Entities\OfferReport;
use Src\Reporting\OfferReport\Domain\ValueObjects\OfferRow;

/**
 * The totals RE-01's screen summarises above the table. They live on the
 * aggregate so the spreadsheet, the PDF and the screen cannot each count
 * "grupos sin docente" their own way.
 */
final class OfferReportTest extends TestCase
{
    public function test_it_summarises_the_offer_of_a_term(): void
    {
        $report = OfferReport::forTerm('2026-II', [
            $this->row('ISW-521-G01', teacher: 'Ana', classroom: 'Aula 101', enrollment: 30),
            $this->row('ISW-521-G02', teacher: null, classroom: 'Aula 102', enrollment: 20),
            $this->row('ISW-411-G01', teacher: 'Carlos', classroom: null, enrollment: 15),
            $this->row('ADM-101-G01', teacher: null, classroom: null, enrollment: 4),
        ], new DateTimeImmutable('2026-08-10 09:00:00'));

        $this->assertSame('2026-II', $report->term());
        $this->assertSame(4, $report->groupCount());
        $this->assertSame(2, $report->groupsWithoutTeacher());
        $this->assertSame(2, $report->groupsWithoutClassroom());
        $this->assertSame(69, $report->totalEstimatedEnrollment());
        $this->assertFalse($report->isEmpty());
    }

    public function test_a_term_with_no_groups_is_empty(): void
    {
        $report = OfferReport::forTerm('2027-I', [], new DateTimeImmutable);

        $this->assertTrue($report->isEmpty());
        $this->assertSame(0, $report->groupCount());
        $this->assertSame(0, $report->totalEstimatedEnrollment());
    }

    private function row(string $code, ?string $teacher, ?string $classroom, int $enrollment): OfferRow
    {
        return new OfferRow(
            groupId: crc32($code),
            groupCode: $code,
            courseCode: substr($code, 0, 7),
            teacherName: $teacher,
            classroomName: $classroom,
            modality: Modality::InPerson,
            status: GroupStatus::Open,
            estimatedEnrollment: $enrollment,
        );
    }
}
