import ExcelJS from "exceljs";
import type { ComprobanteScrapeado } from "../types.js";
import {
  TIPO_COMPROBANTE,
  TIPO_DOCUMENTO,
  MONEDA,
  TIPO_OPERACION_COMPRA,
  TIPO_OPERACION_VENTA,
  ACTIVIDAD,
  CONCEPTO,
  type EntradaCatalogo,
} from "./catalogosVisualIva.js";

/**
 * Genera un xlsx con la misma forma que la plantilla legacy "Visual IVA"
 * (`Plantilla_Visual_IVA_202605.xls`, ver plan-factibilidad-portal-iva.md
 * §"¿son los mismos CSV?" y el análisis de columnas hecho a mano): 4 hojas
 * — Compras/Ventas (texto legible, "código descripción") y Hoja2/Hoja3
 * ("VISUAL IVA IMPORTACION DE COMPRAS/VENTAS", códigos puros).
 *
 * Diferencia deliberada con el original: Hoja2/Hoja3 NO llevan fórmulas
 * VLOOKUP en vivo — se calculan los valores finales acá mismo. El original
 * las necesita porque el contador tipea directo en Compras/Ventas; acá el
 * dato ya viene resuelto de ARCA, así que replicar fórmulas solo agregaría
 * fragilidad (dependen de que Excel recalcule al abrir) sin ganar nada real
 * para el consumidor final (el importador de ecosistema lee valores, no
 * fórmulas). El layout de columnas de Compras/Ventas/Hoja2/Hoja3 sí es 1:1
 * con el original.
 *
 * Defaults acordados con el usuario para los 3 campos que ARCA no manda
 * (Tipo de Operación de Compra/Venta, Actividad, Concepto): el mismo
 * criterio que ya usa `ecosistema` en el DJ IVA Simple v1 (toda la
 * operatoria a la actividad principal, sin bienes de uso, concepto
 * "Productos").
 */

const DEFAULT_TIPO_OPERACION_COMPRA = "Compras de bienes (excepto bienes de uso)";
const DEFAULT_TIPO_OPERACION_VENTA =
  "Ventas de cosas muebles, obras, locaciones y/o prestaciones de de servicios";
const DEFAULT_ACTIVIDAD = "Actividad Primaria en Visual Iva";
const DEFAULT_CONCEPTO = "Productos";

function porCodigo(catalogo: EntradaCatalogo[], codigo: string): EntradaCatalogo {
  // .trim() en ambos lados: la plantilla original tiene al menos un código
  // con un espacio de más ("DOL " en vez de "DOL", confirmado con datos
  // reales) — no vale la pena arriesgarse a que haya más así.
  const buscado = codigo.trim();
  const entrada = catalogo.find((e) => e.codigo.trim() === buscado);
  if (!entrada) {
    throw new Error(
      `Código "${codigo}" no encontrado en el catálogo de la plantilla Visual IVA ` +
        `(${catalogo.length} entradas). Revisar catalogosVisualIva.ts — puede ser un ` +
        "tipo/código que la plantilla legacy no contempla.",
    );
  }
  return entrada;
}

function porDescripcion(catalogo: EntradaCatalogo[], descripcion: string): EntradaCatalogo {
  const entrada = catalogo.find((e) => e.descripcion === descripcion);
  if (!entrada) {
    throw new Error(`Descripción "${descripcion}" no encontrada en el catálogo.`);
  }
  return entrada;
}

function codigoTipoComprobante(tipoComprobanteArca: string): string {
  return tipoComprobanteArca.padStart(3, "0");
}

function textoTipoComprobante(tipoComprobanteArca: string): string {
  return porCodigo(TIPO_COMPROBANTE, codigoTipoComprobante(tipoComprobanteArca)).descripcion;
}

function textoTipoDocumento(codigo: string): string {
  const entrada = TIPO_DOCUMENTO.find((e) => e.descripcion.trim().startsWith(codigo));
  if (!entrada) {
    throw new Error(`Tipo de documento "${codigo}" no encontrado en el catálogo.`);
  }
  return entrada.descripcion;
}

function textoMoneda(codigo: string): string {
  return porCodigo(MONEDA, codigo).descripcion;
}

function aFecha(iso: string | undefined): Date | null {
  if (!iso) return null;
  return new Date(`${iso}T00:00:00`);
}

function netoIvaPorAlicuota(c: ComprobanteScrapeado, pct: number): [number | null, number | null] {
  const d = c.alicuotas.find((a) => a.porcentaje === pct);
  return d ? [d.neto || null, d.iva || null] : [null, null];
}

