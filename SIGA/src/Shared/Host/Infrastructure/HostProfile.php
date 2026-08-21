<?php

declare(strict_types=1);

namespace Src\Shared\Host\Infrastructure;

/**
 * What this host can actually do, so the numbers that used to be typed in
 * by hand stop being a property of one developer's machine.
 *
 * The PDF pipeline has one genuinely CPU-bound knob: how many chunk
 * renders run at once (ChunkedChromePdfWriter on the PHP side,
 * PDF_SIDECAR_CONCURRENCY on the sidecar's). Both were fixed at 10, and
 * both files carried a comment saying the number "tracks cores" while
 * doing nothing of the sort.
 *
 * Deriving it started as a portability fix — ten Chromium layouts
 * fighting over a CI runner's four cores is not what that 10 meant. The
 * first honest A/B of the derived rule against the constant it replaced
 * found something else: on the very machine the 10 was picked for, 10 is
 * close to the worst value available. 45,000 rows, same machine state,
 * medians over 3-4 runs each:
 *
 *   pool     3      4      5      6      7      8     10     12
 *   total  18.9s  16.2s  17.2s  12.3s  12.2s  31.1s  30.6s  26.5s
 *
 * Same work, 2.5x between the best and the shipped value. The shape is
 * the giveaway: the cliff is not at 12 logical processors, it is at 8 —
 * and that machine is 6 physical cores with SMT. A Chromium layout is
 * already internally parallel and memory-bound; a second render on the
 * sibling hyperthread does not get a second core's worth of work done,
 * it gets in the way. What the pool tracks is PHYSICAL cores.
 *
 * The isolating comparison, because chunk size moves with the pool and
 * could have explained it instead: pool 6 and pool 12 both split 45,000
 * rows into 12 chunks of 3,750 — 12.3s against 26.5s. Pools 2, 4 and 8
 * all produce 8 chunks of 5,625 — 24.8s, 16.2s, 31.1s. Concurrency, not
 * chunking.
 *
 * scripts/host-profile.mjs implements the same rule for the Node side;
 * HostProfileParityTest pins that the two never drift apart.
 */
final class HostProfile
{
    /**
     * Logical processors per physical core, assumed rather than probed.
     *
     * The number that matters is physical cores, and asking the OS for it
     * is cheap on Linux (/proc/cpuinfo) and macOS (sysctl) but costs a
     * WMI query on Windows — a process spawn on a path that a queue
     * worker hits per export, to refine a number whose error is bounded
     * at 2x. Every x86 host this project runs on has SMT enabled, so
     * halving is exact there; the hosts where it is wrong (SMT disabled,
     * Intel E-cores, most ARM server parts) get HALF the pool they could
     * use, and the measurements above say that is the cheap direction to
     * be wrong in: pool 3 costs 54% over the optimum, pool 8 costs 150%.
     * Under-subscribing is a slowdown, over-subscribing is a cliff.
     *
     * HOST_CORES pins the input, PDF_SIDECAR_CONCURRENCY pins the output,
     * for a host that knows better than this assumption.
     */
    private const THREADS_PER_CORE = 2;

    /**
     * Never serialize completely. One slot would turn a chunked export
     * back into the fully sequential render that chunking exists to
     * avoid (measured: 17s of real work stretched to 63s of waiting).
     */
    private const MINIMUM_CONCURRENCY = 2;

    /**
     * Past this, the limit stops being CPU: every concurrent render is a
     * fresh Chromium page holding a chunk's worth of DOM, so a 64-thread
     * host would run itself out of memory long before it ran out of
     * cores. Nothing above 12 logical processors has been measured here,
     * so the cap is an honest "untested beyond this", not a measured
     * optimum.
     */
    private const MAXIMUM_CONCURRENCY = 16;

    /** Used only when the host refuses to say. Yields the minimum pool. */
    private const FALLBACK_CORES = 4;

    /**
     * The pool the project's published chunked-export timings were taken
     * at: the reference machine's own resolved value (12 threads -> 6).
     */
    private const REFERENCE_CONCURRENCY = 6;

    private static ?int $detected = null;

    /**
     * Logical processors visible to this process, before the halving —
     * hyperthreads included, because this is the raw count the OS gives.
     *
     * Detected once per process: on Linux this reads a file and on macOS
     * it shells out, neither of which should happen per export.
     */
    public static function logicalCores(): int
    {
        return self::intFromEnvironment('HOST_CORES') ?? self::probe();
    }

    /**
     * What the machine itself reports, ignoring HOST_CORES. Only the
     * `host:profile` command needs this: it is what lets the table say
     * "pinned to 4, this host has 12" instead of quietly showing 4 twice.
     */
    public static function probe(): int
    {
        return self::$detected ??= max(1, self::detectCores() ?? self::FALLBACK_CORES);
    }

