<?php

declare(strict_types=1);

namespace Src\Reporting\OfferReport\Application\UseCases;

use DateTimeImmutable;
use Src\Reporting\OfferReport\Domain\Contracts\OfferSourceInterface;
use Src\Reporting\OfferReport\Domain\Entities\OfferReport;

final class GenerateOfferReportUseCase
{
    public function __construct(
        private readonly OfferSourceInterface $source,
    ) {}

    public function handle(string $term): OfferReport
    {
        return OfferReport::forTerm(
            term: $term,
            rows: $this->source->rowsForTerm($term),
            // Plain PHP, no Carbon: the Application layer stays as
            // framework-free as the Domain it orchestrates.
            generatedAt: new DateTimeImmutable,
        );
    }
}
