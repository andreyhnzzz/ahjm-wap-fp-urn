<?php

declare(strict_types=1);

namespace Src\Academic\Teacher\Domain\Entities;

use Src\Academic\Teacher\Domain\Exceptions\InvalidWorkloadException;

/**
 * Teacher — Aggregate Root of the Academic\Teacher module.
 *
 * Pure PHP. Zero framework coupling: no Eloquent, no Illuminate, no
 * Livewire. Delete Laravel from the project and this class still loads.
 *
 * Scope note: this entity deliberately does NOT know how much workload
 * has actually been assigned to it. That figure is a per-term aggregate
 * over groups, it belongs to the reporting/risk read models (RE-02,
 * RE-04), and pulling it in here would turn a small, stable aggregate
 * into one that changes every time a group is edited.
 */
final class Teacher
{
    private function __construct(
        private readonly ?int $id,
        private string $identityCard,
        private string $name,
        private float $referenceWorkload,
    ) {}

    public static function create(string $identityCard, string $name, float $referenceWorkload): self
    {
        self::assertWorkloadIsPositive($referenceWorkload);

        return new self(
            id: null,
            identityCard: $identityCard,
            name: $name,
            referenceWorkload: $referenceWorkload,
        );
    }

    public static function reconstitute(int $id, string $identityCard, string $name, float $referenceWorkload): self
    {
        self::assertWorkloadIsPositive($referenceWorkload);

        return new self(
            id: $id,
            identityCard: $identityCard,
            name: $name,
            referenceWorkload: $referenceWorkload,
        );
    }

    public function rename(string $name): void
    {
        $this->name = $name;
    }

    public function changeIdentityCard(string $identityCard): void
    {
        $this->identityCard = $identityCard;
    }

    public function changeReferenceWorkload(float $referenceWorkload): void
    {
        self::assertWorkloadIsPositive($referenceWorkload);

        $this->referenceWorkload = $referenceWorkload;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function identityCard(): string
    {
        return $this->identityCard;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function referenceWorkload(): float
    {
        return $this->referenceWorkload;
    }

    private static function assertWorkloadIsPositive(float $referenceWorkload): void
    {
        if ($referenceWorkload <= 0.0) {
            throw InvalidWorkloadException::mustBePositive($referenceWorkload);
        }
    }
}
