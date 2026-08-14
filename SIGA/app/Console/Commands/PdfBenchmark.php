<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Src\Shared\Export\Contracts\PdfFileWriterInterface;

/**
 * Phase-by-phase stopwatch for the PDF export pipeline, built to answer
 * one question the end-to-end wall clock cannot: *which* stage costs the
 * seconds. It walks the same four stages a real queued export walks —
 *
 *   1. queue   the job payload's json_encode/json_decode round trip
 *              (QUEUE_CONNECTION=database means every row travels
 *              through the jobs table as JSON before the worker sees it)
 *   2. blade   server-side rendering of exports.table-pdf into an HTML
 *              string — the SSR stage
 *   3. render  handing that HTML to PdfFileWriterInterface, i.e. Chrome
 *   4. bytes   the PDF that came out
 *
 * — against synthetic rows rather than the database, on purpose. The
 * seeder is deterministic and its group codes are load-bearing for the
 * risk-board scenarios; a benchmark that needed 10,000 seeded groups
 * would have to overwrite them (that exact mistake is on record). Rows
 * built in memory are also the only way to sweep 2.5k -> 10k on demand.
 *
 * The shape mirrors GroupComponent::exportHeaders() — nine columns plus
 * the index column, realistic string lengths — because HTML size per row
 * is what Chrome's layout cost scales with, not row count alone.
 *
 * Usage:
 *   php artisan pdf:bench --rows=2500 --runs=3
 *   php artisan pdf:bench --rows=2500,5000,10000 --runs=1 --json
 */
class PdfBenchmark extends Command
{
    protected $signature = 'pdf:bench
        {--rows=2500 : Row counts to measure, comma-separated (e.g. 2500,5000,10000)}
        {--runs=3 : Measured runs per row count; the median is reported}
        {--paper=letter : Paper size passed to the writer}
        {--keep : Keep the generated PDFs in storage/app/pdf-bench instead of deleting them}
        {--dump-html : Write the rendered HTML to storage/app/pdf-bench/bench-{rows}.html and exit without rendering}
        {--breakdown : Also split the sidecar round trip into upload / parse / Chrome / download}
        {--json : Emit one JSON line per measurement instead of a table}';

    protected $description = 'Measure the PDF export pipeline stage by stage at a given row count';

    public function handle(PdfFileWriterInterface $writer): int
    {
        $rowCounts = array_map(intval(...), explode(',', (string) $this->option('rows')));
        $runs = max(1, (int) $this->option('runs'));
        $paper = (string) $this->option('paper');

        $outDir = storage_path('app/pdf-bench');
        if (! is_dir($outDir)) {
            mkdir($outDir, 0755, true);
        }

        $this->line(sprintf(
            '<comment>engine:</comment> %s   <comment>sidecar:</comment> %s',
            $writer::class,
            $this->sidecarIsUp() ? '<info>up (warm)</info>' : '<error>down (cold)</error>',
        ));

        if ($this->option('dump-html')) {
            foreach ($rowCounts as $rowCount) {
                $path = "{$outDir}/bench-{$rowCount}.html";
                file_put_contents($path, $this->renderHtml($rowCount));
                $this->info("wrote {$path}");
            }

            return self::SUCCESS;
        }

        $results = [];

        foreach ($rowCounts as $rowCount) {
            $samples = [];

            for ($run = 1; $run <= $runs; $run++) {
                $this->output->write(sprintf("\r  %d rows, run %d/%d ...   ", $rowCount, $run, $runs));
                $samples[] = $this->measure($writer, $rowCount, $paper, "{$outDir}/bench-{$rowCount}.pdf");
            }

            $this->output->write("\r".str_repeat(' ', 46)."\r");

            // The first run is reported separately and never averaged in.
            // It is the only run that pays whatever the clean start costs
            // — launching the sidecar, or Browsershot's node+Chromium
            // spawn — and that is the number the requirement is about.
            // Folding it into a median hides exactly what we set out to
            // measure; keeping it out of one hides the steady state.
            $result = ($runs > 1 ? $this->median(array_slice($samples, 1)) : $samples[0]) + ['rows' => $rowCount];
            $result['cold_total_ms'] = $samples[0]['total_ms'];
            $result['cold_render_ms'] = $samples[0]['render_ms'];

            $results[] = $result;
        }

        if (! $this->option('keep')) {
            array_map(unlink(...), glob("{$outDir}/*.pdf") ?: []);
        }

        if ($this->option('json')) {
            foreach ($results as $result) {
                $this->line((string) json_encode($result));
            }

            return self::SUCCESS;
        }

        $this->newLine();
        $this->table(
            ['rows', 'queue ms', 'blade ms', 'render ms', 'WARM total', 'COLD total', 'html KB', 'pdf KB', 'peak MB'],
            array_map(static fn (array $r): array => [
                number_format($r['rows']),
                number_format($r['queue_ms'], 0),
                number_format($r['blade_ms'], 0),
                number_format($r['render_ms'], 0),
                '<options=bold>'.number_format($r['total_ms'], 0).'</>',
                '<options=bold>'.number_format($r['cold_total_ms'], 0).'</>',
                number_format($r['html_bytes'] / 1024, 0),
                number_format($r['pdf_bytes'] / 1024, 0),
                number_format($r['peak_mem_bytes'] / 1_048_576, 0),
            ], $results),
        );
        $this->line('  <comment>COLD</comment> = first run of the process (pays the clean start).  <comment>WARM</comment> = median of the rest.');

        if ($this->option('breakdown')) {
            $this->renderBreakdown($rowCounts, $paper);
        }

        return self::SUCCESS;
    }

