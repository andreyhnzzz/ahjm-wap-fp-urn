/**
 * The Node half of config/host.php — the same rule, so the sidecar and
 * the PHP side that feeds it can never disagree about how many renders
 * may run at once.
 *
 * Keeping one copy of the rule was not an option worth its cost: the
 * sidecar is started by `npm run pdf:sidecar` as often as it is started
 * by PHP, and making it shell out to `artisan` to learn its own pool size
 * would put a PHP boot in front of a process whose entire reason to exist
 * is not paying startup costs. So the rule is duplicated, deliberately,
 * and HostProfileParityTest fails the build if the two copies drift.
 *
 * Src\Shared\Host\Infrastructure\HostProfile carries the reasoning for
 * every constant below; this file only mirrors the arithmetic.
 */
import os from 'node:os';

const THREADS_PER_CORE = 2;
const MINIMUM_CONCURRENCY = 2;
const MAXIMUM_CONCURRENCY = 16;
const FALLBACK_CORES = 4;

/**
 * availableParallelism() respects cgroup CPU limits where the platform
 * exposes them, which cpus().length does not — a container capped at two
 * CPUs on a 64-core node reports 64 from the latter.
 */
export function logicalCores() {
    const override = Number(process.env.HOST_CORES);

    if (Number.isFinite(override) && override > 0) {
        return Math.floor(override);
    }

    const detected = os.availableParallelism?.() ?? os.cpus().length;

    return detected > 0 ? detected : FALLBACK_CORES;
}

export function renderConcurrency(cores = logicalCores()) {
    const override = Number(process.env.PDF_SIDECAR_CONCURRENCY);

    if (Number.isFinite(override) && override > 0) {
        return Math.floor(override);
    }

    return Math.max(
        MINIMUM_CONCURRENCY,
        Math.min(MAXIMUM_CONCURRENCY, Math.floor(cores / THREADS_PER_CORE)),
    );
}
