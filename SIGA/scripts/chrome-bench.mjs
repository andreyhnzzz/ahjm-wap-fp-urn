/**
 * Donde se va el tiempo de Chromium con tablas grandes.
 *
 * Mide setContent + pdf() sobre un Chromium ya caliente: el numero es
 * layout puro, sin arranque de node ni spawn.
 *
 *   node scripts/chrome-bench.mjs <dir-con-tNNNN.html>
 */
import puppeteer from 'puppeteer';
import fs from 'node:fs';

const DIR = process.argv[2];
const browser = await puppeteer.launch({
    headless: 'shell', pipe: true,
    args: ['--no-sandbox', '--disable-gpu', '--disable-dev-shm-usage',
           '--disable-software-rasterizer', '--disable-extensions',
           '--disable-background-networking'],
});

async function medir(etiqueta, html) {
    const page = await browser.newPage();
    try {
        const t0 = Date.now();
        await page.setContent(html, { waitUntil: 'load', timeout: 300000 });
        const carga = Date.now() - t0;
        const t1 = Date.now();
        const pdf = await page.pdf({ format: 'letter', printBackground: true,
                                     timeout: 300000 });
        const impreso = Date.now() - t1;
        console.log(`  ${etiqueta.padEnd(32)} ${String(carga + impreso).padStart(7)}ms` +
                    `   (carga ${carga} + pdf ${impreso})   ${(pdf.length / 1048576).toFixed(1)}MB`);
        return { ms: carga + impreso, mb: pdf.length / 1048576 };
    } catch (e) {
        console.log(`  ${etiqueta.padEnd(32)}   FALLA: ${String(e.message).slice(0, 44)}`);
        return null;
    } finally {
        await page.close();
    }
}

const sinPuntos = (h) => h.replace(/\.dots-accent\s*\{[^}]*\}/, '.dots-accent{display:none}');
const sinRayado = (h) => h.replace(/tbody tr:nth-child\(even\)\s*\{[^}]*\}/, '')
                          .replace(/tbody tr:nth-child\(odd\)\s*\{[^}]*\}/, '');
const sinTarjeta = (h) => h.replace(/\.table-card\s*\{[^}]*\}/,
                          '.table-card{margin-top:26px;border:1px solid #d7dce6;}');
const minificado = (h) => h.replace(/>\s+</g, '><');
const sinFuentes = (h) => h.replace(/@font-face\s*\{[^}]*\}/g, '')
                           .replace(/'Archivo'/g, 'Arial').replace(/'Source Sans 3'/g, 'Arial');

const base = fs.readFileSync(`${DIR}/t5000.html`, 'utf8');

console.log('\n  QUE PESA (5.000 filas)');
await medir('baseline', base);
await medir('sin dots-accent (gradiente)', sinPuntos(base));
await medir('sin nth-child', sinRayado(base));
await medir('sin radius/overflow tarjeta', sinTarjeta(base));
await medir('sin indentacion', minificado(base));
await medir('sin fuentes embebidas', sinFuentes(base));

const magro = (h) => sinFuentes(minificado(sinTarjeta(sinRayado(sinPuntos(h)))));
await medir('TODO junto', magro(base));

console.log('\n  ESCALA con la version magra');
for (const n of [1000, 2000, 5000, 15000, 45000]) {
    const f = `${DIR}/t${n}.html`;
    if (fs.existsSync(f)) await medir(`${n} filas`, magro(fs.readFileSync(f, 'utf8')));
}

await browser.close();
