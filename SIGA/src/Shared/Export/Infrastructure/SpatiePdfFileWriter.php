<?php

declare(strict_types=1);

namespace Src\Shared\Export\Infrastructure;

use Spatie\Browsershot\Browsershot;
use Spatie\LaravelPdf\Facades\Pdf;
use Src\Shared\Export\Contracts\PdfFileWriterInterface;

/**
 * Renders HTML to a PDF on disk through spatie/laravel-pdf, i.e. through
 * headless Chromium — the CSS layout work happens in a browser engine,
 * never in the PHP process.
 *
 * Chromium is configured by BrowsershotConfiguration, the same object
 * SpatiePdfExporter uses, so a report saved for RE-01 is identical to
 * the same report streamed from a CRUD screen.
 */
final class SpatiePdfFileWriter implements PdfFileWriterInterface
{
    public function write(string $html, string $absolutePath, string $paperSize = 'a4'): void
    {
        // Same warm-Chrome fast path as SpatiePdfExporter, same fallback —
        // a saved report stays identical to a streamed one either way.
        $pdfBytes = WarmChromePdfRenderer::render($html, $paperSize);

        if ($pdfBytes !== null) {
            file_put_contents($absolutePath, $pdfBytes);

            return;
        }

        Pdf::html($html)
            ->format($paperSize)
            ->withBrowsershot(static function (Browsershot $browsershot): void {
                BrowsershotConfiguration::apply($browsershot);
            })
            ->save($absolutePath);
    }
}
