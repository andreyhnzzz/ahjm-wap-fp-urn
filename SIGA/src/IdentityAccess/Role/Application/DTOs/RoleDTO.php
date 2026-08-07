<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Role\Application\DTOs;

/**
 * Immutable data boundary between Presentation and Application layers.
 * Carries primitives only — no Domain Entities and no Eloquent Models
 * leak across this line in either direction.
 */
final readonly class RoleDTO
{
    /**
     * @param array<int, string> $permissions
     */
    public function __construct(
        public string $name,
        public array $permissions = [],
    ) {}

    /**
     * @param array{name: string, permissions?: array<int, string>} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            permissions: $data['permissions'] ?? [],
        );
    }
}
