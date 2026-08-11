<?php

declare(strict_types=1);

namespace Src\Academic\Teacher\Domain\Exceptions;

use DomainException;

/**
 * Raised when a teacher is given a reference workload that cannot mean
 * anything in the academic domain.
 *
 * This is a genuine invariant, not defensive input validation: the
 * reference workload is the denominator of the under-contracting rule in
 * RE-02 ("assigned < 80% of estimated"). A zero or negative reference
 * would make that comparison either a division by zero or silently
 * meaningless, so the entity refuses to exist in that state instead of
 * letting a report compute nonsense from it later.
 */
final class InvalidWorkloadException extends DomainException
{
    public static function mustBePositive(float $workload): self
    {
        return new self("A teacher reference workload must be greater than zero, [{$workload}] given.");
    }
}
