<?php

declare(strict_types=1);

namespace Src\Academic\Group\Domain\Exceptions;

use DomainException;

/**
 * Both factories guard the same aggregate figure — the workload a group
 * contributes to its teacher's term total — from the two ways it can
 * become meaningless.
 *
 * `requiresATeacher()` is the interesting one: workload assigned to a
 * group with nobody teaching it would still be summed by RE-02 and
 * RE-04, silently inflating a teacher's load or inventing a "jornada en
 * conflicto" for a teacher who was never assigned anything. Refusing the
 * state here is what keeps both read models honest, whichever entry
 * point wrote the data.
 */
final class InvalidGroupWorkloadException extends DomainException
{
    public static function mustNotBeNegative(float $workload): self
    {
        return new self("A group workload cannot be negative, [{$workload}] given.");
    }

    public static function requiresATeacher(string $code): self
    {
        return new self("Group [{$code}] cannot carry an assigned workload while it has no teacher.");
    }
}
