<?php

declare(strict_types=1);

namespace Src\Shared\Export\Contracts;

/**
 * Port for rendering HTML to a PDF **file**. Same segregation reasoning
 * as ExcelFileWriterInterface: RE-01 needs two artifacts from one
 * action, which rules out the streaming contract.
 *
 * Takes a raw HTML string, not a view name plus data, so the contract
 * never assumes Blade exists.
 */
interface PdfFileWriterInterface
{
    /**
     * @param  string  $paperSize  Any size the underlying renderer accepts
     *                             ('a4', 'letter', 'legal', …).
     */
    public function write(string $html, string $absolutePath, string $paperSize = 'a4'): void;
}
