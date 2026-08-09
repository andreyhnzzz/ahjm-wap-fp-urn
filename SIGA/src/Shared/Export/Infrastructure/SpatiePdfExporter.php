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
 * ->withBrowsershot()->setNodeModulePath() points Browsershot's child
 * Node process straight at the project's own node_modules explicitly,
 * rather than relying on Node's implicit directory-walking resolution
 * to find puppeteer on its own — that implicit lookup is where this
 * broke the first time (Windows, PHP spawning a Node child process from
 * public/ as its working directory, global npm install not on that
 * resolution path). Requires `npm install puppeteer` run from the
 * Laravel project root (not `public/`, not a global -g install).
 *
 * ->timeout(15) deliberately well under PHP's default 30s
 * max_execution_time: if Chrome/Puppeteer ever hangs on launch again
 * (missing Chrome binary, a stuck child process, antivirus interference
 * — all real things that happened during setup), Browsershot throws its
 * own catchable ProcessTimedOutException at 15s instead of PHP's blunt
 * execution-time FatalError killing the whole request at 30s with no
 * usable error message.
 *
 * ->showBackground() is not optional here — Chrome's print/PDF engine
 * omits background colors and images by default (the same convention
 * browsers use for "print this page" to save ink), which would silently
 * drop the navy header, the striped rows, everything that makes this
 * report look like the approved design instead of plain black-on-white
 * text. Without this call the PDF would still "work", just not match.
 *
 * ->addChromiumArguments([...]) disables a handful of background
 * network/telemetry calls Chrome makes on startup by default even in
 * headless mode (Safe Browsing list updates, component/extension
 * update checks, sync, etc.), plus a few flags that speed up a cold
 * start and matter more once this runs on a real Linux server than on
 * a Windows dev machine (disable-gpu skips graphics-driver init headless
 * doesn't need; disable-dev-shm-usage avoids Chrome writing shared
 * memory to a tmpfs that's tiny by default on many Docker/Linux hosts,
 * a very common source of random crashes in containerized deployments;
 * disable-software-rasterizer skips a GPU fallback path headless will
 * never use). None of these change what gets rendered — same DOM, same
 * layout, same PDF — only how fast and how reliably Chrome gets there.
 * Combined with the template now being fully self-contained (no Google
 * Fonts network call either, see table-pdf.blade.php), this removes
 * every network-dependent variable from render time — what's left is
 * just Chrome's own startup + layout, which is fast and consistent.
 */
final class SpatiePdfExporter implements PdfExporterInterface
{
    public function fromHtml(string $html, string $filename, string $paperSize = 'a4'): StreamedResponse
    {
        $pdfBytes = Pdf::html($html)
            ->format($paperSize)
            ->withBrowsershot(function (Browsershot $browsershot): void {
                $browsershot
                    ->setNodeModulePath(base_path('node_modules'))
                    ->timeout(15)
                    ->showBackground()
                    ->addChromiumArguments([
                        'disable-extensions',
                        'disable-background-networking',
                        'disable-default-apps',
                        'disable-sync',
                        'disable-translate',
                        'metrics-recording-only',
                        'mute-audio',
                        'no-first-run',
                        'safebrowsing-disable-auto-update',
                        'disable-gpu',
                        'disable-dev-shm-usage',
                        'disable-software-rasterizer',
                    ]);
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
