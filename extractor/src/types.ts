/** Desglose de un comprobante por alícuota de IVA (0/2,5/5/10,5/21/27%). */
export interface AlicuotaDetalle {
  porcentaje: number;
  neto: number;
  iva: number;
}

/**
 * Comprobante tal como lo trae el CSV de ARCA (Portal IVA → Libro Ventas/
 * Compras → botón CSV). Algunos campos son específicos de un solo libro
 * (ver comentarios) — quedan `undefined` en el otro.
 */
export interface ComprobanteScrapeado {
  fecha: string; // YYYY-MM-DD
  tipoComprobante: string; // código de tipo de comprobante (no el CbteTipo de WSFEv1)
  puntoVenta: string;
  numero: string;
  numeroHasta?: string; // solo ventas
  fechaVencimientoPago?: string; // solo ventas
  cuitContraparte: string; // vendedor (compras) o comprador (ventas)
  tipoDocContraparte: string; // código AFIP (80=CUIT, 96=DNI, etc.)
  razonSocialContraparte: string;
  monedaCodigo: string;
  tipoCambio: number;
  importeTotal: number;
  importeNoGravado: number;
  importeExento: number;
  creditoFiscalComputable?: number; // solo compras
  percepcionesOtrosImpNac: number;
  percepcionesIIBB: number;
  impuestosMunicipales: number;
  percepcionesIVA: number; // "Percepciones o Pagos a Cta. de IVA" — solo compras en este export
  percepcionNoCategorizados?: number; // solo ventas
  impuestosInternos: number;
  otrosTributos: number;
  alicuotas: AlicuotaDetalle[]; // solo las que tienen neto y/o IVA distinto de cero
  totalNetoGravado: number;
  totalIva: number;
  /** Fila cruda completa (claves = encabezados originales del CSV), por si hace falta algo no mapeado. */
  raw: Record<string, string>;
}

/** Período fiscal en formato MM/YYYY, tal como lo pide el selector del Portal IVA. */
export type PeriodoFiscal = string;

export type Libro = "ventas" | "compras";
