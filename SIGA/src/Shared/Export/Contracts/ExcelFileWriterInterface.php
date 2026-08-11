<?php

declare(strict_types=1);

namespace Src\Shared\Export\Contracts;

/**
 * Port for writing tabular data to a spreadsheet **file**.
 *
 * A separate contract from ExcelExporterInterface rather than a second
 * method on it, on purpose (Interface Segregation): streaming a download
 * and producing a file on disk are different capabilities with different
 * callers. Every CRUD screen streams and never writes files; RE-01
 * writes files and never streams, because it has to produce the .xlsx
 * *and* the .pdf in a single operation and an HTTP response can only
 * carry one of them. Merging both into one interface would force every
 * implementer to support a capability its callers never ask for.
 *
 * No Symfony/HTTP type appears here at all — writing a file has nothing
 * to do with a request.
 */
interface ExcelFileWriterInterface
{
    /**
     * @param  iterable<array<string, scalar|null>>  $rows  Each element is one
     *                                                      row as an associative array; the keys of the first row become
     *                                                      the header row. Accepts any iterable so a lazy source keeps
     *                                                      memory flat.
     * @param  string  $absolutePath  Full destination path. The extension
     *                                selects the format (.xlsx, .csv, …) and the directory is
     *                                expected to exist — creating it is the caller's decision,
     *                                not this adapter's.
     */
    public function write(iterable $rows, string $absolutePath): void;
}