function filaCompras(c: ComprobanteScrapeado): Record<string, unknown> {
  const [n21, i21] = netoIvaPorAlicuota(c, 21);
  const [n105, i105] = netoIvaPorAlicuota(c, 10.5);
  const [n27, i27] = netoIvaPorAlicuota(c, 27);
  const [n0] = netoIvaPorAlicuota(c, 0);
  if (c.otrosTributos) {
    console.warn(
      `⚠ Compras ${c.puntoVenta}-${c.numero}: Otros Tributos=${c.otrosTributos} no tiene ` +
        "columna en la plantilla — se está perdiendo ese importe en este archivo (el dato " +
        "real sigue en el CSV original guardado al lado en salida/).",
    );
  }
  return {
    fecha: aFecha(c.fecha),
    tipoComprobante: textoTipoComprobante(c.tipoComprobante),
    puntoVenta: Number(c.puntoVenta),
    numero: Number(c.numero),
    tipoDocumento: textoTipoDocumento(c.tipoDocContraparte),
    cuit: Number(c.cuitContraparte),
    denominacion: c.razonSocialContraparte,
    total: c.importeTotal,
    neto21: n21,
    iva21: i21,
    neto105: n105,
    iva105: i105,
    neto27: n27,
    iva27: i27,
    neto0: n0,
    iva0: 0,
    noGravado: c.importeNoGravado || null,
    exento: c.importeExento || null,
    percepIva: c.percepcionesIVA || null,
    percepOtrosImpNac: c.percepcionesOtrosImpNac || null,
    percepIIBB: c.percepcionesIIBB || null,
    percepMunicipales: c.impuestosMunicipales || null,
    impInternos: c.impuestosInternos || null,
    creditoFiscal: null, // ver nota: el original SIEMPRE lo deja vacío (el software de escritorio lo calcula al importar)
    moneda: textoMoneda(c.monedaCodigo),
    tipoCambio: c.tipoCambio,
    tipoOperacion: DEFAULT_TIPO_OPERACION_COMPRA,
    // Verificador: fiel al original (H2-SUM(I2:X2)), aunque incluye la
    // columna de Crédito Fiscal Computable en la suma — eso ya está en el
    // template original tal cual, no es algo que agreguemos acá.
    verificador: { formula: "H{r}-SUM(I{r}:X{r})" },
  };
}

function filaVentas(c: ComprobanteScrapeado): Record<string, unknown> {
  const [n21, i21] = netoIvaPorAlicuota(c, 21);
  const [n105, i105] = netoIvaPorAlicuota(c, 10.5);
  const [n27, i27] = netoIvaPorAlicuota(c, 27);
  const [n0] = netoIvaPorAlicuota(c, 0);
  if (c.otrosTributos) {
    console.warn(
      `⚠ Ventas ${c.puntoVenta}-${c.numero}: Otros Tributos=${c.otrosTributos} no tiene ` +
        "columna en la plantilla — se está perdiendo ese importe en este archivo (el dato " +
        "real sigue en el CSV original guardado al lado en salida/).",
    );
  }
  return {
    fecha: aFecha(c.fecha),
    tipoComprobante: textoTipoComprobante(c.tipoComprobante),
    puntoVenta: Number(c.puntoVenta),
    numeroDesde: Number(c.numero),
    numeroHasta: c.numeroHasta ? Number(c.numeroHasta) : Number(c.numero),
    tipoDocumento: textoTipoDocumento(c.tipoDocContraparte),
    cuit: Number(c.cuitContraparte),
    denominacion: c.razonSocialContraparte,
    total: c.importeTotal,
    neto21: n21,
    iva21: i21,
    neto105: n105,
    iva105: i105,
    neto27: n27,
    iva27: i27,
    neto0: n0,
    iva0: 0,
    noGravado: c.importeNoGravado || null,
    exento: c.importeExento || null,
    percepNoCategorizados: c.percepcionNoCategorizados || null,
    percepOtrosImpNac: c.percepcionesOtrosImpNac || null,
    percepIIBB: c.percepcionesIIBB || null,
    percepMunicipales: c.impuestosMunicipales || null,
    impInternos: c.impuestosInternos || null,
    moneda: textoMoneda(c.monedaCodigo),
    tipoCambio: c.tipoCambio,
    fechaVencimiento: aFecha(c.fechaVencimientoPago),
    tipoOperacion: DEFAULT_TIPO_OPERACION_VENTA,
    actividad: DEFAULT_ACTIVIDAD,
    concepto: DEFAULT_CONCEPTO,
    verificador: { formula: "I{r}-SUM(J{r}:X{r})" },
  };
}

