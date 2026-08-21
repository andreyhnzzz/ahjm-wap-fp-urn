<?php

declare(strict_types=1);

namespace Tests\Unit\Shared;

use PHPUnit\Framework\TestCase;
use Src\Shared\Host\Infrastructure\HostProfile;

/**
 * The arithmetic that replaced two hardcoded 10s.
 *
 * Pure PHPUnit, no framework: HostProfile is plain PHP and reads nothing
 * from Laravel — the same rule the domain tests follow.
 *
 * The load-bearing case is the first one, and it is the one the whole
 * change turns on: the constant it replaced was 10, and on the machine
 * that 10 was chosen for, an A/B over 45,000 rows put 10 at 30.6s
 * against 12.3s for 6. The rule has to land on the measured optimum, not
 * reproduce the shipped guess — see HostProfile for the full sweep and
 * for why the answer is physical cores rather than logical ones.
 */
class HostProfileTest extends TestCase
{
    public function test_the_reference_machine_resolves_to_the_measured_optimum(): void
    {
        // 12 logical processors = 6 physical cores, and 6 is where the
        // sweep bottoms out (12.3s; the shipped constant of 10 measured
        // 30.6s on the same rows and the same machine state).
        $this->assertSame(6, HostProfile::renderConcurrency(12),
            'The reference machine must get the pool its own measurements put at the optimum.');
    }

    public function test_a_small_host_never_serializes_completely(): void
    {
        // A 2-vCPU runner would get 0 from cores - 2, which array_chunk()
        // and the sidecar's semaphore would both refuse, and which would
        // undo chunking's whole reason to exist.
        $this->assertSame(2, HostProfile::renderConcurrency(1));
        $this->assertSame(2, HostProfile::renderConcurrency(2));
        $this->assertSame(2, HostProfile::renderConcurrency(4));
        $this->assertSame(2, HostProfile::renderConcurrency(5));
        $this->assertSame(3, HostProfile::renderConcurrency(6));
    }

    public function test_a_large_host_stops_at_the_memory_ceiling_not_the_core_count(): void
    {
        // Every concurrent render holds a fresh Chromium page with a
        // chunk of DOM in it, so past this the binding constraint is RAM.
        $this->assertSame(16, HostProfile::renderConcurrency(64));
        $this->assertSame(16, HostProfile::renderConcurrency(32));
        $this->assertSame(15, HostProfile::renderConcurrency(30));
    }

    public function test_detection_returns_something_usable_on_this_host(): void
    {
        // Whatever machine runs the suite, the probe must produce a
        // positive count rather than 0 or null — every derived value
        // depends on it and the fallback exists precisely so a host that
        // refuses to answer still gets a working pool.
        $this->assertGreaterThanOrEqual(1, HostProfile::logicalCores());
    }

    public function test_throughput_scale_counts_waves_and_never_shrinks_a_budget(): void
    {
        // Same batch, a third of the pool, three times the waves — the
        // reference pool of 6 against a 4-vCPU runner's 2.
        $this->assertSame(3.0, HostProfile::throughputScale(6, 2));
        $this->assertSame(1.0, HostProfile::throughputScale(6, 6));

        // A bigger machine finishes sooner; it does not earn a shorter
        // timeout, which would only turn "fast" into "flaky".
        $this->assertSame(1.0, HostProfile::throughputScale(6, 16));
    }
}
