<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Src\Shared\Host\Infrastructure\HostProfile;

/**
 * Prints what the application decided about this machine.
 *
 * The point of deriving the render pool from the host instead of hard-
 * coding it is that nobody has to edit a constant to move the project to
 * another computer. The cost of that is a number nobody typed, so it has
 * to be inspectable: this is the answer to "why is my export slower on
 * the server than on my laptop", and the first thing to paste into a bug
 * report.
 *
 * --json is for the checks that need the same numbers without parsing a
 * table (scripts/pdf-sidecar-check.mjs reads it to size its own budget).
 */
class HostProfileCommand extends Command
{
    protected $signature = 'host:profile {--json : Emit the profile as JSON and nothing else}';

    protected $description = 'Show the cores detected on this host and the render settings derived from them';

    public function handle(): int
    {
        $detected = HostProfile::probe();
        $cores = (int) config('host.cores');
        $concurrency = (int) config('host.render_concurrency');
        $reference = (int) config('host.reference_concurrency');
        $scale = (float) config('host.throughput_scale');

        if ($this->option('json')) {
            $this->line((string) json_encode([
                'cores' => $cores,
                'detected_cores' => $detected,
                'render_concurrency' => $concurrency,
                'reference_concurrency' => $reference,
                'throughput_scale' => round($scale, 2),
            ], JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $this->table(['setting', 'value', 'where it comes from'], [
            ['logical cores', (string) $cores, $cores === $detected
                ? 'detected on this host'
                : "HOST_CORES overrides the {$detected} detected"],
            ['concurrent renders', (string) $concurrency, 'one per physical core (threads ÷ 2), floor 2, ceiling 16'],
            ['reference pool', (string) $reference, 'the pool every published timing was measured at'],
            ['throughput scale', number_format($scale, 2).'×', 'how many more waves a batch takes here'],
        ]);

        $this->newLine();
        $this->line('Both halves of the chunked PDF export use the same pool:');
        $this->line('  ChunkedChromePdfWriter sends '.$concurrency.' chunk renders per wave');
        $this->line('  scripts/pdf-sidecar.mjs opens '.$concurrency.' Chromium pages at once');
        $this->newLine();
        $this->line('Pin either one with HOST_CORES or PDF_SIDECAR_CONCURRENCY in .env.');

        return self::SUCCESS;
    }
}
