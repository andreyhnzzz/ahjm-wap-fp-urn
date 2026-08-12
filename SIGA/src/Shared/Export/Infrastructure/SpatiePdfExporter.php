<?php

declare(strict_types=1);

namespace Src\Shared\Export\Infrastructure;

use Spatie\Browsershot\Browsershot;
use Spatie\LaravelPdf\Facades\Pdf;
use Src\Shared\Export\Contracts\PdfExporterInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Renders HTML to PDF via spatie/laravel-pdf, which delegates the
 * actual rendering to headless Chromium through Browsershot — the heavy
 * CSS/Tailwind layout work happens in the browser engine, never in the
 * PHP process itself.
 *
 * Uses ->generatePdfContent() (raw bytes) rather than the package's own
 * ->name()/Responsable path deliberately: that path has a documented,
 * open issue specifically inside Livewire ("Livewire needs pdf as a
 * string not base64" — spatie/laravel-pdf discussion #120), where the
 * PDF arrives base64-encoded instead of as a clean binary stream.
 * Generating the bytes ourselves and handing them to Laravel's own
 * response()->streamDownload() sidesteps that entirely, and keeps this
 * adapter symmetric with SpatieExcelExporter.
 *
 * How Chromium itself is driven (node_modules path, timeout,
 * backgrounds, startup flags) lives in BrowsershotConfiguration, shared
 * with SpatiePdfFileWriter so a streamed PDF and a saved one can never
 * come out looking different. Combined with the template being fully
 * self-contained (no Google Fonts network call either, see
 * table-pdf.blade.php), that removes every network-dependent variable
 * from render time — what is left is Chrome's own startup and layout,
 * which is fast and consistent.
 */
final class SpatiePdfExporter implements PdfExporterInterface
{
    public function fromHtml(string $html, string $filename, string $paperSize = 'a4'): StreamedResponse
    {
        // Warm-Chrome sidecar first (sub-0.14s when running), Browsershot
        // as the always-working fallback. See WarmChromePdfRenderer.
        $pdfBytes = WarmChromePdfRenderer::render($html, $paperSize)
            ?? Pdf::html($html)
                ->format($paperSize)
                ->withBrowsershot(static function (Browsershot $browsershot): void {
                    BrowsershotConfiguration::apply($browsershot);
                })
                ->generatePdfContent();

        // We already have the complete PDF in memory at this point (unlike
        // the Excel export, which genuinely streams row-by-row without ever
        // knowing the total size upfront) — so, unlike Excel, we CAN tell
        // the browser the exact byte count via Content-Length. That gets
        // you an accurate download progress bar instead of an "unknown
        // size" spinner; free correctness, not a performance trick.
        return response()->streamDownload(function () use ($pdfBytes): void {
            echo $pdfBytes;
        }, $filename, [
            'Content-Type' => 'application/pdf',
            'Content-Length' => (string) strlen($pdfBytes),
        ]);
    }
}
