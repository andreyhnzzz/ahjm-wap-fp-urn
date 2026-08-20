<?php

declare(strict_types=1);

namespace Src\Shared\Export\Infrastructure;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Client for the warm-Chrome sidecar (scripts/pdf-sidecar.mjs), the
 * long-lived Node process that BrowsershotConfiguration's own comments
 * identify as the only way past Browsershot's per-request cost: a fresh
 * `node` + `require('puppeteer')` + Chromium launch, measured at ~2.2s
 * of fixed overhead per export before Chrome is asked to lay out a
 * single row. With the sidecar running, a render is a localhost POST
 * against an already-launched Chromium.
 *
 * Two things this class does beyond posting HTML:
 *
 * 1. It launches the sidecar when nothing is listening. That is the
 *    whole point of "from a clean start": the first export after boot
 *    used to be the slow one, every time. Booting the sidecar measures
 *    faster than one Browsershot render, so waiting for it is not a
 *    gamble — and if it does not come up within the boot timeout, the
 *    caller falls back to Browsershot exactly as before.
 *
 * 2. It sends the HTML as a raw request body rather than wrapped in
 *    JSON. A 10,000-row export is 8.4 MB of HTML; json_encode on this
 *    side and JSON.parse on the other are two full copies of a string
 *    that is already exactly what Chrome wants.
 *
 * Returns null when the sidecar is unreachable or errors, so callers
 * fall back to the Browsershot path unchanged: the sidecar is an
 * accelerator, never a requirement. No new dependency — puppeteer was
 * already installed for Browsershot, Http is Laravel's own.
 */
final class WarmChromePdfRenderer
{
    public static function render(string $html, string $paperSize): ?string
    {
        $bytes = self::post($html, $paperSize);

        if ($bytes !== null) {
            return $bytes;
        }

        return self::boot() ? self::post($html, $paperSize) : null;
    }

    /**
     * Makes sure the sidecar is reachable, booting it if needed. For a
     * caller that is about to fire several renders through Http::pool()
     * (ChunkedChromePdfWriter) rather than one at a time through render():
     * the pool has no chance to trigger boot() itself mid-flight, so the
     * boot dance has to happen once, up front, before the pool opens.
     */
    public static function ensureRunning(): bool
    {
        try {
            if (Http::connectTimeout(1)->timeout(2)->get(self::endpoint('/health'))->successful()) {
                return true;
            }
        } catch (ConnectionException) {
            // Falls through to boot().
        }

        return self::boot();
    }

    /**
     * The URL a single chunk render should be posted to — same protocol
     * post() uses (raw body, format/tagged in the query string), exposed
     * so a caller driving several renders through Http::pool() can build
     * each request itself instead of going through render()'s one-shot
     * signature.
     */
    public static function pdfEndpoint(string $paperSize): string
    {
        return self::endpoint('/pdf', [
            'format' => $paperSize,
            'tagged' => config('exports.pdf.tagged') ? '1' : '0',
        ]);
    }

    private static function post(string $html, string $paperSize): ?string
    {
        try {
            $response = Http::connectTimeout(1)
                // Sized for the documented ceiling, not the typical case:
                // 10,000 rows measured ~11s. A cap that sits inside the
                // working range does not protect anything, it just turns
                // slow-but-correct into a silent fallback to the slower
                // path (which is what a 15s cap was doing here).
                ->timeout((int) config('exports.pdf.sidecar.render_timeout'))
                ->withBody($html, 'text/html')
                ->post(self::pdfEndpoint($paperSize));
        } catch (ConnectionException) {
            return null;
        }

        return $response->successful() ? $response->body() : null;
    }

    /**
     * Launches the sidecar and waits for it to answer /health.
     *
     * Detached on purpose, and not through Symfony/Laravel's Process:
     * those keep a handle whose destructor stops the child, so the
     * sidecar would die with the request that started it — the exact
     * opposite of a long-lived process. The platform-specific one-liners
     * below hand the process to the OS and return immediately.
     */
    private static function boot(): bool
    {
        if (! config('exports.pdf.sidecar.autostart')) {
            return false;
        }

        // Only one process may launch a sidecar; the rest wait for the
        // port. Without this, a queue worker running several exports
        // concurrently would spawn a Node process per export, all but one
        // of which would fail to bind and exit — noise in the log and a
        // burst of pointless Chromium launches.
        $lock = base_path('storage/framework/pdf-sidecar.lock');
        $handle = @fopen($lock, 'c');

        if ($handle === false) {
            return false;
        }

        if (! flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);

            // Someone else is booting it. Wait for the same port they are.
            return self::awaitHealth();
        }

        $script = base_path('scripts/pdf-sidecar.mjs');

        if (! is_file($script)) {
            flock($handle, LOCK_UN);
            fclose($handle);

            return false;
        }

        self::spawnDetached($script);

        $up = self::awaitHealth();

        flock($handle, LOCK_UN);
        fclose($handle);

        if (! $up) {
            // Not fatal — the caller renders through Browsershot. Logged
            // because "exports are mysteriously slow" is otherwise
            // invisible: both paths produce the same correct PDF.
            Log::warning('pdf-sidecar did not come up; falling back to Browsershot', ['script' => $script]);
        }

        return $up;
    }

    /**
     * Starts the sidecar with no file descriptor of ours, so it outlives
     * this process without holding anything of it open.
     *
     * That second half is not a detail. Every obvious Windows spawn —
     * `start /B`, `start` in a new console, `proc_open` with NUL on all
     * three streams, with or without bypass_shell — was measured leaving
     * the sidecar holding this process's stdout: PHP exits, and anything
     * reading its output (an artisan run piped to a log, a CI step,
     * `php artisan pdf:bench | tail`) waits forever for an EOF that only
     * arrives when the sidecar is killed. CreateProcess inherits handles
     * whether or not they are the child's std handles. PowerShell's
     * Start-Process is the one launcher of the set that does not: it was
     * verified to release the pipe *and* leave the sidecar running.
     *
     * On POSIX, `sh -c '... &'` with all three streams redirected already
     * has that property.
     */
    private static function spawnDetached(string $script): void
    {
        $command = PHP_OS_FAMILY === 'Windows'
            ? 'powershell -NoProfile -NonInteractive -Command '.escapeshellarg(
                "Start-Process -FilePath node -ArgumentList '{$script}' -WindowStyle Hidden",
            )
            : 'node '.escapeshellarg($script).' < /dev/null > /dev/null 2>&1 &';

        $handle = popen($command, 'r');

        if ($handle !== false) {
            pclose($handle);
        }
    }

    /**
     * Polls /health until the sidecar answers or the boot budget runs
     * out. Short sleeps rather than one long one: the sidecar is usually
     * ready well before the timeout, and every millisecond spent waiting
     * here is on the user's export.
     */
    private static function awaitHealth(): bool
    {
        $deadline = microtime(true) + (float) config('exports.pdf.sidecar.boot_timeout');

        while (microtime(true) < $deadline) {
            usleep(100_000);

            try {
                if (Http::connectTimeout(1)->timeout(2)->get(self::endpoint('/health'))->successful()) {
                    return true;
                }
            } catch (ConnectionException) {
                // Still starting. Keep waiting until the deadline.
            }
        }

        return false;
    }

    /**
     * @param  array<string, string>  $query
     */
    private static function endpoint(string $path, array $query = []): string
    {
        $url = 'http://127.0.0.1:'.config('exports.pdf.sidecar.port').$path;

        return $query === [] ? $url : $url.'?'.http_build_query($query);
    }
}
