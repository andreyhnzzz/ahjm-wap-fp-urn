<?php

declare(strict_types=1);

namespace Tests\Feature\Host;

use Illuminate\Support\Facades\Process;
use Src\Shared\Export\Infrastructure\ChunkedChromePdfWriter;
use Src\Shared\Host\Infrastructure\HostProfile;
use Tests\TestCase;

/**
 * The rule for how many renders may run at once lives twice: once in
 * PHP (HostProfile, read through config/host.php by
 * ChunkedChromePdfWriter) and once in JavaScript (scripts/host-profile.mjs,
 * read by the sidecar). Duplicated on purpose — the sidecar exists to
 * avoid paying startup costs and must not boot PHP to learn its own pool
 * size — which makes drift between the two copies the obvious way for
 * this to rot.
 *
 * Drift is also invisible in production: if PHP sends more chunk renders
 * per wave than the sidecar will serve at once, nothing errors, the
 * surplus simply queues inside the sidecar and every large export gets
 * slower. That is exactly the class of bug that survives for months, so
 * it gets a test rather than a comment asking people to remember.
 */
class HostProfileParityTest extends TestCase
{
    /** Core counts spanning both clamps and the reference machine. */
    private const CORE_COUNTS = [1, 2, 4, 5, 8, 12, 16, 18, 64];

    public function test_php_and_the_sidecar_derive_the_same_pool_from_the_same_cores(): void
    {
        $module = 'file:///'.str_replace(DIRECTORY_SEPARATOR, '/', base_path('scripts/host-profile.mjs'));
        $cores = json_encode(self::CORE_COUNTS);

        $result = Process::env([
            // Either variable would short-circuit the derivation being
            // compared, and both may legitimately be set on the machine
            // running the suite.
            'HOST_CORES' => '',
            'PDF_SIDECAR_CONCURRENCY' => '',
        ])->run([
            'node',
            '--input-type=module',
            '-e',
            "import { renderConcurrency } from '{$module}';".
            "console.log(JSON.stringify({$cores}.map((n) => renderConcurrency(n))));",
        ]);

        if (! $result->successful()) {
            $this->markTestSkipped('node is not available to compare the sidecar-side rule: '.trim($result->errorOutput()));
        }

        $fromNode = json_decode(trim($result->output()), true);

        $this->assertIsArray($fromNode, 'The sidecar-side rule did not print a JSON array.');

        $fromPhp = array_map(
            static fn (int $cores): int => HostProfile::renderConcurrency($cores),
            self::CORE_COUNTS,
        );

        $this->assertSame($fromPhp, $fromNode,
            'scripts/host-profile.mjs and HostProfile disagree about the render pool. '.
            'Whichever one changed, the other has to change with it — PHP sends the requests the sidecar serves.');
    }

    public function test_the_configuration_exposes_the_derived_values(): void
    {
        // An .env that pins either value is a legitimate configuration,
        // not a broken one — the point of this test is that the DEFAULT
        // is the derivation rather than a constant, so a machine that has
        // opted out of the derivation has nothing to say here.
        if (env('HOST_CORES') || env('PDF_SIDECAR_CONCURRENCY')) {
            $this->markTestSkipped('This host pins its own core count or render pool in .env.');
        }

        $this->assertSame(HostProfile::logicalCores(), config('host.cores'));
        $this->assertSame(HostProfile::renderConcurrency(), config('host.render_concurrency'));
        $this->assertGreaterThanOrEqual(1.0, config('host.throughput_scale'));
    }

    public function test_the_export_writer_uses_the_hosts_pool_rather_than_a_constant(): void
    {
        config()->set('host.render_concurrency', 4);

        $writer = $this->app->make(ChunkedChromePdfWriter::class);

        $this->assertSame(4, $writer->parallelRequests(),
            'The writer resolved its wave size at construction from something other than the host profile.');
    }

    public function test_the_profile_command_reports_the_same_numbers(): void
    {
        $this->artisan('host:profile --json')
            ->expectsOutputToContain('"render_concurrency": '.config('host.render_concurrency'))
            ->assertSuccessful();
    }
}