const COLUMNAS_COMPRAS = [
  { header: "Fecha", key: "fecha", width: 12, style: { numFmt: "dd/mm/yyyy" } },
  { header: "Tipo de comprobante", key: "tipoComprobante", width: 24 },
  { header: "Punto de venta", key: "puntoVenta", width: 10 },
  { header: "Número de comprobante", key: "numero", width: 14 },
  { header: "Tipo Documento Vendedor", key: "tipoDocumento", width: 16 },
  { header: "Número identificación", key: "cuit", width: 16 },
  { header: "Apellido y nombre o denominación del vendedor", key: "denominacion", width: 30 },
  { header: "TOTAL", key: "total", width: 14 },
  { header: "Importe neto gravado 21", key: "neto21", width: 14 },
  { header: "Impuesto liquidado      21%", key: "iva21", width: 14 },
  { header: "Importe neto gravado 10,5", key: "neto105", width: 14 },
  { header: "Impuesto liquidado 10,5%", key: "iva105", width: 14 },
  { header: "Importe neto gravado 27", key: "neto27", width: 14 },
  { header: "Impuesto liquidado      27%", key: "iva27", width: 14 },
  { header: "Importe neto gravado 0", key: "neto0", width: 14 },
  { header: "Impuesto liquidado      0%", key: "iva0", width: 12 },
  {
    header: "Importe total de conceptos que no integran el precio neto gravado",
    key: "noGravado",
    width: 16,
  },
  { header: "Importe de operaciones exentas", key: "exento", width: 14 },
  {
    header: "Importe de percepciones o pagos a cuenta del Impuesto al Valor Agregado",
    key: "percepIva",
    width: 16,
  },
  {
    header: "Importe de percepciones o pagos a cuenta de otros impuestos nacionales",
    key: "percepOtrosImpNac",
    width: 16,
  },
  { header: "Importe de percepciones de Ingresos Brutos", key: "percepIIBB", width: 16 },
  { header: "Importe de percepciones de Impuestos Municipales", key: "percepMunicipales", width: 16 },
  { header: "Importe de Impuestos Internos", key: "impInternos", width: 14 },
  { header: "Crédito Fiscal Computable", key: "creditoFiscal", width: 14 },
  { header: "Código de moneda", key: "moneda", width: 14 },
  { header: "Tipo de cambio", key: "tipoCambio", width: 10 },
  { header: "Tipo de Operación de Compra", key: "tipoOperacion", width: 30 },
  { header: "VERIFICADOR DIFERENCIA CON EL TOTAL", key: "verificador", width: 14 },
];

const COLUMNAS_VENTAS = [
  { header: "Fecha", key: "fecha", width: 12, style: { numFmt: "dd/mm/yyyy" } },
  { header: "Tipo de comprobante", key: "tipoComprobante", width: 24 },
  { header: "Punto de venta", key: "puntoVenta", width: 10 },
  { header: "Número de comprobante Desde", key: "numeroDesde", width: 14 },
  { header: "Número de comprobante Hasta", key: "numeroHasta", width: 14 },
  { header: "Tipo Documento Comprador", key: "tipoDocumento", width: 16 },
  { header: "Número identificación", key: "cuit", width: 16 },
  { header: "Apellido y nombre o denominación del Comprador", key: "denominacion", width: 30 },
  { header: "TOTAL", key: "total", width: 14 },
  { header: "Importe neto gravado 21", key: "neto21", width: 14 },
  { header: "Impuesto liquidado      21%", key: "iva21", width: 14 },
  { header: "Importe neto gravado 10,5", key: "neto105", width: 14 },
  { header: "Impuesto liquidado 10,5%", key: "iva105", width: 14 },
  { header: "Importe neto gravado 27", key: "neto27", width: 14 },
  { header: "Impuesto liquidado      27%", key: "iva27", width: 14 },
  { header: "Importe neto gravado 0", key: "neto0", width: 14 },
  { header: "Impuesto liquidado      0%", key: "iva0", width: 12 },
  {
    header: "Importe total de conceptos que no integran el precio neto gravado",
    key: "noGravado",
    width: 16,
  },
  { header: "Importe de operaciones exentas", key: "exento", width: 14 },
  { header: "Percepción a no categorizados", key: "percepNoCategorizados", width: 16 },
  {
    header: "Importe de percepciones o pagos a cuenta de impuestos Nacionales",
    key: "percepOtrosImpNac",
    width: 16,
  },
  { header: "Importe de percepciones de Ingresos Brutos", key: "percepIIBB", width: 16 },
  { header: "Importe de percepciones de Impuestos Municipales", key: "percepMunicipales", width: 16 },
  { header: "Importe de Impuestos Internos", key: "impInternos", width: 14 },
  { header: "Código de moneda", key: "moneda", width: 14 },
  { header: "Tipo de cambio", key: "tipoCambio", width: 10 },
  { header: "Fecha Vencimiento", key: "fechaVencimiento", width: 14, style: { numFmt: "dd/mm/yyyy" } },
  { header: "Tipo de operación de venta", key: "tipoOperacion", width: 36 },
  { header: "Actividad", key: "actividad", width: 26 },
  { header: "Concepto", key: "concepto", width: 14 },
  { header: "VERIFICADOR DIFERENCIA CON EL TOTAL", key: "verificador", width: 14 },
];

