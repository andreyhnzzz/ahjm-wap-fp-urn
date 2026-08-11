<?php

declare(strict_types=1);

namespace Src\Academic\Classroom\Domain\Entities;

use Src\Academic\Classroom\Domain\Exceptions\InvalidCapacityException;

/**
 * Classroom — Aggregate Root of the Academic\Classroom module: a
 * physical space and how many students fit in it.
 *
 * Pure PHP, zero framework coupling.
 */
final class Classroom
{
    private function __construct(
        private readonly ?int $id,
        private string $name,
        private int $capacity,
    ) {}

    public static function create(string $name, int $capacity): self
    {
        self::assertCapacityIsPositive($capacity);

        return new self(id: null, name: $name, capacity: $capacity);
    }

    public static function reconstitute(int $id, string $name, int $capacity): self
    {
        self::assertCapacityIsPositive($capacity);

        return new self(id: $id, name: $name, capacity: $capacity);
    }

    public function rename(string $name): void
    {
        $this->name = $name;
    }

    public function changeCapacity(int $capacity): void
    {
        self::assertCapacityIsPositive($capacity);

        $this->capacity = $capacity;
    }

    /**
     * Answers the only question the rest of the academic domain actually
     * asks a classroom: does this many students fit? Expressed here so
     * the comparison lives with the data it constrains instead of being
     * re-derived by whichever screen happens to need it.
     */
    public function canHost(int $students): bool
    {
        return $students <= $this->capacity;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function capacity(): int
    {
        return $this->capacity;
    }

    private static function assertCapacityIsPositive(int $capacity): void
    {
        if ($capacity <= 0) {
            throw InvalidCapacityException::mustBePositive($capacity);
        }
    }
}
