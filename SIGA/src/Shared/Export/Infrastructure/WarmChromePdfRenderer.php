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
    // ponytail: fixed localhost endpoint; lift to config() only if a
    // deployment ever needs a different port than PDF_SIDECAR_PORT=8720.
    private const ENDPOINT = 'http://127.0.0.1:8720/pdf';

    public static function render(string $html, string $paperSize): ?string
    {
        try {
            $response = Http::connectTimeout(1)
                ->timeout(15)
                ->post(self::ENDPOINT, ['html' => $html, 'format' => $paperSize]);
        } catch (ConnectionException) {
            return null;
        }

        return $response->successful() ? $response->body() : null;
    }
}
