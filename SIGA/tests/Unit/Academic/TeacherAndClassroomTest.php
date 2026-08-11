<?php

declare(strict_types=1);

namespace Tests\Unit\Academic;

use PHPUnit\Framework\TestCase;
use Src\Academic\Classroom\Domain\Entities\Classroom;
use Src\Academic\Classroom\Domain\Exceptions\InvalidCapacityException;
use Src\Academic\Teacher\Domain\Entities\Teacher;
use Src\Academic\Teacher\Domain\Exceptions\InvalidWorkloadException;

/**
 * Invariants of the two smaller Academic aggregates. Both rules exist
 * because a downstream report would otherwise compute nonsense from
 * them: a zero reference workload breaks RE-02's percentage comparison,
 * and a classroom that seats nobody is not a room.
 */
final class TeacherAndClassroomTest extends TestCase
{
    public function test_a_teacher_needs_a_positive_reference_workload(): void
    {
        $this->expectException(InvalidWorkloadException::class);

        Teacher::create(identityCard: '1-1111-1111', name: 'Ana', referenceWorkload: 0.0);
    }

    public function test_a_teacher_reference_workload_cannot_be_changed_to_zero(): void
    {
        $teacher = Teacher::create(identityCard: '1-1111-1111', name: 'Ana', referenceWorkload: 1.0);

        $this->expectException(InvalidWorkloadException::class);

        $teacher->changeReferenceWorkload(0.0);
    }

    public function test_a_teacher_can_be_renamed_and_re_identified(): void
    {
        $teacher = Teacher::create(identityCard: '1-1111-1111', name: 'Ana', referenceWorkload: 1.0);

        $teacher->rename('Ana Lucía Rodríguez');
        $teacher->changeIdentityCard('2-2222-2222');
        $teacher->changeReferenceWorkload(0.75);

        $this->assertSame('Ana Lucía Rodríguez', $teacher->name());
        $this->assertSame('2-2222-2222', $teacher->identityCard());
        $this->assertSame(0.75, $teacher->referenceWorkload());
        $this->assertNull($teacher->id(), 'An unsaved entity has no identity yet.');
    }

    public function test_a_classroom_needs_a_positive_capacity(): void
    {
        $this->expectException(InvalidCapacityException::class);

        Classroom::create(name: 'Aula 101', capacity: 0);
    }

    public function test_a_classroom_knows_whether_a_group_fits(): void
    {
        $classroom = Classroom::create(name: 'Aula 101', capacity: 30);

        $this->assertTrue($classroom->canHost(30), 'A group matching the capacity exactly fits.');
        $this->assertTrue($classroom->canHost(12));
        $this->assertFalse($classroom->canHost(31));
    }
}
