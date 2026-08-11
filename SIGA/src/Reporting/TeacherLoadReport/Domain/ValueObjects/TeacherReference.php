<?php

declare(strict_types=1);

namespace Src\Reporting\TeacherLoadReport\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * The teacher a load report is about, as this context needs them: an
 * identity, a name, and the reference workload every comparison in RE-02
 * is made against.
 *
 * Declared here rather than imported from the Academic context so the
 * report stays independent of how a teacher is modelled elsewhere — the
 * same anti-corruption reasoning as the risk board's snapshots.
 */
final readonly class TeacherReference
{
    public function __construct(
        public int $id,
        public string $identityCard,
        public string $name,
        public float $referenceWorkload,
    ) {
        // The reference workload is the denominator of the
        // under-contracting rule. Zero would make that comparison
        // meaningless, so a report about such a teacher must not exist in
        // the first place.
        if ($referenceWorkload <= 0.0) {
            throw new InvalidArgumentException("Teacher [{$identityCard}] has a non-positive reference workload of [{$referenceWorkload}].");
        }
    }
}
