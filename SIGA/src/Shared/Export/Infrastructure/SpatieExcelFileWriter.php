<?php

declare(strict_types=1);

namespace Src\Shared\Export\Infrastructure;

use Spatie\SimpleExcel\SimpleExcelWriter;
use Src\Shared\Export\Contracts\ExcelFileWriterInterface;

/**
 * Writes rows straight to a path with spatie/simple-excel (OpenSpout
 * underneath), one row at a time — memory stays flat no matter how many
 * rows arrive.
 *
 * SimpleExcelWriter::create() picks its writer by inspecting the file
 * extension, which is exactly right here (unlike the streaming adapter,
 * where the target has no extension and streamDownload() has to be used
 * instead).
 */
final class SpatieExcelFileWriter implements ExcelFileWriterInterface
{
    public function write(iterable $rows, string $absolutePath): void
    {
        $writer = SimpleExcelWriter::create($absolutePath);

        foreach ($rows as $row) {
            $writer->addRow($row);
        }

        $writer->close();
    }
}
