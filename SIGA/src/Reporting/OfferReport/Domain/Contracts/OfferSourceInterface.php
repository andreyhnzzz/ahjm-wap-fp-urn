<?php

declare(strict_types=1);

namespace Src\Reporting\OfferReport\Domain\Contracts;

use Src\Reporting\OfferReport\Domain\ValueObjects\OfferRow;

/**
 * Read port of the offer report: where the rows come from.
 *
 * The Reporting context declares the shape it needs and its own adapter
 * produces it, exactly like the risk board does. That is what lets this
 * report be built from a completely different source one day — a data
 * warehouse, a read replica, another campus' API — without a single line
 * of the report itself changing.
 */
interface OfferSourceInterface
{
    /**
     * Every group of the given term, ordered the way the report presents
     * them.
     *
     * @return array<int, OfferRow>
     */
    public function rowsForTerm(string $term): array;

    /**
     * Terms that actually have groups loaded, most recent first — the
     * options the term selector offers.
     *
     * @return array<int, string>
     */
    public function availableTerms(): array;
}
