import api from './client'

/**
 * Reglas de imputación contable del proveedor (documento "Satélite Visual IVA" §5.4, Pantalla B
 * — página aparte, decisión B2). Migración 0051: capa de "concepto" global — la regla de punto
 * de venta se carga UNA vez por proveedor y aplica a todas las empresas; cada empresa la
 * traduce a su propia cuenta vía el mapeo concepto→cuenta.
 */
export interface ReglaPuntoVenta {
  id: number
  punto_venta: string
  concepto_id: number
  concepto_nombre: string
  /** null = el concepto todavía no está mapeado a una cuenta en esta empresa. */
  cuenta_id: number | null
  cuenta_codigo: string | null
  cuenta_nombre: string | null
}

export interface MapeoConceptoCuenta {
  id: number
  concepto_id: number
  concepto_nombre: string
  cuenta_id: number
  cuenta_codigo: string | null
  cuenta_nombre: string
}

const base = (empresaId: number, proveedorId: number) =>
  `/empresas/${empresaId}/proveedores/${proveedorId}/imputacion`

// ── 1. Regla global de punto de venta (todas las empresas) ─────────────────────────────────

export async function listReglasGlobales(empresaId: number, proveedorId: number): Promise<ReglaPuntoVenta[]> {
  const { data } = await api.get(`${base(empresaId, proveedorId)}/global`)
  return data.data as ReglaPuntoVenta[]
}
export async function setReglaGlobal(
  empresaId: number,
  proveedorId: number,
  puntoVenta: string,
  conceptoId: number,
): Promise<void> {
  await api.post(`${base(empresaId, proveedorId)}/global`, { punto_venta: puntoVenta, concepto_id: conceptoId })
}
export async function deleteReglaGlobal(empresaId: number, proveedorId: number, id: number): Promise<void> {
  await api.delete(`${base(empresaId, proveedorId)}/global/${id}`)
}

// ── 2. Excepción de punto de venta para esta empresa ────────────────────────────────────────

export async function listReglasEmpresa(empresaId: number, proveedorId: number): Promise<ReglaPuntoVenta[]> {
  const { data } = await api.get(`${base(empresaId, proveedorId)}/empresa`)
  return data.data as ReglaPuntoVenta[]
}
export async function setReglaEmpresa(
  empresaId: number,
  proveedorId: number,
  puntoVenta: string,
  conceptoId: number,
): Promise<void> {
  await api.post(`${base(empresaId, proveedorId)}/empresa`, { punto_venta: puntoVenta, concepto_id: conceptoId })
}
export async function deleteReglaEmpresa(empresaId: number, proveedorId: number, id: number): Promise<void> {
  await api.delete(`${base(empresaId, proveedorId)}/empresa/${id}`)
}

// ── 3. Excepción del concepto por defecto para esta empresa ─────────────────────────────────

export async function getConceptoExcepcion(empresaId: number, proveedorId: number): Promise<number | null> {
  const { data } = await api.get(`${base(empresaId, proveedorId)}/concepto-default`)
  return data.data.concepto_id as number | null
}
export async function setConceptoExcepcion(
  empresaId: number,
  proveedorId: number,
  conceptoId: number | null,
): Promise<void> {
  await api.put(`${base(empresaId, proveedorId)}/concepto-default`, { concepto_id: conceptoId })
}

// ── Mapeo concepto→cuenta de la empresa (no depende de ningún proveedor puntual) ────────────

export async function listMapeoEmpresa(empresaId: number): Promise<MapeoConceptoCuenta[]> {
  const { data } = await api.get(`/empresas/${empresaId}/conceptos-cuenta`)
  return data.data as MapeoConceptoCuenta[]
}
export async function setMapeoEmpresa(empresaId: number, conceptoId: number, cuentaId: number): Promise<void> {
  await api.post(`/empresas/${empresaId}/conceptos-cuenta`, { concepto_id: conceptoId, cuenta_id: cuentaId })
}
export async function deleteMapeoEmpresa(empresaId: number, conceptoId: number): Promise<void> {
  await api.delete(`/empresas/${empresaId}/conceptos-cuenta/${conceptoId}`)
}
