<?php

declare(strict_types=1);

namespace Src\Shared\Export\Contracts;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Port for streaming tabular data to the browser as a downloadable
 * spreadsheet. Deliberately entity-agnostic — Role, Permission, and
 * every future CRUD share this exact same contract instead of each
 * getting its own {Entity}ExporterInterface. Exporting rows to a file
 * needs zero domain knowledge (unlike a Repository, which genuinely
 * needs to know the entity to persist it correctly), so one shared port
 * avoids N nearly-identical interfaces for what is really one capability.
 *
 * The only "framework" dependency here is Symfony's HttpFoundation
 * (which underlies Laravel itself, not a third-party export library) —
 * this is the natural, stable shape of "a streamed HTTP download",
 * not an infrastructure leak. No Spatie/OpenSpout type appears here;
 * only Infrastructure/SpatieExcelExporter.php is allowed to import those.
 */
interface ExcelExporterInterface
{
    /**
     * @param  iterable<array<string, scalar|null>>  $rows  Each element is one
     *                                                      spreadsheet row as an associative array; the keys of the
     *                                                      first row become the header row. Accepts any iterable
     *                                                      (array, Generator, LazyCollection, …) specifically so the
     *                                                      caller can pass a lazily-evaluated source and never hold
     *                                                      the full result set in memory at once.
     */
    public function streamDownload(iterable $rows, string $filename): StreamedResponse;
}
