<?php

declare(strict_types=1);

namespace Src\Shared\Export\Infrastructure;

use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use Src\Shared\Export\Contracts\PdfFileWriterInterface;
use Src\Shared\Export\Contracts\TabularPdfWriterInterface;

/**
 * Renders a large table by splitting it into several small PDFs and
 * stitching them back together.
 *
 * WHY: a single PDF document has a hard ceiling in Chrome's print
 * pipeline, not a soft one. Swept against the real template, untagged, on
 * a fresh page (see pdf-sidecar.mjs for why fresh, not reused):
 *
 *   15,000 rows -> 22.8s, 1,155 pages, valid PDF
 *   16,000 rows -> Chrome's print process dies:
 *                  "Protocol error (Page.printToPDF): Printing failed"
 *
 * Nothing degrades in between — it works, then it fails outright. So
 * chunking is not a tuning knob, it is the only way a report past that
 * line comes out at all. CHUNK_ROWS stays well under the measured line
 * for the same reason config/exports.php used to keep its own margin: the
 * real limit is total HTML content, not row count, and a report with
 * longer cells reaches the same wall sooner than this project's own
 * template does.
 *
 * The pieces:
 *
 *   1. chunk    rows sliced into CHUNK_ROWS documents
 *   2. render   PARALLEL_REQUESTS at a time against the warm sidecar
 *   3. merge    mPDF imports every page and writes one file
 *
 * mPDF is already installed (it arrived with the D-09 engine spike that
 * *rejected* it as a renderer) so the merge costs no new dependency, and
 * it recompresses the pages on the way out.
 *
 * Below CHUNK_THRESHOLD rows this delegates to the plain one-document
 * writer, so a small report keeps rendering exactly as before — same
 * Chromium, same template, byte-for-byte the same output.
 */
final class ChunkedChromePdfWriter implements TabularPdfWriterInterface
{
    /**
     * Rows per chunk. Roughly a third of the measured 15,000-row ceiling —
     * comfortable margin for reports whose cells run longer than this
     * project's, and small enough that the merge step is stitching a
     * manageable number of documents rather than dozens of tiny ones.
     */
    private const CHUNK_ROWS = 6000;

    /**
     * Concurrent renders. Chromium layout is CPU-bound, so this tracks
     * cores rather than I/O.
     *
     * The sidecar has to be able to serve them: PDF_SIDECAR_CONCURRENCY
     * (default 10, deliberately the same number) bounds how many fresh
     * pages it renders at once, and anything beyond that queues inside the
     * sidecar. Change them together, or the extra requests here just wait
     * — silently, and only under load.
     */
    private const PARALLEL_REQUESTS = 10;

    /**
     * Under this, one document is both faster (no merge pass) and safer
     * (no temp files) — comfortably inside the measured working range.
     */
    private const CHUNK_THRESHOLD = 12000;

    /** Seconds a single chunk render may take before we give up on it. */
    private const CHUNK_TIMEOUT = 60;

    /** Seconds spent turning rows into markup, accumulated across chunks. */
    private float $htmlSeconds = 0.0;

    public function __construct(
        private readonly PdfFileWriterInterface $singleDocumentWriter,
    ) {}

    /**
     * @param  array<int, array{label: string}>  $headers
     * @param  iterable<array<string, scalar|null>>  $rows
     */
    public function write(
        string $title,
        array $headers,
        iterable $rows,
        string $absolutePath,
        string $paperSize = 'letter',
    ): void {
        $chunks = $this->chunk($rows);

        if ($chunks === []) {
            $this->singleDocumentWriter->write(
                $this->html($title, $headers, [], false), $absolutePath, $paperSize);

            return;
        }

        if (count($chunks) === 1 && count($chunks[0]) <= self::CHUNK_THRESHOLD) {
            $this->singleDocumentWriter->write(
                $this->html($title, $headers, $chunks[0], false), $absolutePath, $paperSize);

            return;
        }

        $startedRendering = microtime(true);
        $files = $this->renderChunks($title, $headers, $chunks, $paperSize);
        $rendered = microtime(true);

        try {
            $this->merge($files, $absolutePath);

            // Timings are logged, not measured-and-discarded: RE-01 requires
            // the elapsed time to be verifiable in the log, and when this
            // export gets slow the first question is always which of the two
            // phases moved.
            Log::info('pdf.chunked', [
                'rows' => array_sum(array_map('count', $chunks)),
                'chunks' => count($chunks),
                'html_s' => round($this->htmlSeconds, 2),
                'render_s' => round($rendered - $startedRendering, 2),
                'merge_s' => round(microtime(true) - $rendered, 2),
                'total_s' => round(microtime(true) - $startedRendering, 2),
            ]);
        } finally {
            foreach ($files as $file) {
                @unlink($file);
            }
        }
    }

