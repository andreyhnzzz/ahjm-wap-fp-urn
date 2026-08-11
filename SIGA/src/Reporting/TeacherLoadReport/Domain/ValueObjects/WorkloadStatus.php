<?php

declare(strict_types=1);

namespace Src\Reporting\TeacherLoadReport\Domain\ValueObjects;

/**
 * The verdict RE-02 asks the report to colour a teacher's load with.
 *
 * The classification rule lives here, on the concept it produces, rather
 * than in the report entity or in a Blade template: "over-contracted"
 * means one specific comparison, and the moment two places are allowed
 * to decide it independently they will eventually disagree — typically
 * on the boundary case, which is exactly the one a defence asks about.
 *
 * The rule, straight from the requirement:
 *   assigned  >  reference             → over-contracting
 *   assigned  <  reference × ratio     → significant under-contracting
 *   otherwise                          → no alert
 *
 * `ratio` is a parameter, not a constant, because the campus policy
 * (80%) is configuration, and hardcoding it here would make the domain
 * un-tunable without a code change.
 */
enum WorkloadStatus: string
{
    case OverContracted = 'over_contracted';
    case UnderContracted = 'under_contracted';
    case Balanced = 'balanced';

    public static function classify(float $assigned, float $reference, float $underLoadRatio): self
    {
        if ($assigned > $reference) {
            return self::OverContracted;
        }

        if ($assigned < $reference * $underLoadRatio) {
            return self::UnderContracted;
        }

        return self::Balanced;
    }

    /**
     * Whether this verdict is one the report has to paint. Balanced is
     * deliberately not an alert — colouring every row would make the two
     * that matter invisible.
     */
    public function isAlert(): bool
    {
        return $this !== self::Balanced;
    }
}
