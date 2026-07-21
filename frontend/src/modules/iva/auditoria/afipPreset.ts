import type { ComprobanteAfipDetalle, ResumenAuditoriaItem } from '../../../api/auditoriaAfip'

/**
 * Preset de alta de venta armado con lo que ARCA devolvió para un comprobante puntual
 * (auditoría vs. ARCA). Mismo mecanismo que `CompraPreset` en Compras: solo se aplica en
 * alta (ventaId == null), vía la prop `preset` de `VentaFormModal`.
 */
export interface VentaPreset {
  fecha?: string
  /** Código del catálogo tipos-comprobante (se resuelve a id dentro del modal). */
  tipoCodigo?: string
  letra?: string
  punto_venta?: string
  numero?: string
  cai?: string
  fecha_cai?: string
  discriminaciones: { neto_gravado: string; iva_alicuota: string }[]
}

export function buildVentaPreset(
  fila: Pick<ResumenAuditoriaItem, 'tipo_comprobante' | 'punto_venta' | 'letra'>,
  numero: string,
  detalle: ComprobanteAfipDetalle,
): VentaPreset {
  return {
    fecha: detalle.fecha ?? undefined,
    tipoCodigo: fila.tipo_comprobante,
    letra: fila.letra,
    punto_venta: fila.punto_venta,
    numero,
    cai: detalle.cae ?? undefined,
    fecha_cai: detalle.cae_vto ?? undefined,
    discriminaciones:
      detalle.alicuotas.length > 0
        ? detalle.alicuotas.map((a) => ({
            neto_gravado: String(a.base),
            iva_alicuota: String(a.alicuota),
          }))
        : [],
  }
}
