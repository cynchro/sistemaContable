import api from './client'

export interface Venta {
  id: number
  fecha: string | null
  cliente_nombre: string | null
  cuit: string | null
  letra: string | null
  punto_venta: number | null
  numero: number | null
  total: string
  cae: string | null
  afip_resultado: string | null
}

export interface VentasFiltros {
  fecha_desde?: string
  fecha_hasta?: string
  cliente_id?: number
  letra?: string
}

/** Línea de discriminación de IVA: neto gravado a una alícuota. */
export interface DiscriminacionInput {
  neto_gravado: string
  iva_alicuota: string
}

/** Cabecera de la venta enviada al backend (las líneas/total los calcula el motor). */
export interface VentaInput {
  fecha: string
  tipo_comprobante_id?: number | null
  tipo_operacion_venta_id?: number | null
  condicion_iva_id?: number | null
  provincia_id?: number | null
  cliente_id?: number | null
  cliente_nombre?: string | null
  cuit?: string | null
  letra?: string | null
  punto_venta?: string | null
  numero?: string | null
  neto_no_grav?: string | null
  exento?: string | null
  imp_interno?: string | null
  actividad_id?: number | null
  es_bien_uso?: string | null
  discriminaciones: DiscriminacionInput[]
}

/** Venta completa (cabecera + líneas) tal como la devuelve el backend para editar. */
export interface VentaDetalle {
  id: number
  fecha: string | null
  tipo_comprobante_id: number | null
  tipo_operacion_venta_id: number | null
  condicion_iva_id: number | null
  provincia_id: number | null
  cliente_id: number | null
  cliente_nombre: string | null
  cuit: string | null
  letra: string | null
  punto_venta: number | string | null
  numero: number | string | null
  neto_no_grav: string | null
  exento: string | null
  imp_interno: string | null
  actividad_id: number | null
  es_bien_uso: string | null
  total: string
  discriminaciones: Array<{
    id: number
    neto_gravado: string
    iva_alicuota: string
    iva_importe: string
  }>
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
