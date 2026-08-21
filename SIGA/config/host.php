<?php

declare(strict_types=1);

use Src\Shared\Host\Infrastructure\HostProfile;

/**
 * How this machine is sized, and what the application derives from it.
 *
 * Nothing here is a business rule and nothing here changes what a report
 * says — same reasoning that keeps config/exports.php separate from
 * config/academic.php. What this file exists for is narrower: the PDF
 * pipeline has knobs that are only correct relative to the machine
 * underneath them, and those knobs used to be constants measured on one
 * Windows laptop and copied into two files that had to agree with each
 * other by hand.
 *
 * This file only exposes what HostProfile resolves; the overrides
 * (HOST_CORES, PDF_SIDECAR_CONCURRENCY, HOST_BUDGET_SCALE,
 * HOST_REFERENCE_CONCURRENCY) are read there, because config/queue.php
 * and config/exports.php need the same numbers while the configuration
 * is still being assembled and cannot call config() to get them.
 *
 * Detection is the default, not a policy: a container limited to 2 CPUs
 * by its orchestrator while the kernel still reports 64 is exactly the
 * case where the operator knows better than the probe, and HOST_CORES is
 * how they say so.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Logical processors
    |--------------------------------------------------------------------------
    |
    | The raw count the OS reports, hyperthreads included. Read from
    | NUMBER_OF_PROCESSORS on Windows, /proc/cpuinfo on Linux, sysctl on
    | macOS, falling back to 4 if the host says nothing.
    |
    */
    'cores' => HostProfile::logicalCores(),

    /*
    |--------------------------------------------------------------------------
    | Concurrent PDF renders
    |--------------------------------------------------------------------------
    |
    | The single number both halves of the chunked export path read:
    | ChunkedChromePdfWriter sends this many chunk requests per wave, and
    | the sidecar opens this many fresh Chromium pages at once. They were
    | two separate hardcoded 10s before, with a comment in each file
    | asking whoever changed one to remember the other — the failure mode
    | being silent (the extra requests just queue inside the sidecar) and
    | only visible under load.
    |
    | One render per PHYSICAL core: logical processors / 2, floor 2,
    | ceiling 16. Not a guess — a 45,000-row A/B on the reference machine
    | (12 threads, 6 cores) put the shipped constant of 10 at 30.6s and
    | this rule's 6 at 12.3s. HostProfile carries the full sweep, the
    | isolating comparison against chunk size, and why halving is assumed
    | rather than probed.
    |
    | PDF_SIDECAR_CONCURRENCY overrides both sides at once. PHP exports it
    | into the sidecar's environment when it autostarts it, so an override
    | reaches Node even though Node never reads .env.
    |
    */
    'render_concurrency' => HostProfile::renderConcurrency(),

    /*
    |--------------------------------------------------------------------------
    | Reference host
    |--------------------------------------------------------------------------
    |
    | The pool the chunked-export timings in the README were taken at (the
    | reference machine's own 6). Kept visible because those timings are
    | only meaningful next to it.
    |
    */
    'reference_concurrency' => HostProfile::renderConcurrency(12),

    /*
    |--------------------------------------------------------------------------
    | Throughput scale
    |--------------------------------------------------------------------------
    |
    | A batch of N chunks needs ceil(N / render_concurrency) waves, so a
    | 4-vCPU runner's pool of 2 against the reference pool of 6 means
    | three times the waves for the same report: 1.0 here, 3.0 there.
    |
    | Applies to batch throughput and to give-up thresholds sized from it
    | (the queued export's timeout, a chunk request's timeout) — never to
    | a pass/fail budget, which must not get easier just because the
    | machine is smaller. One render occupies one core, and core COUNT
    | says nothing about core SPEED; a host that is slow per core is a
    | real thing this cannot detect, which is what HOST_BUDGET_SCALE is
    | for: a declared override, never a pretend measurement.
    |
    */
    'throughput_scale' => HostProfile::budgetScale(),

];
