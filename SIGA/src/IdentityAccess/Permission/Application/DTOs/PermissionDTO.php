<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Permission\Application\DTOs;

final readonly class PermissionDTO
{
    public function __construct(
        public string $module,
        public string $action,
    ) {}

    /**
     * @param array{module: string, action: string} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            module: $data['module'],
            action: $data['action'],
        );
    }
}
