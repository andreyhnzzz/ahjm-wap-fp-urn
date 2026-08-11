<?php

declare(strict_types=1);

namespace Src\Reporting\OfferReport\Domain\Entities;

use DateTimeImmutable;
use Src\Reporting\OfferReport\Domain\ValueObjects\OfferRow;

/**
 * OfferReport — Aggregate Root of the Reporting\OfferReport module: the
 * complete academic offer of one term, as of one instant.
 *
 * A read model, never persisted as a row: the report *files* are the
 * artifacts, and they are produced from this. It still earns being an
 * entity rather than a bare array because it answers questions about
 * itself — how many groups are unstaffed, how many students the term
 * expects — and those totals belong next to the data they summarise, not
 * recomputed in whichever screen happens to need them.
 */
final class OfferReport
{
    /**
     * @param  array<int, OfferRow>  $rows
     */
    private function __construct(
        private readonly string $term,
        private readonly array $rows,
        private readonly DateTimeImmutable $generatedAt,
    ) {}

    /**
     * @param  array<int, OfferRow>  $rows
     */
    public static function forTerm(string $term, array $rows, DateTimeImmutable $generatedAt): self
    {
        return new self($term, array_values($rows), $generatedAt);
    }

    public function term(): string
    {
        return $this->term;
    }

    /**
     * @return array<int, OfferRow>
     */
    public function rows(): array
    {
        return $this->rows;
    }

    public function generatedAt(): DateTimeImmutable
    {
        return $this->generatedAt;
    }

    public function groupCount(): int
    {
        return count($this->rows);
    }

    public function isEmpty(): bool
    {
        return $this->rows === [];
    }

    public function groupsWithoutTeacher(): int
    {
        return count(array_filter($this->rows, static fn (OfferRow $row): bool => ! $row->hasTeacher()));
    }

    public function groupsWithoutClassroom(): int
    {
        return count(array_filter($this->rows, static fn (OfferRow $row): bool => ! $row->hasClassroom()));
    }

    public function totalEstimatedEnrollment(): int
    {
        return array_sum(array_map(static fn (OfferRow $row): int => $row->estimatedEnrollment, $this->rows));
    }
}
