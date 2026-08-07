<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Role\Domain\Exceptions;

use DomainException;

/**
 * Raised when an operation attempts to rename or delete one of the
 * system's structural roles (Superadmin, Admin).
 */
final class RoleIsProtectedException extends DomainException
{
    public static function forName(string $name): self
    {
        return new self("The role [{$name}] is protected by the system and cannot be renamed or deleted.");
    }
}
