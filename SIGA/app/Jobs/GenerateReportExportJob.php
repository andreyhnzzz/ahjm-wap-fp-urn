<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\ReportExportStatus;
use App\Models\ReportExport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Src\Shared\Export\Contracts\ExcelFileWriterInterface;
use Src\Shared\Export\Contracts\TabularPdfWriterInterface;
use Throwable;

/**
 * Renders a CRUD screen's export off the request/response cycle. A
 * 1500+ row table can take up to ~20s to render (see the mPDF/Dompdf/
 * Spatie spike) — long enough to tie up a PHP-FPM worker and make the
 * whole app feel unresponsive if 50 users export at once. The Livewire
 * component only creates the ReportExport row and dispatches this job;
 * InteractsWithExports::pollExportStatus() picks up the result.
 *
 * Reuses the same PdfFileWriterInterface/ExcelFileWriterInterface RE-01
 * already writes its archived reports through (see
 * FilesystemOfferReportArchive) — a queued export renders through the
 * exact same Chrome/OpenSpout path a synchronous one would. Only when
 * it happens changes, not what comes out.
 *
 * $headers/$rows arrive already formatted for display (label-keyed,
 * `format` callbacks already applied) — a queued job's properties are
 * serialized to the jobs table, and closures aren't serializable, so
 * InteractsWithExports resolves those before dispatch() rather than
 * this job needing to know about any entity's formatting rules.
 */
class GenerateReportExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $backoff = 3;

    /**
     * @param  array<int, array{label: string}>  $headers
     * @param  array<int, array<string, scalar|null>>  $rows
     */
    public function __construct(
        public readonly int $reportExportId,
        public readonly string $title,
        public readonly array $headers,
        public readonly array $rows,
        public readonly string $format,
        public readonly string $paperSize = 'letter',
    ) {}

    public function handle(TabularPdfWriterInterface $pdfWriter, ExcelFileWriterInterface $excelWriter): void
    {
        $export = ReportExport::query()->findOrFail($this->reportExportId);
        $export->update(['status' => ReportExportStatus::Processing]);

        $disk = Storage::disk($export->disk);
        $directory = 'report-exports/'.$export->user_id;
        $disk->makeDirectory($directory);

        $extension = $this->format === 'pdf' ? 'pdf' : 'xlsx';
        $relativePath = "{$directory}/{$export->id}.{$extension}";
        $absolutePath = $disk->path($relativePath);

        if ($this->format === 'pdf') {
            // The writer receives the rows, not finished markup: past a few
            // thousand rows this has to become several PDFs stitched back
            // together, and only something holding the rows can decide that.
            // See ChunkedChromePdfWriter for the measurements.
            $pdfWriter->write(
                $this->title,
                $this->headers,
                $this->rows,
                $absolutePath,
                $this->paperSize,
            );
        } else {
            $excelWriter->write($this->rows, $absolutePath);
        }

        $export->update(['status' => ReportExportStatus::Ready, 'file_path' => $relativePath]);
    }

    public function failed(?Throwable $exception): void
    {
        ReportExport::query()->whereKey($this->reportExportId)->update([
            'status' => ReportExportStatus::Failed,
            'error_message' => $exception?->getMessage(),
        ]);
    }
}
