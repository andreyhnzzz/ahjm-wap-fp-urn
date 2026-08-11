<?php

declare(strict_types=1);

namespace Src\Reporting\OfferReport\Application\UseCases;

use Src\Reporting\OfferReport\Domain\Contracts\OfferReportArchiveInterface;
use Src\Reporting\OfferReport\Domain\ValueObjects\StoredOfferReport;

final class StoreOfferReportUseCase
{
    public function __construct(
        private readonly OfferReportArchiveInterface $archive,
    ) {}

    /**
     * @param  array<int, array{key: string, label: string}>  $headers
     * @param  array<int, array<string, scalar|null>>  $rows
     */
    public function handle(string $term, string $title, array $headers, array $rows): StoredOfferReport
    {
        return $this->archive->store($term, $title, $headers, $rows);
    }
}
