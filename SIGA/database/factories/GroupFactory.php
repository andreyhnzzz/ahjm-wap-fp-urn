<?php

namespace Database\Factories;

use App\Models\Classroom;
use App\Models\Group;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\Factories\Factory;
use Src\Academic\Group\Domain\ValueObjects\GroupStatus;
use Src\Academic\Group\Domain\ValueObjects\Modality;

/**
 * @extends Factory<Group>
 */
class GroupFactory extends Factory
{
    protected $model = Group::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $courseCode = fake()->randomElement(['ISW-521', 'ISW-411', 'ISW-311', 'ISW-211', 'ISW-111']);

        return [
            // The globally unique suffix keeps `code` unique across every
            // course, which is what the column's unique index requires.
            'code' => $courseCode.'-G'.str_pad((string) fake()->unique()->numberBetween(1, 999), 2, '0', STR_PAD_LEFT),
            'course_code' => $courseCode,
            'term' => '2026-II',
            'teacher_id' => Teacher::factory(),
            'classroom_id' => Classroom::factory(),
            'estimated_enrollment' => fake()->numberBetween(8, 40),
            'assigned_workload' => fake()->randomElement([0.25, 0.50]),
            'modality' => fake()->randomElement(Modality::values()),
            'status' => GroupStatus::Open->value,
        ];
    }

    /**
     * A group nobody is assigned to teach — High risk on the RE-04 board.
     * The workload goes to zero with it, mirroring the Group entity's
     * invariant (workload without a teacher is not a legal state).
     */
    public function withoutTeacher(): self
    {
        return $this->state(fn (): array => [
            'teacher_id' => null,
            'assigned_workload' => 0,
        ]);
    }

    /**
     * A group with no room assigned — the other High risk.
     */
    public function withoutClassroom(): self
    {
        return $this->state(fn (): array => [
            'classroom_id' => null,
        ]);
    }
}
