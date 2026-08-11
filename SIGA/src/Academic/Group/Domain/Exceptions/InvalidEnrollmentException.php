<?php

declare(strict_types=1);

namespace Src\Academic\Group\Domain\Exceptions;

use DomainException;

final class InvalidEnrollmentException extends DomainException
{
    public static function mustNotBeNegative(int $enrollment): self
    {
        return new self("An estimated enrollment cannot be negative, [{$enrollment}] given.");
    }
}
