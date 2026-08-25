import fs from "node:fs";
import path from "node:path";
import type { Libro, PeriodoFiscal } from "../types.js";

const SALIDA_DIR = path.resolve("salida");

/**
 * Guarda una copia del CSV tal como lo entregó ARCA (ya descomprimido si
 * venía en zip, pero sin decodificar/tocar el contenido) junto a los xlsx
 * en `salida/` — a pedido del usuario, para tener también el original de
 * ARCA a mano, no solo el xlsx reprocesado.
 */
export async function guardarCsvOriginal(
  csv: Buffer,
  opts: { cuit: string; periodo: PeriodoFiscal; libro: Libro },
): Promise<string> {
  fs.mkdirSync(SALIDA_DIR, { recursive: true });

  const periodoSanitizado = opts.periodo.replace(/\//g, "-");
  const timestamp = new Date().toISOString().replace(/[:.]/g, "-");
  const nombre = `${opts.cuit}_${periodoSanitizado}_${opts.libro}_${timestamp}.csv`;
  const filePath = path.join(SALIDA_DIR, nombre);

  await fs.promises.writeFile(filePath, csv);
  return filePath;
}