    /**
     * @return array{queue_ms: float, blade_ms: float, render_ms: float, total_ms: float, html_bytes: int, pdf_bytes: int, peak_mem_bytes: int}
     */
    private function measure(PdfFileWriterInterface $writer, int $rowCount, string $paper, string $path): array
    {
        $headers = $this->headers();
        $rows = $this->syntheticRows($rowCount);

        gc_collect_cycles();

        // Stage 1 — what QUEUE_CONNECTION=database does to the payload on
        // its way to the worker. Not a simulation of Laravel's serializer;
        // it is the same json_encode/json_decode pair that serializer runs.
        $queueStart = hrtime(true);
        $payload = json_encode(['title' => 'Groups', 'headers' => $headers, 'rows' => $rows]);
        /** @var array{title: string, headers: array<int, array{label: string}>, rows: array<int, array<string, scalar|null>>} $decoded */
        $decoded = json_decode((string) $payload, true);
        $queueMs = (hrtime(true) - $queueStart) / 1_000_000;

        // Stage 2 — SSR.
        $bladeStart = hrtime(true);
        $html = view('exports.table-pdf', [
            'title' => $decoded['title'],
            'headers' => $decoded['headers'],
            'rows' => $decoded['rows'],
        ])->render();
        $bladeMs = (hrtime(true) - $bladeStart) / 1_000_000;

        // Stage 3 — Chrome.
        $renderStart = hrtime(true);
        $writer->write($html, $path, $paper);
        $renderMs = (hrtime(true) - $renderStart) / 1_000_000;

        return [
            'queue_ms' => $queueMs,
            'blade_ms' => $bladeMs,
            'render_ms' => $renderMs,
            'total_ms' => $queueMs + $bladeMs + $renderMs,
            'html_bytes' => strlen($html),
            'pdf_bytes' => is_file($path) ? (int) filesize($path) : 0,
            'peak_mem_bytes' => memory_get_peak_usage(true),
        ];
    }