function llenarHojaDatos(
  sheet: ExcelJS.Worksheet,
  columnas: typeof COLUMNAS_COMPRAS,
  comprobantes: ComprobanteScrapeado[],
  mapear: (c: ComprobanteScrapeado) => Record<string, unknown>,
): void {
  sheet.columns = columnas;
  sheet.getRow(1).font = { bold: true };
  comprobantes.forEach((c, i) => {
    const fila = mapear(c);
    const numeroFila = i + 2; // fila 1 = header
    if (
      typeof fila.verificador === "object" &&
      fila.verificador !== null &&
      "formula" in fila.verificador
    ) {
      fila.verificador = {
        formula: (fila.verificador as { formula: string }).formula.replace(/\{r\}/g, String(numeroFila)),
      };
    }
    sheet.addRow(fila);
  });
}

/** Construye Hoja2/Hoja3 ("VISUAL IVA IMPORTACION DE ...") con los códigos puros. */
function llenarHojaImportacion(
  sheet: ExcelJS.Worksheet,
  titulo: string,
  filas: Record<string, unknown>[],
  columnas: { key: string; width?: number }[],
): void {
  sheet.getCell("A1").value = titulo;
  sheet.getCell("A1").font = { bold: true };
  sheet.columns = columnas.map((c) => ({ key: c.key, width: c.width ?? 14 }));
  for (const fila of filas) sheet.addRow(fila);
}

export interface GenerarPlantillaOpts {
  compras: ComprobanteScrapeado[];
  ventas: ComprobanteScrapeado[];
}

