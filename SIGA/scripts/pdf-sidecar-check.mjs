/**
 * Self-check for the warm-Chrome sidecar: POSTs a representative HTML
 * document 10 times, asserts every response is a real PDF and that the
 * median render time meets the 0.14s budget. Fails loudly otherwise.
 *
 * Run with the sidecar already up:  node scripts/pdf-sidecar-check.mjs
 */
import assert from 'node:assert';

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

times.sort((a, b) => a - b);
const median = times[Math.floor(times.length / 2)];
console.log(`runs: ${times.map((t) => t.toFixed(0)).join(', ')} ms`);
console.log(`median: ${median.toFixed(0)} ms`);
assert.ok(median <= 140, `median ${median.toFixed(0)}ms exceeds the 140ms budget`);
console.log('OK — warm-Chrome sidecar meets the 0.14s budget');
