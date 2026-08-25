import type { ComprobanteScrapeado, Libro } from "../types.js";

/**
 * Traduce un comprobante scrapeado de ARCA (formato "Mis Comprobantes" /
 * grilla del Portal IVA) al payload que espera el importador masivo de
 * `ecosistema` (`VentaController::import`/`CompraController::import`, mismos
 * campos que un alta individual vía `CreateVentaRequest`/`CreateCompraRequest`).
 *
 * Dos cosas que ARCA NO informa en este CSV y hay que resolver con una
 * heurística documentada (no hay forma de saberlo con certeza sin consultar
 * el padrón del contribuyente):
 *  - `condicion_iva_id`: se infiere de la LETRA del comprobante (A→Responsable
 *    Inscripto, B→Consumidor Final, C→Monotributo, E→Cliente del exterior) —
 *    es la relación típica por la que se emite cada letra, no una garantía.
 *  - `provincia_id`: no se informa, queda `null` (nullable en el modelo).
 *
 * `condicion_iva_id`/catálogo replicado de `CatalogosIvaSeeder.php` — si ese
 * seeder cambia los ids, hay que actualizar esto a mano (no hay un endpoint
 * de catálogos consumible acá todavía).
 */

const CONDICION_IVA_POR_LETRA: Record<string, number> = {
  A: 1, // Responsable Inscripto
  B: 5, // Consumidor Final
  C: 3, // Monotributo
  E: 9, // Cliente del exterior
  M: 1, // Responsable Inscripto (facturación con IVA discriminado por el emisor)
};

const TIPO_DOC_AFIP_A_ID: Record<string, number> = {
  "80": 1, // CUIT
  "86": 2, // CUIL
  "96": 10, // D.N.I.
  "87": 13, // CDI
  "89": 3, // L. Enrolamiento
  "90": 4, // L. Cívica
  "94": 8, // Pasaporte
  "99": 12, // Sin Identificar (Consumidor Final)
};

interface TipoComprobanteInterno {
  tipoComprobanteId: number; // id en la tabla tipos_comprobante de ecosistema
  letra: string;
}

/**
 * Códigos AFIP (CbteTipo) de la facturación estándar → (id interno, letra).
 * Cubre Factura/Nota de Débito/Nota de Crédito en A/B/C/E/M — la inmensa
 * mayoría del tráfico real de facturación electrónica. Códigos fuera de esta
 * tabla (FCE MiPyME, comprobantes T, liquidaciones, etc.) tiran error
 * explícito en vez de mapearse mal en silencio.
 */
const TIPO_COMPROBANTE_AFIP: Record<string, TipoComprobanteInterno> = {
  "1": { tipoComprobanteId: 9, letra: "A" }, // Factura A
  "2": { tipoComprobanteId: 4, letra: "A" }, // Nota de Débito A
  "3": { tipoComprobanteId: 3, letra: "A" }, // Nota de Crédito A
  "6": { tipoComprobanteId: 9, letra: "B" }, // Factura B
  "7": { tipoComprobanteId: 4, letra: "B" }, // Nota de Débito B
  "8": { tipoComprobanteId: 3, letra: "B" }, // Nota de Crédito B
  "11": { tipoComprobanteId: 9, letra: "C" }, // Factura C
  "12": { tipoComprobanteId: 4, letra: "C" }, // Nota de Débito C
  "13": { tipoComprobanteId: 3, letra: "C" }, // Nota de Crédito C
  "19": { tipoComprobanteId: 9, letra: "E" }, // Factura E (exportación)
  "20": { tipoComprobanteId: 4, letra: "E" }, // Nota de Débito E
  "21": { tipoComprobanteId: 3, letra: "E" }, // Nota de Crédito E
  "51": { tipoComprobanteId: 9, letra: "M" }, // Factura M
  "52": { tipoComprobanteId: 4, letra: "M" }, // Nota de Débito M
  "53": { tipoComprobanteId: 3, letra: "M" }, // Nota de Crédito M
  // Encontrado en vivo (25/08/2026, botón "Liquidar IVA", compra real de BANCO DE LA NACION
  // ARGENTINA, $75.158,67, IVA discriminado normal) — sin esto quedaba descartado en silencio
  // al "traer". id 81 ('LM' en tipos_comprobante, cod_citi 063) es un tipo NUEVO del catálogo,
  // agregado a propósito con un código interno que no choca con `CbteTipoResolver::TABLA_FIJA`
  // (ver seeders/CatalogosIvaSeeder.php).
  "63": { tipoComprobanteId: 81, letra: "A" }, // Liquidación A (por mandato)
};

