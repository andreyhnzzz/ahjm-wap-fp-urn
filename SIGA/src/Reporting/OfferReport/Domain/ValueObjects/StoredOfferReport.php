<?php

declare(strict_types=1);

namespace Src\Reporting\OfferReport\Domain\ValueObjects;

use DateTimeImmutable;

/**
 * The pair of artifacts RE-01 demands — one .xlsx and one .pdf holding
 * the same information — plus when they were produced.
 *
 * They travel together in one object because the requirement treats them
 * as one deliverable: a generation that produced only a spreadsheet did
 * not satisfy RE-01, and modelling them as two independent files would
 * make that half-state representable.
 */
final readonly class StoredOfferReport
{
    public function __construct(
        public string $term,
        public string $excelPath,
        public string $pdfPath,
        public DateTimeImmutable $generatedAt,
    ) {}
}
