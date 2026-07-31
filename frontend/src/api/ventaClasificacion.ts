import api from './client'

/**
 * Motor de clasificación de ventas por punto de venta + tipo de comprobante (documento
 * "Satélite Visual IVA" §4, Pantalla D). A diferencia de Actividades, resuelve una cuenta
 * contable, no una actividad, y no depende de ningún sujeto (el PV es del propio
 * contribuyente).
 */
export interface VentaPuntoVenta {
  id: number
  punto_venta: string
  cuenta_id: number
  cuenta_codigo: string | null
  cuenta_nombre: string
}

export interface VentaPuntoVentaTipo {
  id: number
  punto_venta: string
  tipo_comprobante_id: number
  tipo_comprobante_nombre: string
  cuenta_id: number
  cuenta_codigo: string | null
  cuenta_nombre: string
}

const base = (empresaId: number) => `/empresas/${empresaId}/ventas-punto-venta`

export async function listPuntoVenta(empresaId: number): Promise<VentaPuntoVenta[]> {
  const { data } = await api.get(base(empresaId))
  return data.data as VentaPuntoVenta[]
}
export async function setPuntoVenta(empresaId: number, puntoVenta: string, cuentaId: number): Promise<void> {
  await api.post(base(empresaId), { punto_venta: puntoVenta, cuenta_id: cuentaId })
}
export async function deletePuntoVenta(empresaId: number, id: number): Promise<void> {
  await api.delete(`${base(empresaId)}/${id}`)
}

export async function listPorTipo(empresaId: number): Promise<VentaPuntoVentaTipo[]> {
  const { data } = await api.get(`${base(empresaId)}-tipo`)
  return data.data as VentaPuntoVentaTipo[]
}
export async function setPorTipo(
  empresaId: number,
  puntoVenta: string,
  tipoComprobanteId: number,
  cuentaId: number,
): Promise<void> {
  await api.post(`${base(empresaId)}-tipo`, {
    punto_venta: puntoVenta,
    tipo_comprobante_id: tipoComprobanteId,
    cuenta_id: cuentaId,
  })
}
export async function deletePorTipo(empresaId: number, id: number): Promise<void> {
  await api.delete(`${base(empresaId)}-tipo/${id}`)
}
