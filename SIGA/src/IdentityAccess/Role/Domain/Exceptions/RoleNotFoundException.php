<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Role\Domain\Exceptions;

use DomainException;

final class RoleNotFoundException extends DomainException
{
    public static function withId(int $id): self
    {
        return new self("Role with id [{$id}] was not found.");
    }
}
