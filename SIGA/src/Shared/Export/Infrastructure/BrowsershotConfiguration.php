<?php

declare(strict_types=1);

namespace Src\Shared\Export\Infrastructure;

use Spatie\Browsershot\Browsershot;

/**
 * The single place that knows how this application drives headless
 * Chromium. Extracted so the streaming adapter (SpatiePdfExporter) and
 * the file-writing one (SpatiePdfFileWriter) cannot drift apart on
 * timeouts, backgrounds or flags — a PDF written to disk for RE-01 has
 * to come out byte-identical to the same PDF streamed to the browser.
 *
 * setNodeModulePath() points Browsershot's child Node process straight
 * at the project's own node_modules instead of relying on Node's
 * implicit directory-walking resolution to find puppeteer — that
 * implicit lookup is where this broke the first time (Windows, PHP
 * spawning Node with public/ as its working directory, a global npm
 * install that was not on the resolution path). Requires
 * `npm install puppeteer` from the Laravel project root.
 *
 * timeout() sits deliberately below PHP's default 30s
 * max_execution_time: if Chrome ever hangs on launch (missing binary,
 * stuck child process, antivirus interference — all real things that
 * happened during setup), Browsershot throws its own catchable
 * ProcessTimedOutException instead of PHP's blunt FatalError killing the
 * request with no usable message.
 *
 * showBackground() is not optional: Chrome's print engine drops
 * background colours and images by default (the same convention browsers
 * use for "print this page" to save ink), which would silently strip the
 * navy header and the striped rows out of every report.
 *
 * The Chromium arguments disable background network/telemetry calls
 * Chrome makes on startup even headless (Safe Browsing updates,
 * component checks, sync), plus a few flags that matter more on a real
 * Linux server than on a Windows dev machine: disable-gpu skips
 * graphics-driver init headless does not need; disable-dev-shm-usage
 * avoids Chrome writing shared memory to a tmpfs that is tiny by default
 * on many Docker hosts, a very common source of random container
 * crashes; disable-software-rasterizer skips a GPU fallback path
 * headless will never take. None of them change what gets rendered —
 * same DOM, same layout, same PDF — only how fast and how reliably
 * Chrome gets there.
 */
final class BrowsershotConfiguration
{
    /**
     * @var array<int, string>
     */
    private const CHROMIUM_ARGUMENTS = [
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
    ];

    /**
     * Seconds before Browsershot gives up on Chrome.
     */
    private const TIMEOUT = 15;

    public static function apply(Browsershot $browsershot): void
    {
        $browsershot
            ->setNodeModulePath(base_path('node_modules'))
            ->timeout(self::TIMEOUT)
            ->showBackground()
            ->addChromiumArguments(self::CHROMIUM_ARGUMENTS);
    }
}
