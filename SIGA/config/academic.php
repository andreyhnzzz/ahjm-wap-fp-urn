<?php

declare(strict_types=1);

/**
 * Business thresholds for the Academic Offer analytics module
 * (Reports RE-01/RE-02 and Risk Board RE-04).
 *
 * Every value here is a *business rule parameter*, not a technical
 * setting: RE-04 explicitly requires the minimum-enrollment threshold to
 * be configurable, and the remaining numbers follow the same reasoning —
 * a coordinator changing the campus policy must not require a code
 * change or a redeploy of the domain layer.
 *
 * The Domain layer never reads this file. Values are read once at the
 * Presentation/Infrastructure boundary and passed *into* the domain as
 * plain floats/ints (see RiskThresholds and TeacherLoadReport), which is
 * what keeps `src/` free of any Laravel import.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Term shown by default
    |--------------------------------------------------------------------------
    |
    | Pre-selected term in the report screens. When empty, the screens fall
    | back to the most recent term that actually has groups loaded, so a
    | fresh database never opens on an empty selector.
    |
    */
    'default_term' => env('ACADEMIC_DEFAULT_TERM', ''),

    /*
    |--------------------------------------------------------------------------
    | Risk board (RE-04)
    |--------------------------------------------------------------------------
    |
    | minimum_enrollment: a group whose estimated enrollment falls below
    | this number is flagged as a Low risk. The statement fixes the initial
    | value at 5 and requires it to stay configurable.
    |
    | maximum_workload: total workload a single teacher may accumulate in
    | one term. Anything strictly above it is a Medium risk ("jornada en
    | conflicto"). 1.0 = one full-time equivalent.
    |
    | refresh_seconds: how often the board re-evaluates itself in the
    | browser without a manual reload. Must stay comfortably under the 60
    | second ceiling the acceptance criteria impose, since the worst case
    | latency a user can observe is one full interval.
    |
    */
    'risk' => [
        'minimum_enrollment' => (int) env('ACADEMIC_RISK_MIN_ENROLLMENT', 5),
        'maximum_workload' => (float) env('ACADEMIC_RISK_MAX_WORKLOAD', 1.0),
        'refresh_seconds' => (int) env('ACADEMIC_RISK_REFRESH_SECONDS', 15),
    ],

    /*
    |--------------------------------------------------------------------------
    | Teacher load report (RE-02)
    |--------------------------------------------------------------------------
    |
    | under_load_ratio: assigned workload below this fraction of the
    | teacher's reference workload counts as "significant under-contracting"
    | (the statement fixes it at 80%).
    |
    | Over-contracting needs no ratio: any assigned workload strictly above
    | the reference workload qualifies.
    |
    */
    'teacher_load' => [
        'under_load_ratio' => (float) env('ACADEMIC_UNDER_LOAD_RATIO', 0.8),
    ],

    /*
    |--------------------------------------------------------------------------
    | Offer report (RE-01)
    |--------------------------------------------------------------------------
    |
    | storage_disk / storage_path: where the two generated artifacts
    | (.xlsx and .pdf) are written before being offered for download. They
    | are deliberately written to a private disk — a generated report is
    | internal academic data, not a public asset.
    |
    | max_generation_seconds: the ceiling the acceptance criteria impose.
    | Exceeding it does not fail the request; it escalates the log entry
    | from info to warning so the breach is visible in the log the
    | criteria say must be verifiable.
    |
    */
    'offer_report' => [
        'storage_disk' => env('ACADEMIC_REPORT_DISK', 'local'),
        'storage_path' => 'reports/offer',
        'max_generation_seconds' => (int) env('ACADEMIC_REPORT_MAX_SECONDS', 30),
    ],

];
