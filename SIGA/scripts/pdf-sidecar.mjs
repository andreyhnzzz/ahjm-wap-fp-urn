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
 *              POST /pdf     body = {"html": "...", "format": "letter"}
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

// Requests are serialized through a promise chain: one Chromium, one
// render at a time, one FRESH PAGE per render.
//
// The page used to be long-lived, which looks like the obvious
// optimisation and is not one. Measured back to back on the same machine
// state, 10,000-row renders on a reused page went 14.4s, 20.5s, then
// died with "Protocol error (Page.printToPDF): Printing failed"; the
// same three renders on fresh pages ran 17.5s, 14.8s, 13.4s and kept
// going. At 2,500 rows the two strategies are indistinguishable (1.5-3.1s
// either way — this machine has close to 2x run-to-run spread on
// identical work, which is exactly how an earlier "reuse saves 770ms"
// reading survived one measurement and not the second).
//
// So the reuse question resolved as: no reproducible speed to gain, and
// a reproducible way to fail. Fresh page every time, and no tuning knob
// to get wrong.
let queue = Promise.resolve();

async function renderPdf({ html, format = 'letter', tagged = TAGGED_DEFAULT }) {
    const page = await browser.newPage();

    try {
        // Templates are fully self-contained (no network fetches, see
        // table-pdf.blade.php) so 'load' fires immediately — never wait
        // on networkidle here, it costs 500ms flat.
        await page.setContent(html, { waitUntil: 'load' });

        // Explicit timeout: puppeteer's page.pdf() defaults to 30s, and a
        // 10,000-row report measured 13-17s untagged and ~18s tagged,
        // close enough to that default to turn into an intermittent 500
        // rather than a slow success.
        return await page.pdf({ format, printBackground: true, tagged, timeout: 300_000 });
    } finally {
        await page.close().catch(() => {});
    }
}

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
        format: searchParams.get('format') ?? 'letter',
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

    const startedAt = performance.now();

    // Chunks collected as an array and joined once: string += on a
    // multi-megabyte body is quadratic in the number of chunks.
    const chunks = [];
    req.on('data', (chunk) => chunks.push(chunk));
    req.on('end', () => {
        const body = Buffer.concat(chunks).toString('utf8');
        const readMs = performance.now() - startedAt;

        queue = queue.then(async () => {
            const queuedAt = performance.now();
            try {
                const request = parseRequest(req, body);
                const parsedAt = performance.now();
                const pdf = await renderPdf(request);

                // Reported back so the PHP-side benchmark can separate
                // "Chrome was slow" from "getting the bytes here was
                // slow" without guessing. A 10,000-row export ships 8.4 MB
                // of HTML up and a PDF back down; without this split, all
                // of it reads as render time.
                res.writeHead(200, {
                    'Content-Type': 'application/pdf',
                    'Content-Length': pdf.length,
                    'Server-Timing': [
                        `read;dur=${readMs.toFixed(1)}`,
                        `wait;dur=${(queuedAt - startedAt - readMs).toFixed(1)}`,
                        `parse;dur=${(parsedAt - queuedAt).toFixed(1)}`,
                        `render;dur=${(performance.now() - parsedAt).toFixed(1)}`,
                    ].join(', '),
                }).end(pdf);
            } catch (error) {
                res.writeHead(500).end(String(error));
            }
        });
    });
}).listen(PORT, '127.0.0.1', () => {
    console.log(`pdf-sidecar ready on http://127.0.0.1:${PORT}`);
});
