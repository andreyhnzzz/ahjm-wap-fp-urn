<?php

declare(strict_types=1);

namespace Src\Shared\Export\Infrastructure;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Client for the warm-Chrome sidecar (scripts/pdf-sidecar.mjs), the
 * long-lived Node process that BrowsershotConfiguration's own comments
 * identify as the only way past Browsershot's ~350-400ms per-request
 * node-spawn cost. With the sidecar running, a render is a localhost
 * POST against an already-launched Chromium — measured well under the
 * 0.14s budget.
 *
 * Returns null when the sidecar is not running (connection refused) or
 * errors, so callers fall back to the Browsershot path unchanged: the
 * sidecar is an accelerator, never a requirement. No new dependency —
 * puppeteer was already installed for Browsershot, Http is Laravel's own.
 */
final class WarmChromePdfRenderer
{
    public static function render(string $html, string $paperSize): ?string
    {
        try {
            $response = Http::connectTimeout(1)
                // Sized for the documented ceiling, not the typical case:
                // 10,000 rows measured ~11s. A cap that sits inside the
                // working range does not protect anything, it just turns
                // slow-but-correct into a silent fallback to the slower
                // path (which is what a 15s cap was doing here).
                ->timeout((int) config('exports.pdf.sidecar.render_timeout'))
                ->post(self::endpoint('/pdf'), [
                    'html' => $html,
                    'format' => $paperSize,
                    'tagged' => (bool) config('exports.pdf.tagged'),
                ]);
        } catch (ConnectionException) {
            return null;
        }

        return $response->successful() ? $response->body() : null;
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
