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
 * WHY, measured on this machine (12 cores, warm chrome-headless-shell,
 * `php artisan bench:pdf` + `node scripts/chrome-bench.mjs`):
 *
 *   filas    printToPDF   ms/fila
 *    1.000       1.47s      1.47
 *    2.000       2.28s      1.14
 *    5.000       6.60s      1.32
 *   15.000      49.55s      3.30      <- se degrada
 *   45.000       FALLA                <- "Protocol error: Printing failed"
 *
 * Chromium is linear-ish to about five thousand rows and then collapses;
 * past that it does not merely get slow, it refuses. So chunking is not a
 * tuning knob here, it is the only way 45.000 rows come out at all. CSS
 * micro-optimisation was measured first and does not move this: dropping
 * the dotted gradient, the nth-child striping and the rounded card
 * together took 5.000 rows from 11.5s to 10.6s, while the row count is
 * what decides whether the render finishes.
 *
 * The pieces, and what each costs at 45.000 rows:
 *
 *   1. chunk        rows sliced into CHUNK_ROWS documents
 *   2. render       PARALLEL_REQUESTS at a time against the warm sidecar   13.1s
 *   3. merge        mPDF imports every page and writes one file            4.6s
 *                                                                   total ~19s
 *
 * mPDF is already installed (it arrived with the D-09 engine spike that
 * *rejected* it as a renderer) so the merge costs no new dependency. It
 * also recompresses on the way out: the 23 chunk files weigh 127MB
 * together and the merged result 13.7MB, with a 68MB peak in PHP.
 *
 * Below CHUNK_THRESHOLD rows this delegates to the plain one-document
 * writer, so every existing report keeps rendering exactly as before —
 * same Chromium, same template, byte-for-byte the same output.
 */
final class ChunkedChromePdfWriter implements TabularPdfWriterInterface
{
    /**
     * Rows per chunk. 2.000 sits at the bottom of the ms/row curve above
     * (1.14ms/row) — smaller chunks pay Chromium's fixed per-document cost
     * more often, larger ones start climbing back up the curve.
     */
    private const CHUNK_ROWS = 2000;

    /**
     * Concurrent renders. Chromium layout is CPU-bound, so this tracks
     * cores rather than I/O — measured on a 12-core machine, six pages
     * gave 19.9s of render and ten gave 17.4s, leaving two cores for the
     * web workers sharing the box.
     *
     * The sidecar has to be able to serve them: it keeps a pool of
     * PDF_SIDECAR_PAGES pages (default 10, deliberately the same number)
     * and anything beyond that queues. Change them together, or the extra
     * requests here just wait — silently, and only under load.
     */
    private const PARALLEL_REQUESTS = 10;

    /**
     * Under this, one document is both faster (no merge pass) and safer
     * (no temp files). 4.000 is comfortably inside the linear region.
     */
    private const CHUNK_THRESHOLD = 4000;

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

        // In waves rather than all at once: 23 concurrent renders would
        // put every chunk's HTML in flight simultaneously (~32MB) and
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

            $responses = Http::pool(function (Pool $pool) use ($bodies, $paperSize): array {
                $requests = [];

                foreach ($bodies as $index => $html) {
                    $requests[] = $pool->as((string) $index)
                        ->timeout(self::CHUNK_TIMEOUT)
                        ->post(WarmChromePdfRenderer::endpoint(), [
                            'html' => $html,
                            'format' => $paperSize,
                        ]);
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
