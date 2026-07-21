import api from './client'

/**
 * Auditoría de ventas vs. ARCA (WSFEv1): compara, por punto de venta + tipo + letra ya
 * usados localmente, el último número que ARCA reconoce como autorizado contra el
 * último número cargado en el sistema.
 */
export interface ResumenAuditoriaItem {
  punto_venta: string
  tipo_comprobante_id: number
  /** Código legacy del tipo de comprobante (p. ej. 'FA', 'NC'). */
  tipo_comprobante: string
  letra: string
  ultimo_arca: number
  ultimo_local: number
  faltantes: number
}

export async function getResumenAuditoria(empresaId: number): Promise<ResumenAuditoriaItem[]> {
  const { data } = await api.get(`/empresas/${empresaId}/auditoria-afip`)
  return data.data as ResumenAuditoriaItem[]
}

export interface AlicuotaAfip {
  alicuota: number
  base: number
  importe: number
}

/** Detalle de un comprobante puntual según ARCA (FECompConsultar), ya cruzado con lo local. */
export interface ComprobanteAfipDetalle {
  encontrado: boolean
  resultado: string | null
  fecha: string | null
  total: number | null
  neto: number | null
  cae: string | null
  cae_vto: string | null
  alicuotas: AlicuotaAfip[]
  ya_cargado: boolean
  venta_id_local: number | null
}

export async function getComprobanteAfip(
  empresaId: number,
  params: { tipoComprobanteId: number; puntoVenta: string; letra: string; numero: string },
): Promise<ComprobanteAfipDetalle> {
  const { data } = await api.get(`/empresas/${empresaId}/auditoria-afip/comprobante`, {
    params: {
      tipo_comprobante_id: params.tipoComprobanteId,
      punto_venta: params.puntoVenta,
      letra: params.letra,
      numero: params.numero,
    },
  })
  return data.data as ComprobanteAfipDetalle
}