    /**
     * Slices the rows without ever materialising more than one chunk of
     * markup at a time upstream. The rows themselves are held (the caller
     * already has them in memory) but the 30MB HTML string the naive path
     * builds is never created — only CHUNK_ROWS worth of it at once.
     *
     * @param  iterable<array<string, scalar|null>>  $rows
     * @return array<int, array<int, array<string, scalar|null>>>
     */
    private function chunk(iterable $rows): array
    {
        $all = is_array($rows) ? $rows : iterator_to_array($rows, false);
        $total = count($all);

        if ($total === 0) {
            return [];
        }

        return array_chunk($all, $this->chunkSize($total));
    }

    /**
     * Rows per chunk, adjusted so the chunks fill whole waves.
     *
     * Renders go out PARALLEL_REQUESTS at a time and each wave waits for
     * its slowest member, so a wave that is only part full wastes the
     * remaining slots outright: 45.000 rows at a flat 2.000 gives 23
     * chunks, which is two full waves of ten and a third wave of three
     * doing the work of ten. Rounding the chunk COUNT up to a multiple of
     * the pool turns that into three even waves of 1.500 rows, which is
     * also further down the ms/row curve.
     *
     * @return positive-int array_chunk() throws on a size of 0
     */
    private function chunkSize(int $total): int
    {
        $chunks = (int) ceil($total / self::CHUNK_ROWS);

        if ($chunks <= 1) {
            return self::CHUNK_ROWS;
        }

        $waves = (int) ceil($chunks / self::PARALLEL_REQUESTS);
        $chunks = $waves * self::PARALLEL_REQUESTS;

        // max(1, …) is the invariant, not a cast to please the analyser:
        // array_chunk() with a size of 0 throws, and a caller passing a
        // single row must still get one chunk of one.
        return max(1, (int) ceil($total / $chunks));
    }

