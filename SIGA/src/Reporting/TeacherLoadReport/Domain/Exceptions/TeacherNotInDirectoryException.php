<?php

declare(strict_types=1);

namespace Src\Reporting\TeacherLoadReport\Domain\Exceptions;

use DomainException;

final class TeacherNotInDirectoryException extends DomainException
{
    public static function withId(int $teacherId): self
    {
        return new self("No teacher with id [{$teacherId}] is available for a load report.");
    }
}
