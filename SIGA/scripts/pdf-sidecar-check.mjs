/**
 * Self-check for the warm-Chrome sidecar: POSTs a representative HTML
 * document 10 times, asserts every response is a real PDF and that the
 * median render time meets the budget. Fails loudly otherwise.
 *
 * The budget is 0.20s, raised from 0.14s, and the reason is a deliberate
 * trade rather than a threshold nudged to make a test pass. The sidecar
 * used to hold one long-lived page, which put this check's median near
 * 45ms; it now opens a fresh page per render because a reused one was
 * measured dying on the third 10,000-row export ("Protocol error
 * (Page.printToPDF): Printing failed"). That costs ~60ms of newPage on
 * every render, so the honest median for the current design is ~105ms —
 * and a 140ms budget would leave a single scheduling hiccup (one run
 * here measured 144ms) able to fail the build for no real regression.
 *
 * The number to watch is the median against 200ms. If it climbs back
 * toward that, something has genuinely regressed.
 *
 * This budget is deliberately NOT scaled by the host's core count, even
 * though the render pool next to it is. The ten renders below are
 * sequential: each one occupies one core, and how many OTHER cores the
 * machine has does not make that core faster. Scaling it by cores would
 * be a number that looks measured and is not — a slower budget on a
 * 4-core CI runner that would equally excuse a real regression on a
 * 4-core developer machine. What a genuinely slower-per-core host gets
 * instead is PDF_SIDECAR_CHECK_BUDGET_MS: a declared override, with
 * whoever declared it on the hook for the number.
 *
 * The host profile is printed either way, because "the median was 260ms"
 * is unactionable without knowing what machine produced it.
 *
 * Run with the sidecar already up:  node scripts/pdf-sidecar-check.mjs
 */
import assert from 'node:assert';
import http from 'node:http';
import { logicalCores, renderConcurrency } from './host-profile.mjs';

const BUDGET_MS = Number(process.env.PDF_SIDECAR_CHECK_BUDGET_MS ?? 200);
const CORES = logicalCores();

const ENDPOINT = `http://127.0.0.1:${process.env.PDF_SIDECAR_PORT ?? 8720}/pdf`;

const html = `<!doctype html><html><head><style>
  @page { size: letter; margin: 1cm; }
  body { font-family: sans-serif; }
  th { background: #1e3a5f; color: #fff; padding: 4px 8px; }
  tr:nth-child(even) td { background: #eef2f7; }
  td { padding: 4px 8px; }
</style></head><body><h1>Reporte de prueba</h1><table>
<tr><th>Docente</th><th>Grupo</th><th>Carga</th></tr>
${Array.from({ length: 60 }, (_, i) => `<tr><td>Docente ${i}</td><td>G-${i}</td><td>${i % 10}</td></tr>`).join('')}
</table></body></html>`;

const times = [];
for (let i = 0; i < 10; i++) {
    const start = performance.now();
    const res = await fetch(ENDPOINT, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ html, format: 'letter' }),
    });
    const bytes = Buffer.from(await res.arrayBuffer());
    times.push(performance.now() - start);

    assert.strictEqual(res.status, 200, `run ${i}: HTTP ${res.status}`);
    assert.strictEqual(bytes.subarray(0, 5).toString(), '%PDF-', `run ${i}: not a PDF`);
    assert.ok(bytes.length > 1000, `run ${i}: suspiciously small PDF (${bytes.length} bytes)`);
}

// The sidecar renders whatever HTML reaches its port, and setContent()
// makes Chrome fetch every subresource in that HTML. Before the renderer
// was told to refuse non-data: URLs, a posted <img src="http://..."> was
// measured producing a real hit on the target — on a cloud host the
// target of choice being the instance metadata endpoint, with the
// response coming back inside the PDF.
//
// A listener of our own is the only honest way to assert a negative: if
// it is never hit, nothing went out.
let reached = false;
const bait = http.createServer((_, res) => {
    reached = true;
    res.writeHead(200, { 'Content-Type': 'image/svg+xml' }).end('<svg xmlns="http://www.w3.org/2000/svg"/>');
});

await new Promise((resolve) => bait.listen(0, '127.0.0.1', resolve));
const baitUrl = `http://127.0.0.1:${bait.address().port}/should-never-be-fetched.svg`;

const probe = await fetch(ENDPOINT, {
    method: 'POST',
    headers: { 'Content-Type': 'text/html' },
    body: `<!doctype html><html><body><p>probe</p><img src="${baitUrl}"></body></html>`,
});
const probeBytes = Buffer.from(await probe.arrayBuffer());

// Give a slow fetch time to arrive before declaring it never happened.
await new Promise((resolve) => setTimeout(resolve, 500));
bait.close();

assert.strictEqual(probeBytes.subarray(0, 5).toString(), '%PDF-', 'the SSRF probe did not even render');
assert.ok(!reached, `the renderer fetched ${baitUrl} — remote subresources are not being blocked`);
console.log('OK — the renderer refused a remote subresource');

times.sort((a, b) => a - b);
const median = times[Math.floor(times.length / 2)];
console.log(`host: ${CORES} cores, render pool ${renderConcurrency(CORES)}`);
console.log(`runs: ${times.map((t) => t.toFixed(0)).join(', ')} ms`);
console.log(`median: ${median.toFixed(0)} ms (budget ${BUDGET_MS} ms)`);
assert.ok(median <= BUDGET_MS, `median ${median.toFixed(0)}ms exceeds the ${BUDGET_MS}ms budget`);
console.log(`OK — warm-Chrome sidecar meets the ${(BUDGET_MS / 1000).toFixed(2)}s budget`);
