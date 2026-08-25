import fs from "node:fs";
import path from "node:path";
import type { Page } from "playwright";
import { config, requireArcaCuit } from "../config.js";
import { hasSavedSession, openSession } from "../auth/session.js";
import { asegurarSesionVigente } from "../auth/ensureSession.js";
import { irAPortalIva, abrirDdjj, irALibro, importarDesdeArca } from "../flows/portalIva.js";
import { esperarImportacion, listaTareas } from "../flows/esperarImportacion.js";
import { extraerLibroCsv } from "../extract/libroCsv.js";
import { guardarComprobantesXlsx } from "../output/xlsx.js";
import { guardarCsvOriginal } from "../output/csvOriginal.js";
import type { PeriodoFiscal } from "../types.js";

interface Args {
  periodo: PeriodoFiscal;
  importar: boolean;
}

function parseArgs(): Args {
  const args = process.argv.slice(2);
  const idx = args.indexOf("--periodo");
  const periodo = idx >= 0 ? args[idx + 1] : undefined;

  if (!periodo) {
    throw new Error('Uso: npm run traer -- --periodo MM/YYYY [--importar] (ej. "07/2026")');
  }

  return { periodo, importar: args.includes("--importar") };
}

/**
 * Corre headless (sin pantalla), así que si algo falla no hay forma de
 * "ver" qué pasó — se guarda una captura de la página en ese momento para
 * poder diagnosticarlo sin necesitar modo headed. La sesión ya está
 * logueada en ese punto: la captura muestra pantallas post-login (datos de
 * la cuenta/comprobantes), nunca la clave.
 */
async function guardarCapturaDeError(page: Page): Promise<void> {
  try {
    const dir = path.resolve("debug");
    fs.mkdirSync(dir, { recursive: true });
    const archivo = path.join(dir, `error_${Date.now()}.png`);
    await page.screenshot({ path: archivo, fullPage: true });
    console.error(`Captura de pantalla guardada en ${archivo} (para diagnosticar el error).`);
  } catch (screenshotErr) {
    console.error("No se pudo tomar captura de pantalla del error:", screenshotErr);
  }
}

async function main() {
  const { periodo, importar } = parseArgs();
  const cuit = requireArcaCuit();

  if (!hasSavedSession(cuit)) {
    throw new Error(`No hay sesión guardada para CUIT ${cuit}. Corré "npm run login" primero.`);
  }

  const { browser, context } = await openSession(cuit);
  let paginaActual = await context.newPage();

  try {
    // ARCA invalida la sesión bastante rápido (confirmado en vivo: ~42
    // minutos) — antes de asumir que la sesión guardada sirve, se verifica y
    // si expiró se renueva sola (necesita ARCA_CLAVE_FISCAL en .env).
    await asegurarSesionVigente(paginaActual, context, cuit, config.arcaClaveFiscal || undefined);

    console.log(`Abriendo Portal IVA, período ${periodo}...`);
    // irAPortalIva abre una pestaña nueva (ARCA usa target="_blank") — de acá
    // en más se trabaja sobre esa Page, no sobre la pestaña original.
    paginaActual = await irAPortalIva(paginaActual);
    await abrirDdjj(paginaActual, periodo);

    for (const libro of ["ventas", "compras"] as const) {
      await irALibro(paginaActual, libro);

      if (importar) {
        // Por defecto NO se dispara: el click final de confirmación no se
        // probó en vivo (ver flows/portalIva.ts). Solo se ejecuta si se pasa
        // --importar a propósito.
        console.log(`Importando ${libro} desde ARCA (--importar)...`);
        const previos = new Set((await listaTareas(paginaActual, libro)).map((t) => t.codigo));
        await importarDesdeArca(paginaActual, libro);
        await esperarImportacion(paginaActual, libro, previos);
      }

      console.log(`Extrayendo ${libro} ya incluidos en el borrador (CSV)...`);
      const { comprobantes, csv } = await extraerLibroCsv(paginaActual, libro);
      console.log(`${comprobantes.length} comprobantes de ${libro} extraídos.`);

      const xlsxPath = await guardarComprobantesXlsx(comprobantes, { cuit, periodo, libro });
      console.log(`Guardado xlsx en ${xlsxPath}`);

      const csvPath = await guardarCsvOriginal(csv, { cuit, periodo, libro });
      console.log(`Guardado CSV original en ${csvPath}`);
    }
  } catch (err) {
    await guardarCapturaDeError(paginaActual);
    throw err;
  } finally {
    await browser.close();
  }
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
