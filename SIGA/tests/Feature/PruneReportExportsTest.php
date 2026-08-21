<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ReportExportStatus;
use App\Models\ReportExport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Nothing deleted a generated export before this command: the .pdf and
 * the row both stayed forever, so the disk grew at whatever rate people
 * exported and academic data sat at rest with no end date.
 *
 * What these pin is the pair of failure modes a prune can have — leaving
 * behind what it should take, and taking what it should leave.
 */
class PruneReportExportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_deletes_old_exports_with_their_files_and_keeps_recent_ones(): void
    {
        Storage::fake('local');

        $old = $this->export('report-exports/1/old.pdf', days: 40);
        $recent = $this->export('report-exports/1/recent.pdf', days: 2);

        $this->artisan('exports:prune')->assertSuccessful();

        $this->assertDatabaseMissing('report_exports', ['id' => $old->id]);
        Storage::disk('local')->assertMissing('report-exports/1/old.pdf');

        $this->assertDatabaseHas('report_exports', ['id' => $recent->id]);
        Storage::disk('local')->assertExists('report-exports/1/recent.pdf');
    }

    public function test_a_dry_run_reports_without_deleting(): void
    {
        Storage::fake('local');

        $old = $this->export('report-exports/1/old.pdf', days: 40);

        $this->artisan('exports:prune --dry-run')
            ->expectsOutputToContain('Would delete 1 export')
            ->assertSuccessful();

        $this->assertDatabaseHas('report_exports', ['id' => $old->id]);
        Storage::disk('local')->assertExists('report-exports/1/old.pdf');
    }

    /**
     * Deleting the row and deleting the file are two steps, and anything
     * that interrupts them between the two leaves a file nobody
     * references. Without this pass the disk still grows — slower, and
     * with no row left to explain why.
     */
    public function test_it_sweeps_files_that_no_export_row_points_at(): void
    {
        Storage::fake('local');

        $this->export('report-exports/1/kept.pdf', days: 1);
        Storage::disk('local')->put('report-exports/1/orphan.pdf', 'bytes');

        $this->artisan('exports:prune')->assertSuccessful();

        Storage::disk('local')->assertMissing('report-exports/1/orphan.pdf');
        Storage::disk('local')->assertExists('report-exports/1/kept.pdf');
    }

    public function test_a_retention_window_below_one_day_is_refused(): void
    {
        // --days=0 would delete an export the moment it was generated,
        // including the one the user is waiting to download.
        $this->artisan('exports:prune --days=0')->assertFailed();
    }

    private function export(string $path, int $days): ReportExport
    {
        Storage::disk('local')->put($path, 'pdf-bytes');

        $export = ReportExport::factory()->create([
            'user_id' => User::factory()->create()->id,
            'format' => 'pdf',
            'status' => ReportExportStatus::Ready,
            'disk' => 'local',
            'file_path' => $path,
        ]);

        // Written after creation: the factory's own timestamps win over
        // anything passed to create() for created_at.
        $export->forceFill(['created_at' => now()->subDays($days)])->saveQuietly();

        return $export;
    }
}
