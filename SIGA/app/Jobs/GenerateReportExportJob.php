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
use Src\Shared\Export\Infrastructure\RowSpool;
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
 * $headers arrive already formatted for display (label-keyed, `format`
 * callbacks already applied) — a queued job's properties are serialized
 * to the jobs table, and closures aren't serializable, so
 * InteractsWithExports resolves those before dispatch() rather than this
 * job needing to know about any entity's formatting rules.
 *
 * The rows arrive the same way but not by the same route: they come as a
 * path to a spool file (RowSpool) rather than as an array property. They
 * used to be the array, which put every row through the payload — 12.2 MB
 * of JSON and a 192 MB request peak at 45,000 rows, on the click rather
 * than in the worker. See RowSpool for the measurements.
 */
class GenerateReportExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $backoff = 3;

    /**
     * A timeout means the render hung, not that it was unlucky, so the
     * retry that $tries buys is not worth a second multi-minute Chromium
     * fleet on an already-struggling machine. Exceptions still retry;
     * only the timeout is terminal.
     */
    public bool $failOnTimeout = true;

    /**
     * Seconds the worker gives this job. Captured at dispatch because
     * that is where Laravel reads it into the queue payload.
     *
     * Without it the job ran under the worker's default 60s, which is
     * inside its own working range: a 45,000-row chunked export measures
     * 12-14s on the reference machine, ~40s on a 4-vCPU host, and ~55s
     * through the Browsershot fallback when the sidecar is down. The
     * export did not fail cleanly at that ceiling either — it was killed
     * and, with $tries = 2, started again from the top.
     *
     * config/exports.php carries the sizing; config/queue.php's
     * retry_after is derived from the same number and stays above it.
     */
    public int $timeout;

    /**
     * @param  array<int, array{label: string}>  $headers
     * @param  string  $rowsPath  Absolute path of the spooled rows (RowSpool)
     */
    public function __construct(
        public readonly int $reportExportId,
        public readonly string $title,
        public readonly array $headers,
        public readonly string $rowsPath,
        public readonly string $format,
        public readonly string $paperSize = 'letter',
    ) {
        $this->timeout = (int) config('exports.job.timeout');
    }

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

        $rows = RowSpool::read($this->rowsPath);

        if ($this->format === 'pdf') {
            // The writer receives the rows, not finished markup: past a few
            // thousand rows this has to become several PDFs stitched back
            // together, and only something holding the rows can decide that.
            // See ChunkedChromePdfWriter for the measurements — this is also
            // why there is no row-ceiling guard here. An earlier version of
            // this job refused anything past ~12,000 rows, measured against
            // a single Chrome document; chunking removed that ceiling rather
            // than raising it.
            $pdfWriter->write(
                $this->title,
                $this->headers,
                $rows,
                $absolutePath,
                $this->paperSize,
            );
        } else {
            $excelWriter->write($rows, $absolutePath);
        }

        $export->update(['status' => ReportExportStatus::Ready, 'file_path' => $relativePath]);

        RowSpool::discard($this->rowsPath);
    }

    public function failed(?Throwable $exception): void
    {
        // Only here, not in handle()'s finally: a job that throws on its
        // first of two attempts must still find its rows on the second.
        RowSpool::discard($this->rowsPath);

        ReportExport::query()->whereKey($this->reportExportId)->update([
            'status' => ReportExportStatus::Failed,
            'error_message' => $exception?->getMessage(),
        ]);
    }
}
