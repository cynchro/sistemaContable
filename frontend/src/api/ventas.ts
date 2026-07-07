import api from './client'

export interface Venta {
  id: number
  fecha: string | null
  cliente_nombre: string | null
  cuit: string | null
  letra: string | null
  punto_venta: number | null
  numero: number | null
  neto_gravado: string
  iva: string
  total: string
  cae: string | null
  afip_resultado: string | null
}

export interface VentasFiltros {
  fecha_desde?: string
  fecha_hasta?: string
  cliente_id?: number
  letra?: string
  nombre?: string
  numero?: string
  orden?: string
}

/** Línea de discriminación de IVA: neto gravado a una alícuota. `iva_importe` es un
 * override opcional (regla del asterisco); si va vacío, lo calcula el motor. */
export interface DiscriminacionInput {
  neto_gravado: string
  iva_alicuota: string
  iva_importe?: string | null
  /** Reintegro de IVA (Factura T): iguala al IVA para que el débito neto sea cero. */
  reintegro_t?: string | null
}

/**
 * Percepción (ventas) / retención (compras) a nivel comprobante. Solo `tipo_retencion_id`
 * es obligatorio; alícuota/base/importe/provincia son overrides opcionales — si van vacíos,
 * el backend los resuelve desde el tipo (base_calculo) y calcula el importe (integra el total).
 */
export interface PercepcionInput {
  tipo_retencion_id: number
  alicuota?: string | null
  base?: string | null
  importe?: string | null
  provincia_id?: number | null
}

/** Comprobante asociado (para NC/ND: referencia a la factura original). Ventas. */
export interface ComprobanteAsociadoInput {
  tipo_comprobante_id?: number | null
  letra?: string | null
  punto_venta: string
  numero: string
  cuit?: string | null
  fecha?: string | null
}

export interface ComprobanteAsociadoDetalle {
  id: number
  tipo_comprobante_id: number | null
  letra: string | null
  punto_venta: string | null
  numero: string | null
  cuit: string | null
  fecha: string | null
  tipo_codigo: string | null
}

/** Percepción tal como la devuelve el detalle (con datos del tipo para mostrar). */
export interface PercepcionDetalle {
  id: number
  tipo_retencion_id: number | null
  provincia_id: number | null
  base: string
  alicuota: string | null
  importe: string
  tipo_nombre: string | null
  tipo_rg3685: number | null
  tipo_cod_afip: string | null
}

/** Cabecera de la venta enviada al backend (las líneas/total los calcula el motor). */
export interface VentaInput {
  fecha: string
  tipo_comprobante_id?: number | null
  tipo_operacion_venta_id?: number | null
  tipo_documento_id?: number | null
  condicion_iva_id?: number | null
  provincia_id?: number | null
  rubro_id?: number | null
  cliente_id?: number | null
  cliente_nombre?: string | null
  cuit?: string | null
  letra?: string | null
  punto_venta?: string | null
  numero?: string | null
  numero_fin?: string | null
  cai?: string | null
  fecha_cai?: string | null
  neto_no_grav?: string | null
  exento?: string | null
  imp_interno?: string | null
  tipo_moneda_id?: number | null
  tipo_cambio?: string | null
  campo_auxiliar?: string | null
  actividad_id?: number | null
  es_bien_uso?: string | null
  discriminaciones: DiscriminacionInput[]
  percepciones?: PercepcionInput[]
  comprobantes_asociados?: ComprobanteAsociadoInput[]
}

/** Venta completa (cabecera + líneas) tal como la devuelve el backend para editar. */
export interface VentaDetalle {
  id: number
  fecha: string | null
  tipo_comprobante_id: number | null
  tipo_operacion_venta_id: number | null
  tipo_documento_id: number | null
  condicion_iva_id: number | null
  provincia_id: number | null
  rubro_id: number | null
  cliente_id: number | null
  cliente_nombre: string | null
  cuit: string | null
  letra: string | null
  punto_venta: number | string | null
  numero: number | string | null
  numero_fin: number | string | null
  cai: string | null
  fecha_cai: string | null
  neto_no_grav: string | null
  exento: string | null
  imp_interno: string | null
  tipo_moneda_id: number | null
  tipo_cambio: string | null
  campo_auxiliar: string | null
  actividad_id: number | null
  es_bien_uso: string | null
  total: string
  discriminaciones: Array<{
    id: number
    neto_gravado: string
    iva_alicuota: string
    iva_importe: string
  }>
  percepciones?: PercepcionDetalle[]
  comprobantes_asociados?: ComprobanteAsociadoDetalle[]
}

/** Página estándar del backend (PaginatorHelper). */
export interface Pagina<T> {
  total: number
  pagina: number
  cantidad_por_pagina: number
  results: T[]
}

export async function listVentas(
  empresaId: number,
  periodoId: number,
  filtros: VentasFiltros,
  page: number,
  perPage: number,
): Promise<Pagina<Venta>> {
  const params: Record<string, string | number> = { page, per_page: perPage }
  if (filtros.fecha_desde) params.fecha_desde = filtros.fecha_desde
  if (filtros.fecha_hasta) params.fecha_hasta = filtros.fecha_hasta
  if (filtros.cliente_id) params.cliente_id = filtros.cliente_id
  if (filtros.letra) params.letra = filtros.letra
  if (filtros.nombre) params.nombre = filtros.nombre
  if (filtros.numero) params.numero = filtros.numero
  if (filtros.orden) params.orden = filtros.orden

  const { data } = await api.get(`/empresas/${empresaId}/periodos/${periodoId}/ventas`, { params })
  return data.data as Pagina<Venta>
}

export async function getVenta(empresaId: number, periodoId: number, id: number): Promise<VentaDetalle> {
  const { data } = await api.get(`/empresas/${empresaId}/periodos/${periodoId}/ventas/${id}`)
  return data.data as VentaDetalle
}

export async function createVenta(
  empresaId: number,
  periodoId: number,
  input: VentaInput,
): Promise<VentaDetalle> {
  const { data } = await api.post(`/empresas/${empresaId}/periodos/${periodoId}/ventas`, input)
  return data.data as VentaDetalle
}

export async function updateVenta(
  empresaId: number,
  periodoId: number,
  id: number,
  input: VentaInput,
): Promise<VentaDetalle> {
  const { data } = await api.put(`/empresas/${empresaId}/periodos/${periodoId}/ventas/${id}`, input)
  return data.data as VentaDetalle
}

export async function deleteVenta(empresaId: number, periodoId: number, id: number): Promise<void> {
  await api.delete(`/empresas/${empresaId}/periodos/${periodoId}/ventas/${id}`)
}

/** Mueve el comprobante a otro período de la misma empresa. */
export async function moverVenta(
  empresaId: number,
  periodoId: number,
  id: number,
  periodoDestinoId: number,
): Promise<void> {
  await api.post(`/empresas/${empresaId}/periodos/${periodoId}/ventas/${id}/mover`, {
    periodo_destino_id: periodoDestinoId,
  })
}
