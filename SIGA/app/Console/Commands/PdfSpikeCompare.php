<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\GenerateReportExportJob;
use App\Models\Permission;
use App\Models\Role as RoleModel;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Process;
use Spatie\Browsershot\Browsershot;
use Spatie\LaravelPdf\Facades\Pdf;
use Src\Academic\Group\Presentation\Livewire\GroupComponent;
use Src\IdentityAccess\Role\Presentation\Livewire\RoleComponent;
use Src\Shared\Export\Infrastructure\BrowsershotConfiguration;

/**
 * SPIKE — not production code. Throwaway comparison of mPDF/Dompdf/Spatie
 * against the exact HTML this app already generates in production, to
 * evaluate replacing Spatie/Browsershot's Chromium dependency.
 *
 * Each (dataset, engine) pair runs in its own subprocess: memory can't be
 * reliably measured for one engine in a process that already rendered
 * with another (allocator doesn't return freed memory to the OS, so a
 * later "delta" would be contaminated by the previous engine's leftovers),
 * and one engine hitting an unrecoverable OOM fatal must not take the rest
 * of the run down with it.
 *
 * Orchestrator mode (no options): shells out to itself once per
 * (dataset, engine) pair and prints the combined table.
 * Worker mode (--engine=X --dataset=Y): renders exactly one pair via the
 * real production HTML for that component (captured through the actual
 * exportPdf() call, so headers/rows/escaping match production exactly —
 * zero duplicated row-mapping logic) and prints one JSON line.
 */
class PdfSpikeCompare extends Command
{
    protected $signature = 'spike:pdf-compare
        {--engine= : Worker mode — mpdf|dompdf|spatie-warm-sidecar|spatie-browsershot-cold}
        {--dataset= : Worker mode — roles-small|groups-large}
        {--dump-html : Worker mode — write the captured HTML to storage/app/spike-pdf/{dataset}.html and exit, skip rendering}';

    protected $description = 'SPIKE: compare mPDF, Dompdf and Spatie/Browsershot on real report HTML';

    private const ENGINES = ['mpdf', 'dompdf', 'spatie-warm-sidecar', 'spatie-browsershot-cold'];

    private const DATASETS = ['roles-small', 'groups-large'];

    private ?User $spikeUser = null;

    public function handle(): int
    {
        return $this->option('engine') !== null || $this->option('dump-html')
            ? $this->runWorker($this->option('engine'), $this->option('dataset'))
            : $this->runOrchestrator();
    }

    private function runOrchestrator(): int
    {
        $outDir = storage_path('app/spike-pdf');
        if (! is_dir($outDir)) {
            mkdir($outDir, 0755, true);
        }

        $phpBinary = (new \Symfony\Component\Process\PhpExecutableFinder())->find();
        $results = [];

        foreach (self::DATASETS as $dataset) {
            foreach (self::ENGINES as $engine) {
                $this->info("Running {$dataset} / {$engine} ...");

                $wallStart = hrtime(true);

                try {
                    $process = Process::path(base_path())
                        ->timeout(300)
                        ->run([
                            $phpBinary, '-d', 'memory_limit=1024M', 'artisan', 'spike:pdf-compare',
                            "--engine={$engine}", "--dataset={$dataset}",
                        ]);
                } catch (\Illuminate\Process\Exceptions\ProcessTimedOutException) {
                    $results[] = [
                        'dataset' => $dataset, 'engine' => $engine,
                        'wall_ms' => (hrtime(true) - $wallStart) / 1_000_000,
                        'error' => 'TIMEOUT (>300s)',
                    ];

                    continue;
                }

                $wallMs = (hrtime(true) - $wallStart) / 1_000_000;

                $row = ['dataset' => $dataset, 'engine' => $engine, 'wall_ms' => $wallMs];

                if (! $process->successful()) {
                    $row['error'] = trim(substr($process->errorOutput() ?: $process->output(), -160));
                    $results[] = $row;

                    continue;
                }

                $decoded = json_decode(trim($process->output()), true);

                if (! is_array($decoded)) {
                    $row['error'] = 'worker printed non-JSON output: '.substr($process->output(), 0, 160);
                    $results[] = $row;

                    continue;
                }

                $results[] = array_merge($row, $decoded);

                if (isset($decoded['pdf_base64'])) {
                    file_put_contents("{$outDir}/{$dataset}__{$engine}.pdf", base64_decode($decoded['pdf_base64']));
                }
            }
        }

        $this->newLine();
        $this->table(
            ['dataset', 'engine', 'wall_ms', 'render_ms', 'peak_mem_mb', 'output_kb', 'rows', 'error'],
            array_map(fn (array $r) => [
                $r['dataset'], $r['engine'],
                number_format($r['wall_ms'], 0),
                isset($r['render_ms']) ? number_format($r['render_ms'], 1) : '-',
                isset($r['peak_mem_bytes']) ? number_format($r['peak_mem_bytes'] / 1_048_576, 1) : '-',
                isset($r['pdf_bytes']) ? number_format($r['pdf_bytes'] / 1024, 1) : '-',
                $r['row_count'] ?? '-',
                $r['error'] ?? '',
            ], $results),
        );

        $this->info("PDFs written to: {$outDir}");

        return self::SUCCESS;
    }