export async function generarPlantillaVisualIva(opts: GenerarPlantillaOpts): Promise<ExcelJS.Workbook> {
  const wb = new ExcelJS.Workbook();

  const compras = wb.addWorksheet("Compras");
  llenarHojaDatos(compras, COLUMNAS_COMPRAS, opts.compras, filaCompras);

  const ventas = wb.addWorksheet("Ventas");
  llenarHojaDatos(ventas, COLUMNAS_VENTAS, opts.ventas, filaVentas);

  const hoja2 = wb.addWorksheet("Hoja2");
  llenarHojaImportacion(
    hoja2,
    "VISUAL IVA IMPORTACION DE COMPRAS",
    opts.compras.map((c) => ({
      fecha: aFecha(c.fecha),
      tipo: codigoTipoComprobante(c.tipoComprobante),
      puntoVenta: Number(c.puntoVenta),
      numero: Number(c.numero),
      tipoDoc: c.tipoDocContraparte,
      cuit: Number(c.cuitContraparte),
      denominacion: c.razonSocialContraparte,
      total: c.importeTotal,
      neto21: netoIvaPorAlicuota(c, 21)[0],
      iva21: netoIvaPorAlicuota(c, 21)[1],
      neto105: netoIvaPorAlicuota(c, 10.5)[0],
      iva105: netoIvaPorAlicuota(c, 10.5)[1],
      neto27: netoIvaPorAlicuota(c, 27)[0],
      iva27: netoIvaPorAlicuota(c, 27)[1],
      neto0: netoIvaPorAlicuota(c, 0)[0],
      iva0: 0,
      noGravado: c.importeNoGravado || null,
      exento: c.importeExento || null,
      percepIva: c.percepcionesIVA || null,
      percepOtrosImpNac: c.percepcionesOtrosImpNac || null,
      percepIIBB: c.percepcionesIIBB || null,
      percepMunicipales: c.impuestosMunicipales || null,
      impInternos: c.impuestosInternos || null,
      creditoFiscal: null, // ver nota: el original SIEMPRE lo deja vacío (el software de escritorio lo calcula al importar)
      moneda: c.monedaCodigo,
      tipoCambio: c.tipoCambio,
      tipoOperacion: porDescripcion(TIPO_OPERACION_COMPRA, DEFAULT_TIPO_OPERACION_COMPRA).codigo,
    })),
    [
      { key: "fecha" }, { key: "tipo" }, { key: "puntoVenta" }, { key: "numero" },
      { key: "tipoDoc" }, { key: "cuit" }, { key: "denominacion", width: 30 }, { key: "total" },
      { key: "neto21" }, { key: "iva21" }, { key: "neto105" }, { key: "iva105" },
      { key: "neto27" }, { key: "iva27" }, { key: "neto0" }, { key: "iva0" },
      { key: "noGravado" }, { key: "exento" }, { key: "percepIva" }, { key: "percepOtrosImpNac" },
      { key: "percepIIBB" }, { key: "percepMunicipales" }, { key: "impInternos" },
      { key: "creditoFiscal" }, { key: "moneda" }, { key: "tipoCambio" }, { key: "tipoOperacion" },
    ],
  );

  const hoja3 = wb.addWorksheet("Hoja3");
  llenarHojaImportacion(
    hoja3,
    "VISUAL IVA IMPORTACION DE VENTAS",
    opts.ventas.map((c) => ({
      fecha: aFecha(c.fecha),
      tipo: codigoTipoComprobante(c.tipoComprobante),
      puntoVenta: Number(c.puntoVenta),
      numeroDesde: Number(c.numero),
      numeroHasta: c.numeroHasta ? Number(c.numeroHasta) : Number(c.numero),
      tipoDoc: c.tipoDocContraparte,
      cuit: Number(c.cuitContraparte),
      denominacion: c.razonSocialContraparte,
      total: c.importeTotal,
      neto21: netoIvaPorAlicuota(c, 21)[0],
      iva21: netoIvaPorAlicuota(c, 21)[1],
      neto105: netoIvaPorAlicuota(c, 10.5)[0],
      iva105: netoIvaPorAlicuota(c, 10.5)[1],
      neto27: netoIvaPorAlicuota(c, 27)[0],
      iva27: netoIvaPorAlicuota(c, 27)[1],
      neto0: netoIvaPorAlicuota(c, 0)[0],
      iva0: 0,
      noGravado: c.importeNoGravado || null,
      exento: c.importeExento || null,
      percepNoCategorizados: c.percepcionNoCategorizados || null,
      percepOtrosImpNac: c.percepcionesOtrosImpNac || null,
      percepIIBB: c.percepcionesIIBB || null,
      percepMunicipales: c.impuestosMunicipales || null,
      impInternos: c.impuestosInternos || null,
      moneda: c.monedaCodigo,
      tipoCambio: c.tipoCambio,
      fechaVencimiento: aFecha(c.fechaVencimientoPago),
      tipoOperacion: porDescripcion(TIPO_OPERACION_VENTA, DEFAULT_TIPO_OPERACION_VENTA).codigo,
      actividad: porDescripcion(ACTIVIDAD, DEFAULT_ACTIVIDAD).codigo,
      concepto: porDescripcion(CONCEPTO, DEFAULT_CONCEPTO).codigo,
    })),
    [
      { key: "fecha" }, { key: "tipo" }, { key: "puntoVenta" }, { key: "numeroDesde" },
      { key: "numeroHasta" }, { key: "tipoDoc" }, { key: "cuit" }, { key: "denominacion", width: 30 },
      { key: "total" }, { key: "neto21" }, { key: "iva21" }, { key: "neto105" }, { key: "iva105" },
      { key: "neto27" }, { key: "iva27" }, { key: "neto0" }, { key: "iva0" }, { key: "noGravado" },
      { key: "exento" }, { key: "percepNoCategorizados" }, { key: "percepOtrosImpNac" },
      { key: "percepIIBB" }, { key: "percepMunicipales" }, { key: "impInternos" }, { key: "moneda" },
      { key: "tipoCambio" }, { key: "fechaVencimiento" }, { key: "tipoOperacion" },
      { key: "actividad" }, { key: "concepto" },
    ],
  );

  return wb;
}
