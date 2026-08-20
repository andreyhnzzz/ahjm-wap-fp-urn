<?php

declare(strict_types=1);

namespace Src\Shared\Export\Contracts;

/**
 * Writes a tabular report to a PDF on disk.
 *
 * Deliberately NOT the same port as PdfFileWriterInterface, which takes a
 * finished HTML string. At report scale that signature is the problem: a
 * single HTML document is exactly what Chromium cannot render past a few
 * thousand rows (measured — 5.000 rows 6.6s, 15.000 rows 49.6s, 45.000
 * rows fails outright with "Printing failed"). A writer that receives the
 * rows instead of the markup is free to decide how many documents that
 * becomes, which is the whole point.
 *
 * The rows arrive as an iterable so an implementation can stay lazy and
 * never hold the full report in memory; callers that already have an
 * array can pass it unchanged.
 *
 * @phpstan-type Header array{label: string}
 * @phpstan-type Row array<string, scalar|null>
 */
interface TabularPdfWriterInterface
{
    /**
     * @param  array<int, array{label: string}>  $headers
     * @param  iterable<array<string, scalar|null>>  $rows
     */
    public function write(
        string $title,
        array $headers,
        iterable $rows,
        string $absolutePath,
        string $paperSize = 'letter',
    ): void;
}
