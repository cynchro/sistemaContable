import api from './client'

/** Fila del resumen de Mayor de Cuentas: total debe/haber/saldo de una cuenta en el período. */
export interface MayorResumen {
  id: number
  codigo: string | null
  nombre: string
  debe: string
  haber: string
  saldo: string
  movimientos: number
}

/** Movimiento del detalle de una cuenta: neto de una línea o total de un comprobante. */
export interface MayorMovimiento {
  origen: 'venta' | 'compra'
  /** 'linea' = neto de la línea (gasto/ingreso) · 'comprobante' = total (contrapartida). */
  nivel: 'linea' | 'comprobante'
  lado: 'debe' | 'haber'
  fecha: string | null
  cbte_codigo: string | null
  letra: string | null
  punto_venta: string | null
  numero: string | null
  nombre: string | null
  importe: string
}

export async function getMayorResumen(empresaId: number, periodoId: number): Promise<MayorResumen[]> {
  const { data } = await api.get(`/empresas/${empresaId}/periodos/${periodoId}/mayor`)
  return data.data as MayorResumen[]
}

export async function getMayorDetalle(
  empresaId: number,
  periodoId: number,
  cuentaId: number,
): Promise<MayorMovimiento[]> {
  const { data } = await api.get(`/empresas/${empresaId}/periodos/${periodoId}/mayor/${cuentaId}`)
  return data.data as MayorMovimiento[]
}

/** Subtotal/total de un nodo del reporte de Mayor (neto/iva con signo + cantidad). */
export interface MayorTotales {
  neto: string
  iva: string
  cantidad: number
}

/** Renglón hoja del reporte: un movimiento (neto de una línea imputada). */
export interface MayorReporteHoja {
  fecha: string | null
  origen: 'venta' | 'compra' | null
  comprobante: string
  sujeto: string | null
  cuit: string | null
  provincia: string | null
  cuenta: string | null
  neto: string
  iva: string
}

/** Nodo de grupo del reporte (cascada): subtotal + hijos (subgrupos u hojas). */
export interface MayorReporteGrupo {
  dimension: 'cuenta' | 'proveedor' | 'provincia'
  clave: string
  etiqueta: string
  subtotal: MayorTotales
  hijos: MayorReporteGrupo[] | MayorReporteHoja[]
}

export interface MayorReporte {
  grupos: MayorReporteGrupo[]
  totales: MayorTotales
  filtros: Record<string, unknown>
  agrupar: string[]
}

export interface MayorReporteFiltros {
  desde?: string
  hasta?: string
  cuenta_id?: number | null
  provincia_id?: number | null
  cuit?: string
  origen?: 'venta' | 'compra' | ''
  agrupar?: string
}

export async function getReporteMayor(empresaId: number, filtros: MayorReporteFiltros): Promise<MayorReporte> {
  const params: Record<string, string | number> = {}
  if (filtros.desde) params.desde = filtros.desde
  if (filtros.hasta) params.hasta = filtros.hasta
  if (filtros.cuenta_id) params.cuenta_id = filtros.cuenta_id
  if (filtros.provincia_id) params.provincia_id = filtros.provincia_id
  if (filtros.cuit) params.cuit = filtros.cuit
  if (filtros.origen) params.origen = filtros.origen
  if (filtros.agrupar) params.agrupar = filtros.agrupar
  const { data } = await api.get(`/empresas/${empresaId}/reportes/mayor`, { params })
  return data.data as MayorReporte
}
