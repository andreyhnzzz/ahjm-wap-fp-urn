<?php

declare(strict_types=1);

namespace Src\Academic\Classroom\Domain\Exceptions;

use DomainException;

/**
 * A classroom that seats nobody is not a classroom. Enforced in the
 * entity rather than only in the form, so the rule survives any future
 * entry point (CSV import, API, seeder) that never touches Livewire
 * validation.
 */
final class InvalidCapacityException extends DomainException
{
    public static function mustBePositive(int $capacity): self
    {
        return new self("A classroom capacity must be greater than zero, [{$capacity}] given.");
    }
}
