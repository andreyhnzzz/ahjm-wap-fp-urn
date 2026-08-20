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

// A small pool of reused pages.
//
// This started as one page behind a promise chain, with a note saying to
// swap in a pool "if concurrent exports ever queue up noticeably". They
// did: a 45.000-row report is rendered as chunks fired concurrently
// (ChunkedChromePdfWriter), and against a single serialized page those
// renders simply queued — 63s wall clock for work that takes 17s when it
// actually runs in parallel. Chromium layout is CPU-bound, so the pool
// size tracks cores rather than I/O: on a 12-core machine six pages
// measured 19.9s of render and ten measured 17.4s.
//
// Ten, and do not lower it without lowering
// ChunkedChromePdfWriter::PARALLEL_REQUESTS to match. That side fires ten
// renders at a time; a smaller pool here quietly turns the extra ones
// back into a queue, which is the exact bug this replaced — only harder
// to see, because everything still works, just slowly.
//
// Pages are reused, not created per request: newPage() costs ~40ms and
// the whole point of this process is not paying setup costs per export.
const POOL_SIZE = Number(process.env.PDF_SIDECAR_PAGES ?? 10);
const idle = await Promise.all(
    Array.from({ length: POOL_SIZE }, () => browser.newPage()),
);
const waiting = [];

// A page that has never printed pays one-off costs on its first pdf()
// (font stack, print pipeline). With ten of them that landed entirely on
// whoever exported first after a restart: measured 23.2s against 16-17s
// for every run after it. One throwaway render each moves that cost to
// startup, where nobody is waiting.
await Promise.all(idle.map(async (page) => {
    await page.setContent('<p>warmup</p>', { waitUntil: 'load' });
    await page.pdf({ format: 'letter', printBackground: true });
}));

/** Hands out a free page, or queues until one is released. */
function acquire() {
    const page = idle.pop();
    return page ? Promise.resolve(page) : new Promise((resolve) => waiting.push(resolve));
}

function release(page) {
    const next = waiting.shift();
    if (next) {
        next(page);
        return;
    }
    idle.push(page);
}

http.createServer((req, res) => {
    if (req.method !== 'POST' || req.url !== '/pdf') {
        res.writeHead(404).end();
        return;
    }

    let body = '';
    req.on('data', (chunk) => (body += chunk));
    req.on('end', () => {
        acquire().then(async (page) => {
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
            } finally {
                // Drop the DOM before parking the page. A reused page holds
                // its last document until the next setContent, so ten pages
                // sat on ten chunks' worth of rows between exports; with
                // 1.500-row chunks that was enough resident DOM to make an
                // occasional export take 38s instead of 17 while Chromium
                // reclaimed it. Emptying costs ~1ms and bounds the memory.
                try {
                    await page.setContent('', { waitUntil: 'load' });
                } catch {
                    // A page that will not even clear is not worth keeping,
                    // but the request already succeeded — do not fail it.
                }

                // Always: a page leaked on an error path would shrink the
                // pool by one until the process restarts, and the symptom
                // would be exports getting mysteriously slower over days.
                release(page);
            }
        });
    });
}).listen(PORT, '127.0.0.1', () => {
    console.log(`pdf-sidecar ready on http://127.0.0.1:${PORT} (${POOL_SIZE} pages)`);
});