    /**
     * Runs exactly one (dataset, engine) pair in this process and prints
     * one JSON line to stdout — everything else must go to stderr so the
     * orchestrator's json_decode() of stdout stays clean.
     */
    private function runWorker(?string $engine, ?string $dataset): int
    {
        if ($dataset === null || ! in_array($dataset, self::DATASETS, true)) {
            fwrite(STDERR, "invalid --dataset\n");

            return self::FAILURE;
        }

        try {
            $this->actingAsSuperadmin();

            $html = $this->captureHtml($dataset === 'roles-small' ? RoleComponent::class : GroupComponent::class);
            $rowCount = substr_count($html, 'class="row-index"');

            if ($this->option('dump-html')) {
                $outDir = storage_path('app/spike-pdf');
                if (! is_dir($outDir)) {
                    mkdir($outDir, 0755, true);
                }
                file_put_contents("{$outDir}/{$dataset}.html", $html);
                $this->spikeUser?->delete();

                return self::SUCCESS;
            }

            if (! in_array($engine, self::ENGINES, true)) {
                fwrite(STDERR, "invalid --engine\n");
                $this->spikeUser?->delete();

                return self::FAILURE;
            }

            gc_collect_cycles();
            $start = hrtime(true);

            $bytes = match ($engine) {
                'mpdf' => $this->renderMpdf($html),
                'dompdf' => $this->renderDompdf($html),
                'spatie-warm-sidecar' => $this->renderSpatieWarm($html),
                'spatie-browsershot-cold' => $this->renderSpatieBrowsershotDirect($html),
            };

            $renderMs = (hrtime(true) - $start) / 1_000_000;

            $this->spikeUser?->delete();

            fwrite(STDOUT, json_encode([
                'render_ms' => $renderMs,
                'peak_mem_bytes' => memory_get_peak_usage(true),
                'pdf_bytes' => strlen($bytes),
                'row_count' => $rowCount,
                'pdf_base64' => base64_encode($bytes),
            ]));

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->spikeUser?->delete();
            fwrite(STDERR, $e->getMessage()."\n".$e->getTraceAsString());

            return self::FAILURE;
        }
    }

    private function renderMpdf(string $html): string
    {
        // Production's WriteHTML() call has no override for this, so the
        // default 1,000,000-char PCRE backtrack limit is a real ceiling
        // this engine would also hit in production on a table this size
        // — raised here only to get past it and measure what's on the
        // other side, not because it's free.
        ini_set('pcre.backtrack_limit', '20000000');

        $mpdf = new \Mpdf\Mpdf(['tempDir' => sys_get_temp_dir()]);
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', \Mpdf\Output\Destination::STRING_RETURN);
    }

    private function renderDompdf(string $html): string
    {
        $dompdf = new \Dompdf\Dompdf(['isRemoteEnabled' => false]);
        $dompdf->setPaper('letter');
        $dompdf->loadHtml($html);
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * Bypasses WarmChromePdfRenderer's production 15s HTTP timeout —
     * tuned for normal report sizes — so the 1541-row dataset gets to
     * finish instead of being cut off and reported as "unreachable".
     * Same endpoint, same sidecar, longer patience.
     */
    private function renderSpatieWarm(string $html): string
    {
        $response = \Illuminate\Support\Facades\Http::connectTimeout(1)
            ->timeout(90)
            ->post('http://127.0.0.1:8720/pdf', ['html' => $html, 'format' => 'letter']);

        if (! $response->successful()) {
            throw new \RuntimeException('warm sidecar not reachable or errored: '.$response->status());
        }

        return $response->body();
    }

    private function renderSpatieBrowsershotDirect(string $html): string
    {
        return Pdf::html($html)
            ->format('letter')
            ->withBrowsershot(static function (Browsershot $browsershot): void {
                BrowsershotConfiguration::apply($browsershot);
                // Same reasoning as renderSpatieWarm(): production's 15s
                // Browsershot timeout is tuned for normal-sized reports.
                $browsershot->timeout(90);
            })
            ->generatePdfContent();
    }

    /**
     * Calls the real component's exportPdf() (same authorize()/use-case/
     * row-mapping code path production uses) with the queue faked,
     * grabs the dispatched GenerateReportExportJob's payload, and renders
     * the same view the job renders — the exact HTML production's worker
     * hands to Chrome.
     */
    private function captureHtml(string $componentClass): string
    {
        \Illuminate\Support\Facades\Queue::fake([GenerateReportExportJob::class]);

        $component = app()->make($componentClass);
        app()->call([$component, 'mount']);
        app()->call([$component, 'exportPdf']);

        $job = null;
        \Illuminate\Support\Facades\Queue::assertPushed(GenerateReportExportJob::class, function (GenerateReportExportJob $pushed) use (&$job): bool {
            $job = $pushed;

            return true;
        });

        if ($job === null) {
            throw new \RuntimeException("exportPdf on {$componentClass} never queued the export job");
        }

        return view('exports.table-pdf', [
            'title' => $job->title,
            'headers' => $job->headers,
            'rows' => $job->rows,
        ])->render();
    }

    private function actingAsSuperadmin(): void
    {
        $role = RoleModel::query()->firstOrCreate(['name' => 'Superadmin'], ['protected' => true]);
        $role->permissions()->sync(Permission::query()->pluck('id'));

        $user = User::factory()->create();
        $user->roles()->sync([$role->id]);

        $this->spikeUser = $user;
        Auth::login($user);
    }
}