export class ComprobanteNoMapeableError extends Error {
  constructor(public readonly comprobante: ComprobanteScrapeado, motivo: string) {
    super(
      `No se pudo mapear el comprobante ${comprobante.puntoVenta}-${comprobante.numero} ` +
        `(tipo AFIP ${comprobante.tipoComprobante}): ${motivo}`,
    );
  }
}

function padIzq(valor: string, largo: number): string {
  return valor.padStart(largo, "0");
}

/**
 * @param libro determina si el payload es de compra (`proveedor_*`) o venta
 *   (`cliente_*` + `tipo_documento_id`).
 * @throws {ComprobanteNoMapeableError} si el tipo de comprobante AFIP no está
 *   en la tabla soportada — mejor fallar explícito que adivinar.
 */
export function mapearComprobante(
  c: ComprobanteScrapeado,
  libro: Libro,
): Record<string, unknown> {
  const tipo = TIPO_COMPROBANTE_AFIP[c.tipoComprobante];
  if (!tipo) {
    throw new ComprobanteNoMapeableError(
      c,
      `código de comprobante AFIP "${c.tipoComprobante}" no está en la tabla soportada ` +
        "(solo Factura/ND/NC A/B/C/E/M) — agregalo a TIPO_COMPROBANTE_AFIP si corresponde.",
    );
  }

  const condicionIvaId = CONDICION_IVA_POR_LETRA[tipo.letra] ?? 1;
  const discriminaciones = c.alicuotas.map((a) => ({
    neto_gravado: a.neto.toFixed(2),
    iva_alicuota: String(a.porcentaje),
    iva_importe: a.iva.toFixed(2),
  }));

  const base: Record<string, unknown> = {
    fecha: c.fecha,
    tipo_comprobante_id: tipo.tipoComprobanteId,
    condicion_iva_id: condicionIvaId,
    provincia_id: null,
    letra: tipo.letra,
    punto_venta: padIzq(c.puntoVenta, 5),
    numero: padIzq(c.numero, 8),
    concepto: 1,
    // Importes fuera de las discriminaciones por alícuota — sin esto, un
    // comprobante 100% No Gravado/Exento/Imp. Interno (típico en pólizas de
    // seguro, que muchas veces no discriminan IVA por comprobante) queda con
    // total $0 y sin ninguna línea de alícuota: ARCA lo rechaza al subir el
    // Libro IVA Digital con "es obligatorio informar alícuotas IVA"
    // (encontrado en vivo, 24/08/2026, comprobantes reales de COSENA SEGUROS
    // S.A. y RED COLON S.A. traídos desde ARCA).
    neto_no_grav: c.importeNoGravado.toFixed(2),
    exento: c.importeExento.toFixed(2),
    imp_interno: c.impuestosInternos.toFixed(2),
    discriminaciones,
  };

  if (libro === "compras") {
    return {
      ...base,
      proveedor_nombre: c.razonSocialContraparte,
      cuit: c.cuitContraparte,
    };
  }

  return {
    ...base,
    cliente_nombre: c.razonSocialContraparte,
    cuit: c.cuitContraparte,
    tipo_documento_id: TIPO_DOC_AFIP_A_ID[c.tipoDocContraparte] ?? 1,
    numero_fin: padIzq(c.numeroHasta ?? c.numero, 8),
  };
}

export interface MapeoResultado {
  comprobantes: Array<Record<string, unknown>>;
  descartados: ComprobanteNoMapeableError[];
}

/** Mapea una lista completa, separando los que no se pudieron mapear en vez de abortar todo. */
export function mapearComprobantes(lista: ComprobanteScrapeado[], libro: Libro): MapeoResultado {
  const comprobantes: Array<Record<string, unknown>> = [];
  const descartados: ComprobanteNoMapeableError[] = [];
  for (const c of lista) {
    try {
      comprobantes.push(mapearComprobante(c, libro));
    } catch (err) {
      if (err instanceof ComprobanteNoMapeableError) descartados.push(err);
      else throw err;
    }
  }
  return { comprobantes, descartados };
}
