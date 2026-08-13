<?php

namespace Database\Factories;

use App\Enums\ReportExportStatus;
use App\Models\ReportExport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReportExport>
 */
class ReportExportFactory extends Factory
{
    protected $model = ReportExport::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'format' => fake()->randomElement(['pdf', 'excel']),
            'title' => fake()->words(2, true),
            'filename' => fake()->slug().'.pdf',
            'status' => ReportExportStatus::Pending,
            'disk' => 'local',
        ];
    }
}
