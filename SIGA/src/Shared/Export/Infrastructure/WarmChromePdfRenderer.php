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

    /**
     * The same sidecar, for callers that drive it themselves rather than
     * through render() — ChunkedChromePdfWriter issues several renders
     * concurrently through Http::pool(), which this one-shot helper
     * cannot express. Exposed here so there is still exactly one place
     * that knows where the sidecar lives.
     */
    public static function endpoint(): string
    {
        return self::ENDPOINT;
    }

    public static function render(string $html, string $paperSize): ?string
    {
        try {
            // 30s, not the typical-case ~0.14s above: a 1500+ row export
            // (Groups) measured at 17-19s end to end (see the mPDF/Dompdf/
            // Spatie spike) — a 15s cap was cutting those off and silently
            // falling back to the slower Browsershot path. Now runs as a
            // queued job (GenerateReportExportJob) regardless, so this
            // only has to survive the render, not a user's page load.
            $response = Http::connectTimeout(1)
                ->timeout(30)
                ->post(self::ENDPOINT, ['html' => $html, 'format' => $paperSize]);
        } catch (ConnectionException) {
            return null;
        }

        return $response->successful() ? $response->body() : null;
    }
}