    /**
     * How many chunk renders may be in flight at once on this host:
     * one per physical core, floor 2, ceiling 16.
     *
     * @return positive-int
     */
    public static function renderConcurrency(?int $cores = null): int
    {
        if ($cores === null) {
            $pinned = self::intFromEnvironment('PDF_SIDECAR_CONCURRENCY');

            if ($pinned !== null) {
                return max(1, $pinned);
            }
        }

        $cores ??= self::logicalCores();

        return max(
            self::MINIMUM_CONCURRENCY,
            min(self::MAXIMUM_CONCURRENCY, intdiv($cores, self::THREADS_PER_CORE)),
        );
    }

    /**
     * How much longer a batch of parallel work takes here than on the
     * host the budgets were measured on.
     *
     * This applies to THROUGHPUT only — a batch of N chunks needs
     * ceil(N / concurrency) waves, so halving the pool doubles the waves.
     * It deliberately does NOT get applied to single-render latency
     * budgets (the sidecar self-check's median, for one): one render uses
     * one core, and the number of cores says nothing about how fast that
     * core is. A host that is slow per-core is a real thing, but core
     * count cannot detect it — HOST_BUDGET_SCALE exists for that case and
     * is a declared override, not a pretend measurement.
     *
     * Clamped at 1.0 from below: a bigger machine never buys a longer
     * timeout, it just finishes sooner.
     */
    public static function throughputScale(int $referenceConcurrency, ?int $concurrency = null): float
    {
        $concurrency ??= self::renderConcurrency();

        return max(1.0, $referenceConcurrency / $concurrency);
    }

    /**
     * The pool this host resolves to, over the pool the published
     * chunked-export timings were taken at.
     *
     * HOST_BUDGET_SCALE overrides it outright, which is the escape hatch
     * for the thing core count cannot see: a host that is slower per
     * core. HOST_REFERENCE_CONCURRENCY moves the baseline, for a fork
     * that re-measures the table on its own reference machine.
     */
    public static function budgetScale(): float
    {
        $declared = self::fromEnvironment('HOST_BUDGET_SCALE');

        if ($declared !== null && (float) $declared > 0) {
            return (float) $declared;
        }

        return self::throughputScale(
            self::intFromEnvironment('HOST_REFERENCE_CONCURRENCY') ?? self::REFERENCE_CONCURRENCY,
        );
    }

    /**
     * A give-up threshold measured on the reference host, expressed for
     * this one.
     *
     * Only for thresholds where erring long is the safe direction — a
     * timeout that fires on a slow-but-correct render turns a working
     * export into a failed one, while a timeout that fires late only
     * delays the report of a failure that already happened. It is never
     * the right transform for a pass/fail budget: those must not get
     * easier just because the machine is smaller.
     */
    public static function scaledSeconds(int $referenceSeconds): int
    {
        return max($referenceSeconds, (int) ceil($referenceSeconds * self::budgetScale()));
    }

    private static function intFromEnvironment(string $name): ?int
    {
        $value = self::fromEnvironment($name);

        return $value !== null && ctype_digit($value) && (int) $value > 0 ? (int) $value : null;
    }

    /**
     * .env is read into all three of these by the framework's own env
     * repository, and which ones are populated depends on the host's
     * variables_order and on whether putenv() is enabled. Config files
     * ask this class for numbers while the configuration is still being
     * assembled, so it cannot go through config() to get them.
     */
    private static function fromEnvironment(string $name): ?string
    {
        foreach ([$_ENV[$name] ?? null, $_SERVER[$name] ?? null, getenv($name)] as $value) {
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    private static function detectCores(): ?int
    {
        // Windows publishes it in the environment; no process to spawn.
        $windows = getenv('NUMBER_OF_PROCESSORS');

        if (is_string($windows) && ctype_digit($windows) && (int) $windows > 0) {
            return (int) $windows;
        }

        // Linux (and WSL, and every container that mounts /proc). Counts
        // the same entries `nproc` counts, without spawning it.
        if (is_readable('/proc/cpuinfo')) {
            $cpuinfo = @file_get_contents('/proc/cpuinfo');

            if (is_string($cpuinfo)) {
                $count = preg_match_all('/^processor\s*:/mi', $cpuinfo);

                if (is_int($count) && $count > 0) {
                    return $count;
                }
            }
        }

        // macOS / BSD have neither, so this is the one path that shells
        // out — and only if the host allows it at all (shared hosting
        // routinely puts shell_exec in disable_functions).
        if (PHP_OS_FAMILY === 'Darwin' || PHP_OS_FAMILY === 'BSD') {
            $sysctl = self::shell('sysctl -n hw.logicalcpu 2>/dev/null');

            if ($sysctl !== null) {
                return $sysctl;
            }
        }

        return self::shell('nproc 2>/dev/null');
    }

    private static function shell(string $command): ?int
    {
        if (! function_exists('shell_exec')) {
            return null;
        }

        $output = @shell_exec($command);

        if (! is_string($output)) {
            return null;
        }

        $value = (int) trim($output);

        return $value > 0 ? $value : null;
    }
}
