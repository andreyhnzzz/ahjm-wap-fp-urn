<?php

namespace Tests\Feature;

use App\Enums\ReportExportStatus;
use App\Jobs\GenerateReportExportJob;
use App\Models\ReportExport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Src\Shared\Export\Contracts\ExcelFileWriterInterface;
use Src\Shared\Export\Contracts\TabularPdfWriterInterface;
use Tests\TestCase;

/**
 * GenerateReportExportJob is what InteractsWithExports::queueExport()
 * hands the actual rendering off to — these pin its two outcomes (the
 * export lands as 'ready' with a file, or as 'failed' with a reason)
 * without touching real Chrome/OpenSpout, which is covered separately
 * by the async export flow itself.
 */
class ReportExportJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_successful_render_marks_the_export_ready_and_writes_the_file(): void
    {
        Storage::fake('local');
        // The job takes the rows now, not finished HTML: past a few thousand
        // rows the writer has to split them across several documents, and
        // only something holding the rows can decide that. See
        // ChunkedChromePdfWriter.
        $this->app->bind(TabularPdfWriterInterface::class, fn () => new class implements TabularPdfWriterInterface
        {
            public function write(string $title, array $headers, iterable $rows, string $absolutePath, string $paperSize = 'letter'): void
            {
                file_put_contents($absolutePath, 'fake-pdf-bytes');
            }
        });

        $export = ReportExport::factory()->create([
            'user_id' => User::factory()->create()->id,
            'format' => 'pdf',
            'status' => ReportExportStatus::Pending,
            'disk' => 'local',
        ]);

        (new GenerateReportExportJob(
            $export->id,
            'Roles',
            [['label' => 'Name']],
            [['Name' => 'Coordinator']],
            'pdf',
        ))->handle(
            $this->app->make(TabularPdfWriterInterface::class),
            $this->app->make(ExcelFileWriterInterface::class),
        );

        $export->refresh();

        $this->assertSame(ReportExportStatus::Ready, $export->status);
        $this->assertNotNull($export->file_path);
        Storage::disk('local')->assertExists($export->file_path);
    }

    public function test_a_failed_render_marks_the_export_failed_with_the_error(): void
    {
        $export = ReportExport::factory()->create([
            'user_id' => User::factory()->create()->id,
            'format' => 'excel',
            'status' => ReportExportStatus::Pending,
            'disk' => 'local',
        ]);

        $job = new GenerateReportExportJob(
            $export->id,
            'Roles',
            [['label' => 'Name']],
            [['Name' => 'Coordinator']],
            'excel',
        );

        // Simulates what the queue worker does when handle() throws: it
        // calls failed() with the exception instead of retrying forever.
        $job->failed(new \RuntimeException('disk full'));

        $export->refresh();

        $this->assertSame(ReportExportStatus::Failed, $export->status);
        $this->assertSame('disk full', $export->error_message);
    }
}
