<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Src\Shared\Export\Contracts\PdfFileWriterInterface;
use Src\Shared\Export\Contracts\TabularPdfWriterInterface;

/**
 * Times a large PDF export stage by stage, so an optimisation can be aimed
 * instead of guessed.
 *
 * The mPDF/Dompdf/Spatie spike (D-09) answered "which engine", but not
 * "where does the time actually go" — and at 45k rows those are different
 * questions. This splits the pipeline into the three stages that can each
 * dominate at scale:
 *
 *   1. fetch    reading the rows out of the database
 *   2. html     turning them into the HTML string
 *   3. render   Chromium laying that out and writing the PDF
 *
 * Run with --rows to compare shapes of the curve; a stage that grows
 * linearly and one that grows quadratically need opposite fixes.
 */
final class PdfScaleBenchmark extends Command
{
    protected $signature = 'bench:pdf
        {--rows=45000 : how many rows to render}
        {--seed : bulk-insert enough groups to reach --rows first}
        {--dump= : write the generated HTML here and stop before rendering}
        {--keep : leave the generated PDF on disk for inspection}';

    protected $description = 'Time a large PDF export stage by stage (fetch / html / render).';

    public function handle(PdfFileWriterInterface $writer): int
    {
        $target = (int) $this->option('rows');

        if ($this->option('seed')) {
            $this->seedTo($target);
        }

        $available = DB::table('groups')->count();
        if ($available < $target) {
            $this->warn("Only {$available} groups in the database; run again with --seed.");
            $target = $available;
        }

        // ── 1. fetch ────────────────────────────────────────────────
        $t = microtime(true);
        $rows = $this->fetch($target);
        $fetch = microtime(true) - $t;
        $this->line(sprintf('  fetch   %7.2fs   %d rows', $fetch, count($rows)));

        // ── 2. el camino real: el escritor troceado ─────────────────
        if (! $this->option('dump')) {
            $path = storage_path('app/bench-'.$target.'.pdf');
            $t = microtime(true);
            app(TabularPdfWriterInterface::class)
                ->write('Grupos', $this->headers(), $rows, $path, 'letter');
            $write = microtime(true) - $t;
            $bytes = $this->sizeOf($path);

            $this->line(sprintf('  escribir %6.2fs   %s pdf', $write, $this->size($bytes)));
            $this->newLine();
            $this->line(sprintf('  TOTAL   %7.2fs   para %d filas', $fetch + $write, $target));
            $this->line(sprintf('  memoria pico: %s', $this->size(memory_get_peak_usage(true))));

            if (! $this->option('keep') && is_file($path)) {
                unlink($path);
            }

            return self::SUCCESS;
        }

        $t = microtime(true);
        $html = view('exports.table-pdf', [
            'title' => 'Grupos',
            'headers' => $this->headers(),
            'rows' => $rows,
        ])->render();
        $html_s = microtime(true) - $t;
        $this->line(sprintf('  html    %7.2fs   %s', $html_s, $this->size(strlen($html))));

        if ($dump = $this->option('dump')) {
            file_put_contents($dump, $html);
            $this->line("  dumped to {$dump}");

            return self::SUCCESS;
        }

        // ── 3. render ───────────────────────────────────────────────
        $path = storage_path('app/bench-'.$target.'.pdf');
        $t = microtime(true);
        $writer->write($html, $path, 'letter');
        $render = microtime(true) - $t;
        $this->line(sprintf('  render  %7.2fs   %s pdf', $render, $this->size($this->sizeOf($path))));

        $total = $fetch + $html_s + $render;
        $this->newLine();
        $this->line(sprintf('  TOTAL   %7.2fs   for %d rows', $total, $target));
        $this->line(sprintf('  peak memory: %s', $this->size(memory_get_peak_usage(true))));

        if (! $this->option('keep') && is_file($path)) {
            unlink($path);
        }

        return self::SUCCESS;
    }

