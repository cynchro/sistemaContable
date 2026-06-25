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

export async function deleteVenta(empresaId: number, periodoId: number, id: number): Promise<void> {
  await api.delete(`/empresas/${empresaId}/periodos/${periodoId}/ventas/${id}`)
}
