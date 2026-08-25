import fs from "node:fs/promises";
import path from "node:path";
import type { Page } from "playwright";
import JSZip from "jszip";
import { parseCsv, aNumeroArg, normalizarFecha } from "./csv.js";
import type { AlicuotaDetalle, ComprobanteScrapeado, Libro } from "../types.js";

/**
 * Extracción vía el botón "CSV" de la grilla (DataTables Buttons →
 * `buttons.html5.min.js`, confirmado cargado en la exploración en vivo).
 * Reemplaza el enfoque anterior basado en el endpoint interno
 * `ajax.do?f=listaComprobantesIncluidos` (ver libroApi.ts, hoy sin uso):
 * ese endpoint solo trae neto/IVA TOTALES por comprobante — este CSV trae
 * el desglose completo por alícuota + percepciones + impuestos, que es lo
 * que necesitamos para reconstruir un comprobante equivalente al modelo de
 * `venta_discriminaciones`/`compra_discriminaciones` de ecosistema.
 *
 * Formato confirmado con dos ejemplos reales (`flujo/*.csv`, otro cliente y
 * período — ver plan-factibilidad-portal-iva.md): separador `;`, decimal
 * con coma, encoding ISO-8859-1, primera fila con encabezados.
 *
 * Selector del botón CSV confirmado en vivo (2026-07-26): NO tiene una clase
 * distintiva como Excel/PDF (`buttons-excel`/`buttons-pdf`) — es el primer
 * botón dentro de `.dt-buttons`, sin más. Se valida el texto antes de
 * clickear para no romper en silencio si ARCA reordena los botones.
 */
const ALICUOTAS_PCT = [0, 2.5, 5, 10.5, 21, 27] as const;

function campo(fila: Record<string, string>, nombre: string): string | undefined {
  return nombre in fila ? fila[nombre] : undefined;
}

/**
 * El botón CSV de esta app a veces entrega el archivo **comprimido en un
 * zip** (confirmado en vivo: `PK\x03\x04` al inicio del archivo descargado,
 * con un único .csv adentro) en vez del CSV plano que traían los dos
 * ejemplos que se bajaron a mano — no se sabe todavía si depende del tamaño
 * del export, del navegador, o de otra cosa. Se maneja ambos casos: si
 * arranca con la firma de zip, se descomprime (con la misma librería que
 * usa la propia app de ARCA para generarlo, `jszip`) y se toma el único
 * archivo de adentro; si no, se usa tal cual.
 */
async function contenidoCsv(buffer: Buffer): Promise<Buffer> {
  const esZip = buffer.length >= 4 && buffer[0] === 0x50 && buffer[1] === 0x4b;
  if (!esZip) return buffer;

  const zip = await JSZip.loadAsync(buffer);
  const nombres = Object.keys(zip.files).filter((n) => !zip.files[n].dir);
  if (nombres.length !== 1) {
    throw new Error(
      `El zip descargado tiene ${nombres.length} archivos adentro, se esperaba exactamente 1 (${nombres.join(", ")}).`,
    );
  }
  return zip.files[nombres[0]].async("nodebuffer");
}

export function mapearFila(fila: Record<string, string>): ComprobanteScrapeado {
  const alicuotas: AlicuotaDetalle[] = [];
  for (const pct of ALICUOTAS_PCT) {
    const sufijo = pct === 0 ? "0%" : `${String(pct).replace(".", ",")}%`;
    const neto = aNumeroArg(campo(fila, `Neto Gravado IVA ${sufijo}`));
    const iva = pct === 0 ? 0 : aNumeroArg(campo(fila, `Importe IVA ${sufijo}`));
    if (neto !== 0 || iva !== 0) alicuotas.push({ porcentaje: pct, neto, iva });
  }

  const creditoFiscalComputable = campo(fila, "Crédito Fiscal Computable");
  const numeroHasta = campo(fila, "Número de Comprobante Hasta");
  const fechaVencimientoPago = campo(fila, "Fecha de Vencimiento del Pago");
  const percepcionNoCategorizados = campo(fila, "Percepción a No Categorizados");

  return {
    fecha: normalizarFecha(campo(fila, "Fecha de Emisión")),
    tipoComprobante: campo(fila, "Tipo de Comprobante") ?? "",
    puntoVenta: campo(fila, "Punto de Venta") ?? "",
    numero: campo(fila, "Número de Comprobante") ?? "",
    numeroHasta: numeroHasta,
    fechaVencimientoPago: fechaVencimientoPago,
    cuitContraparte: campo(fila, "Nro. Doc. Vendedor") ?? campo(fila, "Nro. Doc. Comprador") ?? "",
    tipoDocContraparte: campo(fila, "Tipo Doc. Vendedor") ?? campo(fila, "Tipo Doc. Comprador") ?? "",
    razonSocialContraparte:
      campo(fila, "Denominación Vendedor") ?? campo(fila, "Denominación Comprador") ?? "",
    monedaCodigo: campo(fila, "Moneda Original") ?? "",
    tipoCambio: aNumeroArg(campo(fila, "Tipo de Cambio")),
    importeTotal: aNumeroArg(campo(fila, "Importe Total")),
    importeNoGravado: aNumeroArg(campo(fila, "Importe No Gravado")),
    importeExento: aNumeroArg(campo(fila, "Importe Exento")),
    creditoFiscalComputable:
      creditoFiscalComputable === undefined ? undefined : aNumeroArg(creditoFiscalComputable),
    percepcionesOtrosImpNac: aNumeroArg(
      campo(fila, "Importe de Per. o Pagos a Cta. de Otros Imp. Nac."),
    ),
    percepcionesIIBB: aNumeroArg(campo(fila, "Importe de Percepciones de Ingresos Brutos")),
    impuestosMunicipales: aNumeroArg(campo(fila, "Importe de Impuestos Municipales")),
    percepcionesIVA: aNumeroArg(campo(fila, "Importe de Percepciones o Pagos a Cuenta de IVA")),
    percepcionNoCategorizados:
      percepcionNoCategorizados === undefined ? undefined : aNumeroArg(percepcionNoCategorizados),
    impuestosInternos: aNumeroArg(campo(fila, "Importe de Impuestos Internos")),
    otrosTributos: aNumeroArg(campo(fila, "Importe Otros Tributos")),
    alicuotas,
    totalNetoGravado: aNumeroArg(campo(fila, "Total Neto Gravado")),
    totalIva: aNumeroArg(campo(fila, "Total IVA")),
    raw: fila,
  };
}

