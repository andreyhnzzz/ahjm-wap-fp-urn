<?php

declare(strict_types=1);

namespace Src\Academic\Group\Domain\Entities;

use Src\Academic\Group\Domain\Exceptions\InvalidEnrollmentException;
use Src\Academic\Group\Domain\Exceptions\InvalidGroupWorkloadException;
use Src\Academic\Group\Domain\ValueObjects\GroupStatus;
use Src\Academic\Group\Domain\ValueObjects\Modality;

/**
 * Group — Aggregate Root of the Academic\Group module: one course, in
 * one term, optionally staffed and optionally housed.
 *
 * Teacher and Classroom are referenced by identity (plain ints), never
 * by object: they are separate aggregates with their own lifecycle, and
 * holding instances of them here would make every group load drag two
 * more aggregates behind it and blur where a transaction boundary ends.
 *
 * Both references are nullable *by requirement*. INFRA-01 asks for
 * groups that can be saved deliberately incomplete, because "sin
 * docente" and "sin aula" are precisely the two High risks RE-04 has to
 * surface. An unstaffed group is a valid Group in a known-bad state, not
 * an invalid one.
 *
 * Pure PHP: no Eloquent, no Illuminate, no Livewire.
 */
final class Group
{
    private function __construct(
        private readonly ?int $id,
        private string $code,
        private string $courseCode,
        private string $term,
        private ?int $teacherId,
        private ?int $classroomId,
        private int $estimatedEnrollment,
        private float $assignedWorkload,
        private Modality $modality,
        private GroupStatus $status,
    ) {}

    public static function create(
        string $code,
        string $courseCode,
        string $term,
        ?int $teacherId,
        ?int $classroomId,
        int $estimatedEnrollment,
        float $assignedWorkload,
        Modality $modality,
        GroupStatus $status,
    ): self {
        self::assertWorkloadIsCoherent($code, $teacherId, $assignedWorkload);
        self::assertEnrollmentIsNotNegative($estimatedEnrollment);

        return new self(
            id: null,
            code: $code,
            courseCode: $courseCode,
            term: $term,
            teacherId: $teacherId,
            classroomId: $classroomId,
            estimatedEnrollment: $estimatedEnrollment,
            assignedWorkload: $assignedWorkload,
            modality: $modality,
            status: $status,
        );
    }

    /**
     * Deliberately does not re-assert the workload rule, unlike create().
     *
     * Deleting a teacher nulls `teacher_id` at the database level while
     * leaving `assigned_workload` untouched — a pair create() would
     * reject. Throwing here would make one orphaned row take down the
     * entire group listing, and the stale figure is inert anyway: every
     * read model that sums workload groups by teacher, so a group with
     * no teacher contributes to nobody's total. The next write through
     * the domain normalizes it.
     */
    public static function reconstitute(
        int $id,
        string $code,
        string $courseCode,
        string $term,
        ?int $teacherId,
        ?int $classroomId,
        int $estimatedEnrollment,
        float $assignedWorkload,
        Modality $modality,
        GroupStatus $status,
    ): self {
        return new self(
            id: $id,
            code: $code,
            courseCode: $courseCode,
            term: $term,
            teacherId: $teacherId,
            classroomId: $classroomId,
            estimatedEnrollment: $estimatedEnrollment,
            assignedWorkload: $assignedWorkload,
            modality: $modality,
            status: $status,
        );
    }

    public function relabel(string $code, string $courseCode): void
    {
        $this->code = $code;
        $this->courseCode = $courseCode;
    }

    public function reschedule(string $term): void
    {
        $this->term = $term;
    }

    /**
     * Teacher and workload move together on purpose: the workload is a
     * property of *this teacher teaching this group*, so there is no
     * legitimate moment where one changes without the other being
     * reconsidered. Pass null + 0.0 to leave the group unstaffed.
     */
    public function assignTeacher(?int $teacherId, float $assignedWorkload): void
    {
        self::assertWorkloadIsCoherent($this->code, $teacherId, $assignedWorkload);

        $this->teacherId = $teacherId;
        $this->assignedWorkload = $assignedWorkload;
    }

    public function assignClassroom(?int $classroomId): void
    {
        $this->classroomId = $classroomId;
    }

    public function estimateEnrollment(int $students): void
    {
        self::assertEnrollmentIsNotNegative($students);

        $this->estimatedEnrollment = $students;
    }

    public function changeModality(Modality $modality): void
    {
        $this->modality = $modality;
    }

    public function changeStatus(GroupStatus $status): void
    {
        $this->status = $status;
    }

    public function hasTeacher(): bool
    {
        return $this->teacherId !== null;
    }

    public function hasClassroom(): bool
    {
        return $this->classroomId !== null;
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function code(): string
    {
        return $this->code;
    }

    public function courseCode(): string
    {
        return $this->courseCode;
    }

    public function term(): string
    {
        return $this->term;
    }

    public function teacherId(): ?int
    {
        return $this->teacherId;
    }

    public function classroomId(): ?int
    {
        return $this->classroomId;
    }

    public function estimatedEnrollment(): int
    {
        return $this->estimatedEnrollment;
    }

    public function assignedWorkload(): float
    {
        return $this->assignedWorkload;
    }

    public function modality(): Modality
    {
        return $this->modality;
    }

    public function status(): GroupStatus
    {
        return $this->status;
    }

    private static function assertWorkloadIsCoherent(string $code, ?int $teacherId, float $assignedWorkload): void
    {
        if ($assignedWorkload < 0.0) {
            throw InvalidGroupWorkloadException::mustNotBeNegative($assignedWorkload);
        }

        if ($teacherId === null && $assignedWorkload > 0.0) {
            throw InvalidGroupWorkloadException::requiresATeacher($code);
        }
    }

    private static function assertEnrollmentIsNotNegative(int $estimatedEnrollment): void
    {
        if ($estimatedEnrollment < 0) {
            throw InvalidEnrollmentException::mustNotBeNegative($estimatedEnrollment);
        }
    }
}
