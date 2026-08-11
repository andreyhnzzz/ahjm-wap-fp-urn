<?php

declare(strict_types=1);

namespace Tests\Unit\Academic;

use PHPUnit\Framework\TestCase;
use Src\Academic\Group\Domain\Entities\Group;
use Src\Academic\Group\Domain\Exceptions\InvalidEnrollmentException;
use Src\Academic\Group\Domain\Exceptions\InvalidGroupWorkloadException;
use Src\Academic\Group\Domain\ValueObjects\GroupStatus;
use Src\Academic\Group\Domain\ValueObjects\Modality;

/**
 * The invariants of the Group aggregate — the rules that hold no matter
 * which entry point writes the data (Livewire form, seeder, future CSV
 * import or API).
 */
final class GroupTest extends TestCase
{
    public function test_a_group_can_be_created_deliberately_without_a_teacher_or_a_classroom(): void
    {
        $group = $this->group(teacherId: null, classroomId: null, workload: 0.0);

        $this->assertFalse($group->hasTeacher());
        $this->assertFalse($group->hasClassroom());
        $this->assertSame(0.0, $group->assignedWorkload());
    }

    public function test_a_group_without_a_teacher_cannot_carry_a_workload(): void
    {
        $this->expectException(InvalidGroupWorkloadException::class);

        $this->group(teacherId: null, workload: 0.5);
    }

    public function test_unassigning_the_teacher_of_a_staffed_group_requires_zeroing_its_workload(): void
    {
        $group = $this->group();

        $this->expectException(InvalidGroupWorkloadException::class);

        $group->assignTeacher(null, 0.5);
    }

    public function test_a_teacher_can_be_unassigned_together_with_the_workload(): void
    {
        $group = $this->group();

        $group->assignTeacher(null, 0.0);

        $this->assertFalse($group->hasTeacher());
        $this->assertSame(0.0, $group->assignedWorkload());
    }

    public function test_a_negative_workload_is_rejected(): void
    {
        $this->expectException(InvalidGroupWorkloadException::class);

        $this->group(workload: -0.25);
    }

    public function test_a_negative_enrollment_is_rejected(): void
    {
        $this->expectException(InvalidEnrollmentException::class);

        $this->group(enrollment: -1);
    }

    /**
     * Deleting a teacher nulls the reference at the database level and
     * leaves the workload behind. Reconstitution has to tolerate that
     * pair, or one orphaned row would take down the whole listing.
     */
    public function test_reconstitution_tolerates_a_workload_left_behind_by_a_deleted_teacher(): void
    {
        $group = Group::reconstitute(
            id: 7,
            code: 'ISW-521-G01',
            courseCode: 'ISW-521',
            term: '2026-II',
            teacherId: null,
            classroomId: 3,
            estimatedEnrollment: 25,
            assignedWorkload: 0.5,
            modality: Modality::InPerson,
            status: GroupStatus::Open,
        );

        $this->assertFalse($group->hasTeacher());
        $this->assertSame(0.5, $group->assignedWorkload());
    }

    public function test_a_cancelled_group_is_not_active(): void
    {
        $group = $this->group();

        $this->assertTrue($group->isActive());

        $group->changeStatus(GroupStatus::Cancelled);
        $this->assertFalse($group->isActive());

        $group->changeStatus(GroupStatus::Closed);
        $this->assertTrue($group->isActive(), 'A closed group still has to be delivered.');
    }

    private function group(
        ?int $teacherId = 4,
        ?int $classroomId = 2,
        int $enrollment = 25,
        float $workload = 0.25,
    ): Group {
        return Group::create(
            code: 'ISW-521-G01',
            courseCode: 'ISW-521',
            term: '2026-II',
            teacherId: $teacherId,
            classroomId: $classroomId,
            estimatedEnrollment: $enrollment,
            assignedWorkload: $workload,
            modality: Modality::InPerson,
            status: GroupStatus::Open,
        );
    }
}
