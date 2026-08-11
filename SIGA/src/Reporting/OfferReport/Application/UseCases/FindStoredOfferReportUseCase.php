<?php

declare(strict_types=1);

namespace Src\Reporting\OfferReport\Application\UseCases;

use Src\Reporting\OfferReport\Domain\Contracts\OfferReportArchiveInterface;
use Src\Reporting\OfferReport\Domain\ValueObjects\StoredOfferReport;

/**
 * Resolves the artifacts of a term before serving a download.
 *
 * The download action asks for them by term and never trusts a path from
 * the browser: the client can tamper with any Livewire property it
 * likes, so a stored filesystem path in component state would be an
 * arbitrary-file-read waiting to happen. Deriving the path server-side
 * from a validated term closes that off by construction.
 */
final class FindStoredOfferReportUseCase
{
    public function __construct(
        private readonly OfferReportArchiveInterface $archive,
    ) {}

    public function handle(string $term): ?StoredOfferReport
    {
        return $this->archive->find($term);
    }
}
