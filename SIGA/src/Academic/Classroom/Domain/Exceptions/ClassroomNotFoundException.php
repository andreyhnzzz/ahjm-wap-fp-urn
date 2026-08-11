<?php

declare(strict_types=1);

namespace Src\Academic\Classroom\Domain\Exceptions;

use DomainException;

final class ClassroomNotFoundException extends DomainException
{
    public static function withId(int $id): self
    {
        return new self("Classroom with id [{$id}] was not found.");
    }
}
