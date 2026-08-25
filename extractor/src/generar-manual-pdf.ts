/**
 * Generador del Manual del Ecosistema Contable en PDF.
 *
 * Uso: npm run manual
 *
 * Pipeline:
 *   1. Lee docs/manual-sistema.md
 *   2. Convierte Markdown → HTML con marked
 *   3. Inyecta estilos CSS para impresión A4
 *   4. Abre HTML en Chromium (Playwright)
 *   5. page.pdf() → docs/manual-sistema.pdf
 */

import { marked } from 'marked';
import { chromium } from 'playwright';
import { readFileSync, existsSync, statSync } from 'fs';
import { resolve } from 'path';

const ROOT = resolve(import.meta.dirname, '..', '..');
const MD_PATH = resolve(ROOT, 'docs', 'manual-sistema.md');
const PDF_PATH = resolve(ROOT, 'docs', 'manual-sistema.pdf');
const CSS_PATH = resolve(import.meta.dirname, 'manual-estilos.css');

// ── Leer fuentes ──────────────────────────────────────────────
if (!existsSync(MD_PATH)) {
  console.error(`❌ No se encontró ${MD_PATH}`);
  process.exit(1);
}

const md = readFileSync(MD_PATH, 'utf-8');
const css = readFileSync(CSS_PATH, 'utf-8');

console.log(`📄 Markdown: ${(md.length / 1024).toFixed(0)} KB desde ${MD_PATH}`);

// ── Configurar marked ─────────────────────────────────────────
marked.setOptions({
  gfm: true,
  breaks: false,
});

// ── Pre-procesar: extraer índice (TOC) de los headings ────────
interface TocEntry {
  level: number;
  text: string;
  id: string;
}

function slugify(text: string): string {
  return text
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-|-$/g, '');
}

function buildToc(html: string): { toc: string; body: string } {
  const headingRe = /<h([1-3])[^>]*>(.+?)<\/h\1>/gi;
  const entries: TocEntry[] = [];
  let enriched = html;

  let match: RegExpExecArray | null;
  while ((match = headingRe.exec(html)) !== null) {
    const level = parseInt(match[1]);
    const text = match[0].replace(/<[^>]+>/g, '').trim();
    // Skip the very first h1 (title page heading — will be replaced by portada)
    if (level === 1 && entries.length === 0) continue;
    const id = slugify(text);
    entries.push({ level, text, id });
    const orig = match[0];
    if (!orig.includes('id=')) {
      const withId = orig.replace(/^<h(\d)/, `<h$1 id="${id}"`);
      enriched = enriched.replace(orig, withId);
    }
  }

  let toc = '<div class="toc">\n<h2>Índice</h2>\n<ul>\n';
  for (const e of entries) {
    const cls = e.level === 1 ? 'toc-l1' : e.level === 2 ? 'toc-l2' : 'toc-l3';
    toc += `  <li class="${cls}"><a href="#${e.id}">${e.text}</a></li>\n`;
  }
  toc += '</ul>\n</div>\n';

  return { toc, body: enriched };
}

// ── Convertir MD → HTML ───────────────────────────────────────
let bodyHtml = await marked.parse(md);

// Strip the first <h1> — it becomes the cover title, not body content.
// marked renders "# Manual del Ecosistema Contable" as <h1>Manual...</h1>.
// We replace the FIRST h1 with nothing (the portada div handles it).
bodyHtml = bodyHtml.replace(/<h1[^>]*>[\s\S]*?<\/h1>/, '');

// Build TOC from the remaining headings
const { toc, body: enrichedBody } = buildToc(bodyHtml);

// ── Armar HTML completo ───────────────────────────────────────
const html = `<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Manual del Ecosistema Contable</title>
<style>${css}</style>
</head>
<body>

<div class="portada">
  <div class="logo-linea"></div>
  <h1>Manual del<br>Ecosistema Contable</h1>
  <p class="subtitulo">Sistema integral para estudios contables<br>IVA · Sueldos · AFIP · Gestión</p>
  <div class="logo-linea"></div>
  <p class="meta">
    Versión 1.0<br>
    ${new Date().toLocaleDateString('es-AR', { year: 'numeric', month: 'long', day: 'numeric' })}
  </p>
</div>

${toc}

${enrichedBody}

</body>
</html>`;

// ── Generar PDF con Playwright ─────────────────────────────────
console.log('🖥️  Abriendo Chromium...');
const browser = await chromium.launch({ headless: true });
const page = await browser.newPage();

await page.setContent(html, { waitUntil: 'networkidle' });

console.log('📑 Generando PDF...');
await page.pdf({
  path: PDF_PATH,
  format: 'A4',
  margin: { top: '2.2cm', right: '1.8cm', bottom: '2.5cm', left: '1.8cm' },
  printBackground: true,
  displayHeaderFooter: true,
  headerTemplate: '<span></span>',
  footerTemplate: `
    <div style="font-family:'Segoe UI',sans-serif;font-size:8px;color:#999;width:100%;text-align:center;padding:0 1.8cm;">
      &mdash; P&aacute;gina <span class="pageNumber"></span> de <span class="totalPages"></span> &mdash;
    </div>`,
});

await browser.close();

const sizeKB = (statSync(PDF_PATH).size / 1024).toFixed(0);
console.log(`✅ PDF generado: ${PDF_PATH} (${sizeKB} KB)`);
