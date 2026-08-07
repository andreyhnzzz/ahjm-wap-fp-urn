<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Permission\Domain\Exceptions;

use DomainException;

final class PermissionNotFoundException extends DomainException
{
    public static function withId(int $id): self
    {
        return new self("Permission with id [{$id}] was not found.");
    }
}
