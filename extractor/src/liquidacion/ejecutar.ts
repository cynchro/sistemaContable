import fs from "node:fs";
import fsp from "node:fs/promises";
import os from "node:os";
import path from "node:path";
import type { Page } from "playwright";
import {
  irALibro,
  importarDesdeArca,
  importarArchivos,
  cerrarDialogoResultados,
} from "../flows/portalIva.js";
import { esperarImportacion, listaTareas } from "../flows/esperarImportacion.js";
import { extraerLibroCsv } from "../extract/libroCsv.js";
import { generarTxtLibroIvaDigital, importarComprobantes } from "../ecosistema/client.js";
import { mapearComprobantes } from "../ecosistema/mapeo.js";
import type { Libro } from "../types.js";

/**
 * Las dos direcciones del botón "Liquidar IVA" (ver plan del 24-25/08/2026), extraídas de
 * `cli/liquidar.ts` para que el CLI manual y el `cli/worker.ts` (modo automático, consume la
 * cola de `ecosistema`) compartan la misma lógica sin duplicarla. Cada función devuelve un
 * resultado estructurado (en vez de solo loguear) — lo que reporta el worker de vuelta a
 * `ecosistema` vía `POST /iva/liquidaciones/{id}/estado`.
 */

interface ResultadoArca {
  agregados: number;
  erroneos: number;
  registros: number;
  ignorados: number;
  modificados: number;
}

export interface ResultadoTraer {
  arca: ResultadoArca;
  ecosistema: { creados: number; total: number; errores: Array<{ fila: number; error: string }> };
  descartados: number;
}

export interface ResultadoSubir {
  arca: ResultadoArca;
}

/** ARCA → ecosistema: importa desde ARCA al borrador, extrae el CSV, lo mapea y lo sube. */
export async function traerLibro(
  page: Page,
  libro: Libro,
  empresaId: number,
  periodoEcosistemaId: number,
): Promise<ResultadoTraer> {
  await irALibro(page, libro);

  // Foto ANTES de clickear "Importar" — es lo que distingue la tarea nueva de una vieja ya
  // terminada (ver docblock de `esperarImportacion`, bug real corregido el 25/08/2026).
  const previos = new Set((await listaTareas(page, libro)).map((t) => t.codigo));

  console.log(`  Importando ${libro} desde ARCA (comprobantes ya registrados)...`);
  await importarDesdeArca(page, libro);
  const tarea = await esperarImportacion(page, libro, previos);
  console.log(
    `  Tarea de importación: ${tarea.estado}, agregados=${tarea.cantidadAgregados}, ` +
      `erroneos=${tarea.cantidadErroneos}.`,
  );
  await cerrarDialogoResultados(page);

  console.log(`  Extrayendo ${libro} del borrador (CSV nativo)...`);
  const { comprobantes: scrapeados } = await extraerLibroCsv(page, libro);
  console.log(`  ${scrapeados.length} comprobantes en el borrador de ARCA.`);

  const { comprobantes, descartados } = mapearComprobantes(scrapeados, libro);
  if (descartados.length > 0) {
    console.warn(`  ${descartados.length} comprobante(s) no se pudieron mapear:`);
    for (const err of descartados) console.warn(`    - ${err.message}`);
  }

  const resultadoEco =
    comprobantes.length === 0
      ? { creados: 0, total: 0, errores: [] }
      : await importarComprobantes(empresaId, periodoEcosistemaId, libro, comprobantes);
  console.log(
    `  ecosistema: ${resultadoEco.creados}/${resultadoEco.total} creados` +
      (resultadoEco.errores.length > 0 ? `, ${resultadoEco.errores.length} con error.` : "."),
  );
  for (const e of resultadoEco.errores) console.warn(`    - fila ${e.fila}: ${e.error}`);

  return {
    arca: {
      agregados: tarea.cantidadAgregados,
      erroneos: tarea.cantidadErroneos,
      registros: tarea.cantidadRegistros,
      ignorados: tarea.cantidadIgnorados,
      modificados: tarea.cantidadModificados,
    },
    ecosistema: resultadoEco,
    descartados: descartados.length,
  };
}

/** ecosistema → ARCA: genera los 2 TXT del libro y los sube al borrador. */
export async function subirLibro(
  page: Page,
  libro: Libro,
  empresaId: number,
  periodoEcosistemaId: number,
): Promise<ResultadoSubir> {
  console.log(`  Generando TXT de ${libro} desde ecosistema...`);
  const cbte = await generarTxtLibroIvaDigital(empresaId, periodoEcosistemaId, libro, "cbte");
  const alicuotas = await generarTxtLibroIvaDigital(empresaId, periodoEcosistemaId, libro, "alicuotas");

  const dirTmp = await fsp.mkdtemp(path.join(os.tmpdir(), "liquidar-iva-"));
  const rutaCbte = path.join(dirTmp, `${libro}-cbte.txt`);
  const rutaAlicuotas = path.join(dirTmp, `${libro}-alicuotas.txt`);
  await fsp.writeFile(rutaCbte, cbte, "latin1");
  await fsp.writeFile(rutaAlicuotas, alicuotas, "latin1");

  await irALibro(page, libro);
  const previos = new Set((await listaTareas(page, libro)).map((t) => t.codigo));

  console.log(`  Subiendo ${libro} a ARCA (Importar Archivos)...`);
  await importarArchivos(page, libro, rutaCbte, rutaAlicuotas);
  const tarea = await esperarImportacion(page, libro, previos);
  console.log(
    `  Tarea de importación: ${tarea.estado}, agregados=${tarea.cantidadAgregados}, ` +
      `erroneos=${tarea.cantidadErroneos}.`,
  );
  await cerrarDialogoResultados(page);

  await fsp.rm(dirTmp, { recursive: true, force: true });

  return {
    arca: {
      agregados: tarea.cantidadAgregados,
      erroneos: tarea.cantidadErroneos,
      registros: tarea.cantidadRegistros,
      ignorados: tarea.cantidadIgnorados,
      modificados: tarea.cantidadModificados,
    },
  };
}

/** Captura de pantalla en `debug/` para diagnosticar un error real contra ARCA. */
export async function guardarCapturaDeError(page: Page): Promise<string | null> {
  try {
    const dir = path.resolve("debug");
    fs.mkdirSync(dir, { recursive: true });
    const archivo = path.join(dir, `error_liquidar_${Date.now()}.png`);
    await page.screenshot({ path: archivo, fullPage: true });
    console.error(`Captura de pantalla guardada en ${archivo}.`);
    return archivo;
  } catch (screenshotErr) {
    console.error("No se pudo tomar captura de pantalla del error:", screenshotErr);
    return null;
  }
}
