<?php

declare(strict_types=1);

use Src\Shared\Host\Infrastructure\HostProfile;

/**
 * Technical settings for how reports are rendered — deliberately not in
 * config/academic.php, which holds *business rule* parameters (risk
 * thresholds, workload ratios) that a coordinator may legitimately want
 * to change. Nothing in this file is a business rule; changing any of it
 * changes performance or operations, never what the report says.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Queued export job
    |--------------------------------------------------------------------------
    |
    | timeout: seconds the worker gives GenerateReportExportJob before it
    | kills it. There was no value here at all, which meant Laravel's
    | default of 60 — measured against the work it has to do, a 45,000-row
    | chunked export takes 12-14s on the reference machine and about 40s
    | on a 4-vCPU host, and the Browsershot fallback path (sidecar down,
    | every chunk paying ~400ms of node startup, serially) lands near 55s
    | before either of those is scaled. A 60s ceiling therefore sat inside
    | the working range: not a guard against a hung Chromium, a coin flip
    | on large exports, and one that turned "slow" into "killed and run
    | again from the top".
    |
    | 300s on the reference host, scaled by host.throughput_scale for a
    | smaller one. Far outside the working range on purpose — a give-up
    | threshold only earns its keep by being unreachable when things work.
    |
    | retry_after (config/queue.php) is derived from this and must stay
    | above it: if the queue reclaims a job while its worker is still
    | rendering, a second worker starts the same export in parallel.
    |
    */
    'job' => [
        'timeout' => (int) env('EXPORT_JOB_TIMEOUT', HostProfile::scaledSeconds(300)),
    ],

    'pdf' => [

        /*
        |----------------------------------------------------------------------
        | Tagged (accessibility-structured) PDF
        |----------------------------------------------------------------------
        |
        | Chrome's print engine emits a PDF/UA structure tree by default:
        | one /StructElem object per <td>, per <tr>, plus wrappers. On a
        | 2500-row nine-column export that is 52,519 extra PDF objects —
        | measured at 7.9 MB of an 8.9 MB file, plus a 1 MB cross-reference
        | table that exists only to index them.
        |
        | Measured cost of leaving it on (median, warm Chromium):
        |
        |   rows    tagged   untagged   size tagged -> untagged
        |   2,500   2.66 s     1.96 s       8.9 MB -> 0.8 MB
        |   10,000 18.33 s    10.93 s      36.0 MB -> 3.2 MB
        |
        | Turned off by default, and the reason is not only speed: what is
        | lost is screen-reader table semantics, and for these exports that
        | loss is covered — RE-01 generates an .xlsx from the same rows in
        | the same action, and a spreadsheet is a better assistive-tech
        | surface for tabular data than a tagged PDF is. Text in the PDF
        | stays real text either way: selectable, searchable, copyable.
        |
        | Set PDF_TAGGED=true to trade the time back for the structure
        | tree. The flag is honoured by both render paths.
        |
        */
        'tagged' => filter_var(env('PDF_TAGGED', false), FILTER_VALIDATE_BOOL),

        // There used to be a 'max_rows' key here that refused a PDF export
        // past Chrome's measured single-document ceiling (15,000 rows
        // renders, 16,000 fails outright — see
        // ChunkedChromePdfWriter::CHUNK_ROWS for the same numbers, still
        // true and still the reason chunk size is bounded well under that
        // line). Once GenerateReportExportJob started splitting a large
        // export into several documents and stitching them back together,
        // refusing anything was no longer the honest answer — it works now,
        // so the guard was removed rather than raised.

        /*
        |----------------------------------------------------------------------
        | Warm-Chrome sidecar
        |----------------------------------------------------------------------
        |
        | scripts/pdf-sidecar.mjs — the long-lived Node process that keeps
        | one Chromium launched so a render is a localhost POST instead of
        | a fresh node + puppeteer + Chromium startup (~2.2 s of fixed cost
        | per export, measured).
        |
        | autostart: on a clean start nothing is listening yet, so the
        | first export would pay that full cost. When enabled, the client
        | launches the sidecar itself, waits up to boot_timeout for it to
        | answer, and uses it — falling back to Browsershot unchanged if it
        | does not come up. The sidecar therefore stays an accelerator and
        | never becomes a requirement.
        |
        */
        'sidecar' => [
            'port' => (int) env('PDF_SIDECAR_PORT', 8720),
            'autostart' => filter_var(env('PDF_SIDECAR_AUTOSTART', true), FILTER_VALIDATE_BOOL),
            'boot_timeout' => (float) env('PDF_SIDECAR_BOOT_TIMEOUT', 10),
            'render_timeout' => (int) env('PDF_SIDECAR_RENDER_TIMEOUT', 120),
        ],
    ],

];
