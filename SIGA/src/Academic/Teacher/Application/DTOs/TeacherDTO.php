<?php

declare(strict_types=1);

namespace Src\Academic\Teacher\Application\DTOs;

/**
 * Immutable data boundary between Presentation and Application.
 * Primitives only — no Domain Entity and no Eloquent Model crosses this
 * line in either direction.
 */
final readonly class TeacherDTO
{
    public function __construct(
        public string $identityCard,
        public string $name,
        public float $referenceWorkload,
    ) {}

    /**
     * @param  array{identityCard: string, name: string, referenceWorkload: float}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            identityCard: $data['identityCard'],
            name: $data['name'],
            referenceWorkload: $data['referenceWorkload'],
        );
    }
}
