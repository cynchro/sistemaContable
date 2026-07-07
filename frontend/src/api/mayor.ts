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

/** Movimiento del detalle de una cuenta: un comprobante imputado (como debe o haber). */
export interface MayorMovimiento {
  origen: 'venta' | 'compra'
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
