<?php

namespace Database\Factories;

use App\Models\Teacher;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Teacher>
 */
class TeacherFactory extends Factory
{
    protected $model = Teacher::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Costa Rican national id shape: 9 digits, "1-2345-6789".
            'identity_card' => fake()->unique()->numerify('#-####-####'),
            'name' => fake()->name(),
            'reference_workload' => fake()->randomElement([0.50, 0.75, 1.00]),
        ];
    }
}
