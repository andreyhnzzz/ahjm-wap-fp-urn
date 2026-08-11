<?php

declare(strict_types=1);

namespace Src\Academic\Classroom\Application\DTOs;

/**
 * Immutable data boundary between Presentation and Application.
 */
final readonly class ClassroomDTO
{
    public function __construct(
        public string $name,
        public int $capacity,
    ) {}

    /**
     * @param  array{name: string, capacity: int}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            capacity: $data['capacity'],
        );
    }
}
