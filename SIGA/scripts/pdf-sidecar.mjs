/**
 * Warm-Chrome PDF sidecar.
 *
 * Browsershot's fixed cost is spawning node + require('puppeteer') per
 * export (~350-400ms, see BrowsershotConfiguration). This process pays
 * that cost once at startup and keeps one Chromium alive; each request
 * is then only setContent + print on a warm browser.
 *
 * Start with:  npm run pdf:sidecar
 * Protocol:    POST /pdf     body = the HTML itself, ?format=letter
 *              POST /pdf     body = {"html": "...", "format": "a4"}
 *              GET  /health -> 200 "ok"
 * PHP side:    WarmChromePdfRenderer (falls back to Browsershot when
 *              this process is not running, so it is never required).
 */
import http from 'node:http';
import puppeteer from 'puppeteer';

const PORT = Number(process.env.PDF_SIDECAR_PORT ?? 8720);

// Chrome emits a PDF/UA structure tree by default — one /StructElem per
// <td>. On a 2500-row export that is 52,519 extra objects, 8.9 MB vs
// 0.8 MB and 2.66s vs 1.96s. PHP sends the project's configured value
// per request (config/exports.php); this is only the default for a
// hand-made curl.
const TAGGED_DEFAULT = process.env.PDF_TAGGED === 'true';

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

/**
 * Accepts the HTML either as a raw body (Content-Type: text/html, with
 * ?format= and ?tagged= in the query) or as the original JSON envelope.
 * Raw is what PHP uses: a 10,000-row export is 8.4 MB of HTML, and
 * json_encode on one side plus JSON.parse on the other is pure copying
 * of a string that is already exactly what Chrome wants.
 */
function parseRequest(req, body) {
    if ((req.headers['content-type'] ?? '').includes('application/json')) {
        return JSON.parse(body);
    }

    const { searchParams } = new URL(req.url, 'http://127.0.0.1');

    return {
        html: body,
        format: searchParams.get('format') ?? 'a4',
        tagged: searchParams.has('tagged') ? searchParams.get('tagged') === '1' : TAGGED_DEFAULT,
    };
}

http.createServer((req, res) => {
    // Cheap liveness probe so the PHP client can wait for "up" after
    // launching this process without POSTing a document to find out.
    if (req.method === 'GET' && req.url.startsWith('/health')) {
        res.writeHead(200, { 'Content-Type': 'text/plain' }).end('ok');
        return;
    }

    if (req.method !== 'POST' || !req.url.startsWith('/pdf')) {
        res.writeHead(404).end();
        return;
    }

    // Chunks collected as an array and joined once: string += on a
    // multi-megabyte body is quadratic in the number of chunks.
    const chunks = [];
    req.on('data', (chunk) => chunks.push(chunk));
    req.on('end', () => {
        const body = Buffer.concat(chunks).toString('utf8');

        queue = queue.then(async () => {
            try {
                const { html, format = 'a4', tagged = TAGGED_DEFAULT } = parseRequest(req, body);
                // Templates are fully self-contained (no network fetches,
                // see table-pdf.blade.php) so 'load' fires immediately —
                // never wait on networkidle here, it costs 500ms flat.
                await page.setContent(html, { waitUntil: 'load' });
                // Explicit timeout: puppeteer's page.pdf() defaults to 30s,
                // and a multi-thousand-row report near that edge turns into
                // an intermittent 500 instead of a slow success. 60s gives
                // the same headroom BrowsershotConfiguration's 30s PHP-side
                // cap effectively enforces anyway (PHP gives up first).
                const pdf = await page.pdf({ format, printBackground: true, tagged, timeout: 60_000 });
                res.writeHead(200, { 'Content-Type': 'application/pdf' }).end(pdf);
            } catch (error) {
                res.writeHead(500).end(String(error));
            }
        });
    });
}).listen(PORT, '127.0.0.1', () => {
    console.log(`pdf-sidecar ready on http://127.0.0.1:${PORT}`);
});
