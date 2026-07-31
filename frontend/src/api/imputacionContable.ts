import api from './client'

/**
 * Reglas de imputación contable por punto de venta del proveedor (documento "Satélite Visual
 * IVA" §5.4, Pantalla B — página aparte, decisión B2). La cuenta por defecto del proveedor se
 * administra en su propio modal (Pantalla A, `cuenta_id` en SujetoFormModal); acá solo la
 * excepción por punto de venta.
 */
export interface ImputacionPuntoVenta {
  id: number
  punto_venta: string
  cuenta_id: number
  cuenta_codigo: string | null
  cuenta_nombre: string
}

const base = (empresaId: number, proveedorId: number) =>
  `/empresas/${empresaId}/proveedores/${proveedorId}/imputacion`

export async function listImputacion(empresaId: number, proveedorId: number): Promise<ImputacionPuntoVenta[]> {
  const { data } = await api.get(base(empresaId, proveedorId))
  return data.data as ImputacionPuntoVenta[]
}

export async function setImputacion(
  empresaId: number,
  proveedorId: number,
  puntoVenta: string,
  cuentaId: number,
): Promise<void> {
  await api.post(base(empresaId, proveedorId), { punto_venta: puntoVenta, cuenta_id: cuentaId })
}

export async function deleteImputacion(empresaId: number, proveedorId: number, id: number): Promise<void> {
  await api.delete(`${base(empresaId, proveedorId)}/${id}`)
}
