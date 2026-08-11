<?php

declare(strict_types=1);

namespace Src\Academic\Group\Domain\Exceptions;

use DomainException;

final class GroupNotFoundException extends DomainException
{
    public static function withId(int $id): self
    {
        return new self("Group with id [{$id}] was not found.");
    }
}
