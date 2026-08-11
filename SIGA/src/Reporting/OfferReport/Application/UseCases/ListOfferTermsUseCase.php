<?php

declare(strict_types=1);

namespace Src\Reporting\OfferReport\Application\UseCases;

use Src\Reporting\OfferReport\Domain\Contracts\OfferSourceInterface;

/**
 * The terms the offer report can be asked for. Shared by the term
 * selector and by the validation rule behind it, so the screen can never
 * offer an option the request would then reject.
 */
final class ListOfferTermsUseCase
{
    public function __construct(
        private readonly OfferSourceInterface $source,
    ) {}

    /**
     * @return array<int, string>
     */
    public function handle(): array
    {
        return $this->source->availableTerms();
    }
}