    /**
     * Splits the sidecar round trip using the Server-Timing header it
     * reports, so "render is slow" can be attributed: shipping the HTML
     * up, Chrome laying it out, or shipping the PDF back down. This talks
     * to the sidecar directly rather than through the writer — a
     * benchmark measuring transport has to be able to see the transport.
     *
     * @param  array<int, int>  $rowCounts
     */
    private function renderBreakdown(array $rowCounts, string $paper): void
    {
        if (! $this->sidecarIsUp()) {
            $this->warn('  --breakdown needs the sidecar running (npm run pdf:sidecar); skipped.');

            return;
        }

        $rows = [];

        foreach ($rowCounts as $rowCount) {
            $html = $this->renderHtml($rowCount);

            $start = hrtime(true);
            $response = Http::timeout(300)
                ->withBody($html, 'text/html')
                ->post(sprintf(
                    'http://127.0.0.1:%d/pdf?format=%s&tagged=%s',
                    config('exports.pdf.sidecar.port'),
                    $paper,
                    config('exports.pdf.tagged') ? '1' : '0',
                ));
            $roundTripMs = (hrtime(true) - $start) / 1_000_000;

            // Server-Timing: read;dur=..., wait;dur=..., parse;dur=..., render;dur=...
            preg_match_all('/(\w+);dur=([\d.]+)/', $response->header('Server-Timing'), $matches, PREG_SET_ORDER);
            $timing = array_column($matches, 2, 1);
            $serverMs = array_sum(array_map(floatval(...), $timing));

            $rows[] = [
                number_format($rowCount),
                number_format((float) ($timing['read'] ?? 0), 0),
                number_format((float) ($timing['render'] ?? 0), 0),
                number_format($roundTripMs - $serverMs, 0),
                number_format($roundTripMs, 0),
                number_format(strlen($html) / 1024, 0),
                number_format(strlen($response->body()) / 1024, 0),
            ];
        }

        $this->newLine();
        $this->line('<comment>sidecar round trip</comment>');
        $this->table(
            ['rows', 'upload ms', 'chrome ms', 'client+download ms', 'round trip ms', 'sent KB', 'received KB'],
            $rows,
        );
    }

    private function renderHtml(int $rowCount): string
    {
        return view('exports.table-pdf', [
            'title' => 'Groups',
            'headers' => $this->headers(),
            'rows' => $this->syntheticRows($rowCount),
        ])->render();
    }

    /**
     * Same nine columns GroupComponent exports, so column count and
     * cell widths match what production actually asks Chrome to lay out.
     *
     * @return array<int, array{label: string}>
     */
    private function headers(): array
    {
        return array_map(static fn (string $label): array => ['label' => $label], [
            'Código de grupo', 'Código de curso', 'Cuatrimestre', 'Docente', 'Aula',
            'Matrícula estimada', 'Jornada asignada', 'Modalidad', 'Estado',
        ]);
    }

    /**
     * Deterministic (no faker, same rationale as AcademicDataSeeder): two
     * runs must produce byte-identical HTML, or a render-time difference
     * could be a difference in the data instead of in the code.
     *
     * @return array<int, array<string, string>>
     */
    private function syntheticRows(int $rowCount): array
    {
        $teachers = ['Ana Lucía Rodríguez Vargas', 'Carlos Eduardo Jiménez Mora', 'María Fernanda Solís Araya', 'José Antonio Chaves Ureña', 'Sin asignar'];
        $classrooms = ['Aula A-101', 'Aula B-204', 'Laboratorio C-3', 'Auditorio Principal', 'Sin asignar'];
        $modalities = ['Presencial', 'Virtual', 'Bimodal'];
        $statuses = ['Activo', 'Cancelado', 'Planificado'];
        $terms = ['2026-I', '2026-II'];

        $rows = [];

        for ($i = 0; $i < $rowCount; $i++) {
            $rows[] = [
                'Código de grupo' => sprintf('ISW-%03d-G%02d', $i % 400, $i % 9 + 1),
                'Código de curso' => sprintf('ISW-%03d', $i % 400),
                'Cuatrimestre' => $terms[$i % 2],
                'Docente' => $teachers[$i % 5],
                'Aula' => $classrooms[$i % 5],
                'Matrícula estimada' => (string) (5 + $i % 40),
                'Jornada asignada' => number_format(($i % 4 + 1) * 0.25, 2),
                'Modalidad' => $modalities[$i % 3],
                'Estado' => $statuses[$i % 3],
            ];
        }

        return $rows;
    }

    /**
     * Median, not mean: one antivirus hiccup or one Chrome relaunch would
     * drag a mean far enough to hide a real regression.
     *
     * @param  array<int, array<string, float|int>>  $samples
     * @return array<string, float|int>
     */
    private function median(array $samples): array
    {
        $median = [];

        foreach (array_keys($samples[0]) as $key) {
            $values = array_column($samples, $key);
            sort($values);
            $median[$key] = $values[intdiv(count($values), 2)];
        }

        return $median;
    }

    private function sidecarIsUp(): bool
    {
        $socket = @fsockopen('127.0.0.1', (int) config('exports.pdf.sidecar.port'), $errno, $errstr, 0.5);

        if ($socket === false) {
            return false;
        }

        fclose($socket);

        return true;
    }
}
