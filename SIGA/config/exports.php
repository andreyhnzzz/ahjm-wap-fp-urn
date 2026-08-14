<?php

declare(strict_types=1);

/**
 * Technical settings for how reports are rendered — deliberately not in
 * config/academic.php, which holds *business rule* parameters (risk
 * thresholds, workload ratios) that a coordinator may legitimately want
 * to change. Nothing in this file is a business rule; changing any of it
 * changes performance or operations, never what the report says.
 */
return [

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

        /*
        |----------------------------------------------------------------------
        | Hard row ceiling for a PDF export
        |----------------------------------------------------------------------
        |
        | Measured, not guessed. Sweeping row counts against the real
        | template on chrome-headless-shell:
        |
        |   15,000 rows -> 22.8s, 1,155 pages, 3.7 MB, valid PDF
        |   16,000 rows -> Chrome's print dies:
        |                  "Protocol error (Page.printToPDF): Printing failed"
        |
        | The cliff is Chrome's, not this application's, and nothing above
        | it degrades gracefully — it fails outright. Without a guard the
        | failure is also slow *and* doubled: the sidecar dies, the caller
        | falls back to Browsershot, and Browsershot spends another minute
        | failing at the identical document. Checking the row count first
        | turns a two-minute mystery into an immediate, logged reason.
        |
        | Default 12,000 rather than 15,000 because the real limit is
        | total content, not row count: the sweep used this project's
        | nine columns at their normal widths, and a report with longer
        | cells reaches the same wall sooner. The margin is the part that
        | is a judgement call; the 15,000/16,000 boundary is not.
        |
        | Anything larger is what the .xlsx export is for — OpenSpout
        | streams it row by row at constant memory with no page layout to
        | fragment, so it has no comparable ceiling.
        |
        */
        'max_rows' => (int) env('PDF_MAX_ROWS', 12_000),

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
