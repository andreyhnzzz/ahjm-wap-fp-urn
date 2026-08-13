/**
 * Warm-Chrome PDF sidecar.
 *
 * Browsershot's fixed cost is spawning node + require('puppeteer') per
 * export (~350-400ms, see BrowsershotConfiguration). This process pays
 * that cost once at startup and keeps one Chromium alive; each request
 * is then only setContent + print on a warm browser.
 *
 * Start with:  npm run pdf:sidecar
 * Protocol:    POST /pdf  {"html": "...", "format": "a4"}  -> PDF bytes
 * PHP side:    WarmChromePdfRenderer (falls back to Browsershot when
 *              this process is not running, so it is never required).
 */
import http from 'node:http';
import puppeteer from 'puppeteer';

const PORT = Number(process.env.PDF_SIDECAR_PORT ?? 8720);

// Same flags as BrowsershotConfiguration so both paths drive Chromium
// identically — same DOM, same layout, same PDF.
const browser = await puppeteer.launch({
    // chrome-headless-shell: measured ~25ms per printToPDF vs ~180ms in
    // full-Chrome new headless — the difference between meeting the
    // 0.14s budget and missing it.
    headless: 'shell',
    pipe: true,
    args: [
        '--disable-extensions',
        '--disable-background-networking',
        '--disable-default-apps',
        '--disable-sync',
        '--disable-translate',
        '--metrics-recording-only',
        '--mute-audio',
        '--no-first-run',
        '--safebrowsing-disable-auto-update',
        '--disable-gpu',
        '--disable-dev-shm-usage',
        '--disable-software-rasterizer',
        '--no-sandbox',
    ],
});

// One reused page, requests serialized through a promise chain.
// ponytail: single serialized page; swap for a small page pool if
// concurrent exports ever queue up noticeably.
const page = await browser.newPage();
let queue = Promise.resolve();

http.createServer((req, res) => {
    if (req.method !== 'POST' || req.url !== '/pdf') {
        res.writeHead(404).end();
        return;
    }

    let body = '';
    req.on('data', (chunk) => (body += chunk));
    req.on('end', () => {
        queue = queue.then(async () => {
            try {
                const { html, format = 'a4' } = JSON.parse(body);
                // Templates are fully self-contained (no network fetches,
                // see table-pdf.blade.php) so 'load' fires immediately —
                // never wait on networkidle here, it costs 500ms flat.
                await page.setContent(html, { waitUntil: 'load' });
                // Explicit timeout: puppeteer's page.pdf() defaults to 30s,
                // and a multi-thousand-row report near that edge turns into
                // an intermittent 500 instead of a slow success. 60s gives
                // the same headroom BrowsershotConfiguration's 30s PHP-side
                // cap effectively enforces anyway (PHP gives up first).
                const pdf = await page.pdf({ format, printBackground: true, timeout: 60_000 });
                res.writeHead(200, { 'Content-Type': 'application/pdf' }).end(pdf);
            } catch (error) {
                res.writeHead(500).end(String(error));
            }
        });
    });
}).listen(PORT, '127.0.0.1', () => {
    console.log(`pdf-sidecar ready on http://127.0.0.1:${PORT}`);
});