    /** @return array<int, array<string, scalar|null>> */
    private function fetch(int $limit): array
    {
        // Mirrors what InteractsWithExports hands the job today: a plain
        // array of label-keyed display strings, already joined.
        return DB::table('groups')
            ->leftJoin('teachers', 'groups.teacher_id', '=', 'teachers.id')
            ->leftJoin('classrooms', 'groups.classroom_id', '=', 'classrooms.id')
            ->select([
                'groups.code', 'groups.course_code', 'groups.term',
                'teachers.name as teacher', 'classrooms.name as classroom',
                'groups.modality', 'groups.status',
            ])
            ->limit($limit)
            ->get()
            ->map(static fn ($r): array => [
                'Código' => $r->code,
                'Curso' => $r->course_code,
                'Cuatrimestre' => $r->term,
                'Docente' => $r->teacher ?? '—',
                'Aula' => $r->classroom ?? '—',
                'Modalidad' => $r->modality,
                'Estado' => $r->status,
            ])
            ->all();
    }

    /** @return array<int, array{label: string}> */
    private function headers(): array
    {
        return array_map(
            static fn (string $l): array => ['label' => $l],
            ['Código', 'Curso', 'Cuatrimestre', 'Docente', 'Aula', 'Modalidad', 'Estado'],
        );
    }

    private function seedTo(int $target): void
    {
        $have = DB::table('groups')->count();
        if ($have >= $target) {
            $this->line("  already {$have} groups, nothing to seed");

            return;
        }

        $teacherIds = DB::table('teachers')->pluck('id')->all();
        $classroomIds = DB::table('classrooms')->pluck('id')->all();
        $terms = ['2026-I', '2026-II', '2027-I'];
        $modalities = ['presencial', 'virtual', 'hibrida'];
        $statuses = ['abierto', 'cerrado', 'planificado'];
        $now = now()->toDateTimeString();

        $missing = $target - $have;
        $this->line("  seeding {$missing} groups...");
        $bar = $this->output->createProgressBar((int) ceil($missing / 1000));

        for ($done = 0; $done < $missing; $done += 1000) {
            $batch = [];
            $chunk = min(1000, $missing - $done);
            for ($i = 0; $i < $chunk; $i++) {
                $n = $have + $done + $i + 1;
                // Every 11th group has no teacher and every 17th no classroom:
                // the RE-04 risk cases have to survive at scale too, otherwise
                // the benchmark renders a table the dashboard would never see.
                $batch[] = [
                    'code' => 'G'.str_pad((string) $n, 6, '0', STR_PAD_LEFT),
                    'course_code' => 'ISW-'.(100 + ($n % 400)),
                    'term' => $terms[$n % 3],
                    'teacher_id' => $n % 11 === 0 ? null : $teacherIds[$n % count($teacherIds)],
                    'classroom_id' => $n % 17 === 0 ? null : $classroomIds[$n % count($classroomIds)],
                    'estimated_enrollment' => $n % 23 === 0 ? 3 : 12 + ($n % 25),
                    'assigned_workload' => round(0.1 + ($n % 5) * 0.1, 2),
                    'modality' => $modalities[$n % 3],
                    'status' => $statuses[$n % 3],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            DB::table('groups')->insert($batch);
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();
    }

    /**
     * Bytes on disk, or 0. filesize() returns false on a missing or
     * unreadable file, and a benchmark reporting "false KB" would be a
     * worse outcome than reporting nothing.
     */
    private function sizeOf(string $path): int
    {
        if (! is_file($path)) {
            return 0;
        }

        $bytes = filesize($path);

        return $bytes === false ? 0 : $bytes;
    }

    private function size(int $bytes): string
    {
        return $bytes > 1048576
            ? sprintf('%.1f MB', $bytes / 1048576)
            : sprintf('%.0f KB', $bytes / 1024);
    }
}