    /**
     * @param  array<int, array{label: string}>  $headers
     * @param  array<int, array<int, array<string, scalar|null>>>  $chunks
     * @return array<int, string> absolute paths of the chunk PDFs, in order
     */
    private function renderChunks(string $title, array $headers, array $chunks, string $paperSize): array
    {
        $directory = $this->temporaryDirectory();
        $files = [];
        // Every chunk but the last holds exactly this many rows, so it is
        // also the running offset between one chunk and the next.
        $chunkSize = count(reset($chunks) ?: []);

        // The pool below cannot trigger the sidecar's own boot-on-demand
        // (Http::pool() has no hook for a single request's response before
        // firing the rest), so that has to happen once, up front. If it
        // does not come up, every chunk in every wave falls through to
        // Browsershot individually — slower, never impossible.
        WarmChromePdfRenderer::ensureRunning();
        $endpoint = WarmChromePdfRenderer::pdfEndpoint($paperSize);

        // In waves rather than all at once: two dozen concurrent renders
        // would put every chunk's HTML in flight simultaneously and
        // oversubscribe the CPU, which makes each individual render
        // slower without finishing the batch any sooner.
        foreach (array_chunk($chunks, self::PARALLEL_REQUESTS, true) as $wave) {
            $bodies = [];

            foreach ($wave as $index => $chunkRows) {
                // Only the first chunk carries the cover block; the rest
                // continue the table, or the report header and title would
                // reappear every chunk in the merged document. The offset
                // does the same job for the row numbers, which otherwise
                // restart at 1 in every chunk.
                $bodies[$index] = $this->html(
                    $title, $headers, $chunkRows, $index > 0, $index * $chunkSize);
            }

            // Raw body, not a JSON envelope: a 6.000-row chunk is well over
            // a megabyte of HTML, and json_encode on this side plus
            // JSON.parse on the sidecar's is pure copying of a string that
            // is already exactly what Chrome wants (same reasoning as
            // WarmChromePdfRenderer::post(), which this bypasses because it
            // only knows how to send one request at a time).
            $responses = Http::pool(function (Pool $pool) use ($bodies, $endpoint): array {
                $requests = [];

                foreach ($bodies as $index => $html) {
                    $requests[] = $pool->as((string) $index)
                        ->timeout(self::CHUNK_TIMEOUT)
                        ->withBody($html, 'text/html')
                        ->post($endpoint);
                }

                return $requests;
            });

            foreach ($wave as $index => $ignored) {
                $response = $responses[(string) $index] ?? null;
                $path = $directory.DIRECTORY_SEPARATOR.str_pad((string) $index, 5, '0', STR_PAD_LEFT).'.pdf';

                // The sidecar is an accelerator, never a requirement — the
                // same rule WarmChromePdfRenderer already follows. When it
                // is not running, this chunk goes through Browsershot
                // instead: ~400ms of node startup per chunk on top, which
                // is far better than a large export becoming impossible
                // because a helper process is down.
                //
                // The instanceof is not defensive noise: on a refused
                // connection Http::pool() puts a ConnectionException in the
                // slot where a Response would go, and calling successful()
                // on it is a fatal error — the fallback would itself be
                // what breaks the export.
                if (! $response instanceof Response || ! $response->successful()) {
                    $this->singleDocumentWriter->write($bodies[$index], $path, $paperSize);
                    $files[$index] = $path;

                    continue;
                }

                file_put_contents($path, $response->body());
                $files[$index] = $path;
            }
        }

        ksort($files);

        return array_values($files);
    }

    /**
     * @param  array<int, string>  $files
     */
    private function merge(array $files, string $absolutePath): void
    {
        $pdf = new Mpdf([
            'tempDir' => $this->temporaryDirectory(),
            'format' => 'Letter',
            'margin_left' => 0,
            'margin_right' => 0,
            'margin_top' => 0,
            'margin_bottom' => 0,
        ]);

        foreach ($files as $file) {
            $pageCount = $pdf->SetSourceFile($file);

            for ($page = 1; $page <= $pageCount; $page++) {
                // Import before AddPage so the imported page's own box is
                // what the new page is sized from — Chromium already laid
                // these out at Letter, this pass must not re-scale them.
                $template = $pdf->ImportPage($page);
                $pdf->AddPage();
                $pdf->UseTemplate($template);
            }
        }

        $pdf->Output($absolutePath, Destination::FILE);
    }

    /**
     * @param  array<int, array{label: string}>  $headers
     * @param  array<int, array<string, scalar|null>>  $rows
     */
    private function html(string $title, array $headers, array $rows, bool $continuation, int $rowOffset = 0): string
    {
        $started = microtime(true);

        try {
            // Blade indents every cell, which is ~300 bytes of whitespace per
            // row — two thirds of the 30MB a 45.000-row report produces. It
            // does not change what Chromium lays out (measured: no difference
            // in render time), but each chunk of it is JSON-encoded here,
            // pushed over HTTP and JSON-decoded in the sidecar, and that part
            // is pure transfer cost. Only collapses whitespace BETWEEN tags,
            // so no cell's text is touched.
            return preg_replace('/>\s+</', '><',
                $this->renderView($title, $headers, $rows, $continuation, $rowOffset)) ?? '';
        } finally {
            $this->htmlSeconds += microtime(true) - $started;
        }
    }

    /**
     * @param  array<int, array{label: string}>  $headers
     * @param  array<int, array<string, scalar|null>>  $rows
     */
    private function renderView(string $title, array $headers, array $rows, bool $continuation, int $rowOffset): string
    {
        return view('exports.table-pdf', [
            'title' => $title,
            'headers' => $headers,
            'rows' => $rows,
            'continuation' => $continuation,
            'rowOffset' => $rowOffset,
        ])->render();
    }

    private function temporaryDirectory(): string
    {
        $directory = storage_path('app/pdf-chunks');

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        return $directory;
    }
}
