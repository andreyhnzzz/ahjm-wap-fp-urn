<?php

declare(strict_types=1);

namespace Src\Reporting\OfferReport\Domain\Contracts;

use Src\Reporting\OfferReport\Domain\ValueObjects\StoredOfferReport;

/**
 * Write port for the two artifacts RE-01 requires.
 *
 * It takes an already-labelled table (headers + rows) rather than the
 * OfferReport entity, and that is deliberate. Column captions are
 * user-facing Spanish text resolved through __(), which is a
 * Presentation concern; letting the archive build them would drag
 * translation into infrastructure and quietly give this module a second
 * opinion about what a column is called. The Presentation adapter owns
 * the labels — exactly as it already does for every CRUD export — and
 * the archive owns nothing but bytes on disk.
 *
 * Both files are written by a single store() call, never one each,
 * because RE-01 treats them as one deliverable: an implementation that
 * could produce the spreadsheet and fail on the PDF while still
 * reporting success would not satisfy the requirement.
 */
interface OfferReportArchiveInterface
{
    /**
     * @param  array<int, array{key: string, label: string}>  $headers
     * @param  array<int, array<string, scalar|null>>  $rows  Row values keyed
     *                                                        by the header labels above, ready to be written as-is.
     */
    public function store(string $term, string $title, array $headers, array $rows): StoredOfferReport;

    /**
     * The artifacts of a previous generation, or null when the term has
     * never been generated (or the files were cleaned up).
     */
    public function find(string $term): ?StoredOfferReport;
}
