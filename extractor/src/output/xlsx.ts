import fs from "node:fs";
import path from "node:path";
import ExcelJS from "exceljs";
import type { ComprobanteScrapeado, Libro, PeriodoFiscal } from "../types.js";

const SALIDA_DIR = path.resolve("salida");

const ALICUOTAS_PCT = [0, 2.5, 5, 10.5, 21, 27] as const;

function nombreArchivo(cuit: string, periodo: PeriodoFiscal, libro: Libro): string {
  const periodoSanitizado = periodo.replace(/\//g, "-");
  const timestamp = new Date().toISOString().replace(/[:.]/g, "-");
  return `${cuit}_${periodoSanitizado}_${libro}_${timestamp}.xlsx`;
}

function netoIvaPorAlicuota(comprobante: ComprobanteScrapeado, pct: number): [number, number] {
  const detalle = comprobante.alicuotas.find((a) => a.porcentaje === pct);
  return detalle ? [detalle.neto, detalle.iva] : [0, 0];
}

/**
 * Guarda los comprobantes recolectados en un xlsx local (carpeta `salida/`
 * en la raíz del extractor). No toca ecosistema ni ARCA — es puramente la
 * salida local, mientras se decide el paso siguiente de integración.
 *
 * Vuelca el detalle completo que trae el CSV de origen (desglose por
 * alícuota + percepciones + impuestos), no solo un resumen — ver
 * `extract/libroCsv.ts` y `plan-factibilidad-portal-iva.md`.
 *
 * Devuelve la ruta absoluta del archivo escrito.
 */
export async function guardarComprobantesXlsx(
  comprobantes: ComprobanteScrapeado[],
  opts: { cuit: string; periodo: PeriodoFiscal; libro: Libro },
): Promise<string> {
  fs.mkdirSync(SALIDA_DIR, { recursive: true });

  const workbook = new ExcelJS.Workbook();
  const sheet = workbook.addWorksheet(opts.libro === "ventas" ? "Libro Ventas" : "Libro Compras");

  const columnasAlicuota = ALICUOTAS_PCT.flatMap((pct) => [
    { header: `Neto ${pct}%`, key: `neto${pct}`, width: 12 },
    { header: `IVA ${pct}%`, key: `iva${pct}`, width: 12 },
  ]);

  sheet.columns = [
    { header: "Fecha", key: "fecha", width: 12 },
    { header: "Tipo comprobante", key: "tipoComprobante", width: 10 },
    { header: "Punto de venta", key: "puntoVenta", width: 10 },
    { header: "Número", key: "numero", width: 14 },
    { header: "Número hasta", key: "numeroHasta", width: 14 },
    { header: "Vto. pago", key: "fechaVencimientoPago", width: 12 },
    { header: "CUIT contraparte", key: "cuitContraparte", width: 16 },
    { header: "Razón social", key: "razonSocialContraparte", width: 30 },
    { header: "Moneda", key: "monedaCodigo", width: 8 },
    { header: "Tipo cambio", key: "tipoCambio", width: 10 },
    { header: "Importe total", key: "importeTotal", width: 14 },
    { header: "No gravado", key: "importeNoGravado", width: 12 },
    { header: "Exento", key: "importeExento", width: 12 },
    { header: "Crédito fiscal computable", key: "creditoFiscalComputable", width: 16 },
    { header: "Perc. otros imp. nac.", key: "percepcionesOtrosImpNac", width: 14 },
    { header: "Perc. IIBB", key: "percepcionesIIBB", width: 12 },
    { header: "Imp. municipales", key: "impuestosMunicipales", width: 14 },
    { header: "Perc. IVA", key: "percepcionesIVA", width: 12 },
    { header: "Perc. no categorizados", key: "percepcionNoCategorizados", width: 14 },
    { header: "Imp. internos", key: "impuestosInternos", width: 12 },
    { header: "Otros tributos", key: "otrosTributos", width: 12 },
    ...columnasAlicuota,
    { header: "Total neto gravado", key: "totalNetoGravado", width: 14 },
    { header: "Total IVA", key: "totalIva", width: 14 },
  ];
  sheet.getRow(1).font = { bold: true };

  for (const c of comprobantes) {
    const fila: Record<string, unknown> = {
      fecha: c.fecha,
      tipoComprobante: c.tipoComprobante,
      puntoVenta: c.puntoVenta,
      numero: c.numero,
      numeroHasta: c.numeroHasta,
      fechaVencimientoPago: c.fechaVencimientoPago,
      cuitContraparte: c.cuitContraparte,
      razonSocialContraparte: c.razonSocialContraparte,
      monedaCodigo: c.monedaCodigo,
      tipoCambio: c.tipoCambio,
      importeTotal: c.importeTotal,
      importeNoGravado: c.importeNoGravado,
      importeExento: c.importeExento,
      creditoFiscalComputable: c.creditoFiscalComputable,
      percepcionesOtrosImpNac: c.percepcionesOtrosImpNac,
      percepcionesIIBB: c.percepcionesIIBB,
      impuestosMunicipales: c.impuestosMunicipales,
      percepcionesIVA: c.percepcionesIVA,
      percepcionNoCategorizados: c.percepcionNoCategorizados,
      impuestosInternos: c.impuestosInternos,
      otrosTributos: c.otrosTributos,
      totalNetoGravado: c.totalNetoGravado,
      totalIva: c.totalIva,
    };
    for (const pct of ALICUOTAS_PCT) {
      const [neto, iva] = netoIvaPorAlicuota(c, pct);
      fila[`neto${pct}`] = neto;
      fila[`iva${pct}`] = iva;
    }
    sheet.addRow(fila);
  }

  const filePath = path.join(SALIDA_DIR, nombreArchivo(opts.cuit, opts.periodo, opts.libro));
  await workbook.xlsx.writeFile(filePath);
  return filePath;
}
