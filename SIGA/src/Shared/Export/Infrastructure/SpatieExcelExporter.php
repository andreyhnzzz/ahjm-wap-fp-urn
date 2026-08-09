<?php

declare(strict_types=1);

namespace Src\Shared\Export\Infrastructure;

use Spatie\SimpleExcel\SimpleExcelWriter;
use Src\Shared\Export\Contracts\ExcelExporterInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams rows to the browser via spatie/simple-excel (OpenSpout under
 * the hood) — memory stays flat regardless of row count, since nothing
 * is buffered into a PHP array or written to a temp file on disk first.
 *
 * Uses SimpleExcelWriter::streamDownload($filename) — NOT
 * SimpleExcelWriter::create('php://output') — deliberately.
 * create() picks its writer (XLSX/CSV/ODS) by inspecting the file
 * *extension* of the path you give it; 'php://output' has no extension,
 * so OpenSpout can't tell what to write and throws
 * UnsupportedTypeException. streamDownload($filename) reads the
 * extension from $filename ('roles.xlsx') to pick the right writer,
 * while still targeting the output stream internally.
 *
 * We still never call the package's own ->toBrowser() though: that
 * calls exit() internally (confirmed in the package source — see
 * spatie/simple-excel issue #143 on GitHub), which would terminate the
 * Livewire AJAX request mid-flight instead of returning a normal
 * response for Livewire to finish handling. Calling ->addRow() then
 * ->close() ourselves, inside our OWN response()->streamDownload()
 * callback, gets the correct writer AND avoids that exit() call.
 */
final class SpatieExcelExporter implements ExcelExporterInterface
{
    /**
     * Flush PHP's output buffer periodically so bytes reach the browser
     * progressively instead of arriving all at once at the very end —
     * this is what actually makes the download start immediately for
     * large exports, matching spatie/simple-excel's own documented
     * recommendation.
     */
    private const FLUSH_EVERY = 1000;

    public function streamDownload(iterable $rows, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows, $filename): void {
            $writer = SimpleExcelWriter::streamDownload($filename);

            $count = 0;

            foreach ($rows as $row) {
                $writer->addRow($row);

                if (++$count % self::FLUSH_EVERY === 0) {
                    flush();
                }
            }

            $writer->close();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
