<?php

declare(strict_types=1);

namespace Src\Academic\Teacher\Domain\Exceptions;

use DomainException;

final class TeacherNotFoundException extends DomainException
{
    public static function withId(int $id): self
    {
        return new self("Teacher with id [{$id}] was not found.");
    }
}
