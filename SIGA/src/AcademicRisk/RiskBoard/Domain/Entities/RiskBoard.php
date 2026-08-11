<?php

declare(strict_types=1);

namespace Src\AcademicRisk\RiskBoard\Domain\Entities;

use Src\AcademicRisk\RiskBoard\Domain\ValueObjects\RiskLevel;

/**
 * RiskBoard — Aggregate Root of the AcademicRisk context: the complete
 * set of risks detected in one evaluation, grouped the way RE-04 asks
 * for it.
 *
 * It is a read model with behaviour, not a persisted entity: nothing
 * writes a board to a table. It is recomputed from scratch on every
 * evaluation, which is what makes an item disappear the instant its
 * cause is fixed — there is no stored alert that could go stale or need
 * a "resolved" flag nobody remembers to set.
 *
 * Also the class the RiskBoardPolicy is registered against, so
 * `authorize('viewAny', RiskBoard::class)` reads as what it is.
 */
final class RiskBoard
{
    /**
     * @param  array<int, RiskItem>  $items
     */
    private function __construct(
        private readonly array $items,
    ) {}

    /**
     * @param  array<int, RiskItem>  $items
     */
    public static function of(array $items): self
    {
        return new self(array_values($items));
    }

    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * @return array<int, RiskItem>
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * @return array<int, RiskItem>
     */
    public function itemsAt(RiskLevel $level): array
    {
        return array_values(array_filter(
            $this->items,
            static fn (RiskItem $item): bool => $item->level() === $level,
        ));
    }

    public function countAt(RiskLevel $level): int
    {
        return count($this->itemsAt($level));
    }

    public function total(): int
    {
        return count($this->items);
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }
}