/**
 * Decodifica bytes de un CSV de ARCA (detecta BOM UTF-8 vs. ISO-8859-1 sin
 * asumir uno fijo — ver nota más abajo) y los mapea a comprobantes. Separado
 * de la descarga para poder reusarlo tanto con un archivo recién bajado
 * (`extraerLibroCsv`) como con uno ya guardado en `salida/` de una corrida
 * anterior (`leerComprobantesDesdeArchivo`, usada por `output/
 * plantillaVisualIva.ts` para no tener que volver a pasar por ARCA).
 */
export function decodificarYMapear(bufferCsv: Buffer): ComprobanteScrapeado[] {
  // Detectar el encoding por los bytes, no asumir: los CSV de ejemplo eran
  // ISO-8859-1 sin BOM, pero un export puede venir en UTF-8 con BOM (común
  // para que Excel lo detecte solo) — decodificar eso como latin1 corrompe
  // el primer header con 3 caracteres basura en vez de un solo `﻿`
  // limpio, así que hace falta mirar los bytes crudos antes de decodificar.
  const tieneBomUtf8 =
    bufferCsv.length >= 3 && bufferCsv[0] === 0xef && bufferCsv[1] === 0xbb && bufferCsv[2] === 0xbf;
  const texto = tieneBomUtf8 ? bufferCsv.subarray(3).toString("utf8") : bufferCsv.toString("latin1");
  return parseCsv(texto).map(mapearFila);
}

/** Lee un CSV ya guardado en disco (ej. `salida/{...}.csv` de una corrida anterior). */
export async function leerComprobantesDesdeArchivo(rutaArchivo: string): Promise<ComprobanteScrapeado[]> {
  const buffer = await fs.readFile(rutaArchivo);
  return decodificarYMapear(await contenidoCsv(buffer));
}

export interface ResultadoExtraccionCsv {
  comprobantes: ComprobanteScrapeado[];
  /** El CSV ya descomprimido (si hizo falta), bytes tal como los entregó ARCA — sin decodificar. */
  csv: Buffer;
}

/**
 * Descarga el CSV de la grilla actualmente abierta (Libro Ventas o Libro
 * Compras, ya con los comprobantes importados) y lo parsea. `page` debe
 * estar ya posicionada en esa solapa (ver flows/portalIva.ts).
 */
export async function extraerLibroCsv(page: Page, libro: Libro): Promise<ResultadoExtraccionCsv> {
  const boton = page.locator(".dt-buttons > *").first();
  const textoBoton = (await boton.textContent())?.trim();
  if (textoBoton !== "CSV") {
    throw new Error(
      `Se esperaba que el primer botón de .dt-buttons sea "CSV", encontré "${textoBoton}" — ` +
        "ARCA puede haber reordenado los botones de exportación.",
    );
  }

  const [download] = await Promise.all([page.waitForEvent("download"), boton.click()]);

  const rutaTemporal = await download.path();
  if (!rutaTemporal) {
    throw new Error("La descarga del CSV no generó un archivo temporal accesible");
  }

  const buffer = await fs.readFile(rutaTemporal);

  // Guarda una copia cruda para diagnóstico — si algo sale mal más abajo
  // (parseo, mapeo), poder ver el archivo real en vez de adivinar.
  const dirDebug = path.resolve("debug");
  await fs.mkdir(dirDebug, { recursive: true });
  const timestamp = Date.now();
  await fs.copyFile(rutaTemporal, path.join(dirDebug, `descarga_${libro}_${timestamp}.raw`));

  const bufferCsv = await contenidoCsv(buffer);
  if (bufferCsv !== buffer) {
    await fs.writeFile(path.join(dirDebug, `csv_${libro}_${timestamp}.csv`), bufferCsv);
  }

  return { comprobantes: decodificarYMapear(bufferCsv), csv: bufferCsv };
}
