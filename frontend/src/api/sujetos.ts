import api from './client'

/** Recurso anidado bajo empresa: clientes o proveedores (comparten estructura). */
export type RecursoSujeto = 'clientes' | 'proveedores'

/**
 * Un sujeto del Padrón Único (cliente o proveedor, según el recurso de la ruta): una
 * fila por CUIT compartida por todas las empresas del estudio. `empresa_id` es la
 * empresa que lo tiene activado en este listado, no un "dueño" — el mismo sujeto puede
 * estar activado en varias empresas a la vez.
 */
export interface Sujeto {
  id: number
  empresa_id: number
  nombre: string
  cuit: string
  condicion_iva_id: number | null
  provincia_id: number | null
  domicilio: string | null
  localidad: string | null
  telefono: string | null
  ingresos_brutos: string | null
  cp?: string | null
  cai?: string | null
  fecha_cai?: string | null
  /** CAI adicionales del proveedor (hasta 5). */
  cais?: CaiItem[] | null
  /** Concepto por defecto (documento "Satélite Visual IVA" §5.2), tenant-level: aplica a todas
   * las empresas donde este proveedor esté activado, salvo excepción puntual (ver
   * ProveedorImputacionPage). null = sin regla. */
  concepto_default_id?: number | null
}

/** Un CAI de proveedor: número + fecha de vencimiento. */
export interface CaiItem {
  numero: string
  vencimiento: string
}

export interface SujetoInput {
  nombre: string
  cuit: string
  condicion_iva_id?: number | null
  provincia_id?: number | null
  domicilio?: string | null
  localidad?: string | null
  telefono?: string | null
  ingresos_brutos?: string | null
  cp?: string | null
  cai?: string | null
  fecha_cai?: string | null
  cais?: CaiItem[] | null
  concepto_default_id?: number | null
}

const base = (recurso: RecursoSujeto, empresaId: number) => `/empresas/${empresaId}/${recurso}`

export async function listSujetos(
  recurso: RecursoSujeto,
  empresaId: number,
  filtros?: { q?: string; orden?: string },
): Promise<Sujeto[]> {
  const params: Record<string, string> = {}
  if (filtros?.q) params.q = filtros.q
  if (filtros?.orden) params.orden = filtros.orden
  const { data } = await api.get(base(recurso, empresaId), { params })
  return data.data as Sujeto[]
}

export async function createSujeto(recurso: RecursoSujeto, empresaId: number, input: SujetoInput): Promise<Sujeto> {
  const { data } = await api.post(base(recurso, empresaId), input)
  return data.data as Sujeto
}

export async function updateSujeto(
  recurso: RecursoSujeto,
  empresaId: number,
  id: number,
  input: SujetoInput,
): Promise<Sujeto> {
  const { data } = await api.put(`${base(recurso, empresaId)}/${id}`, input)
  return data.data as Sujeto
}

export async function deleteSujeto(recurso: RecursoSujeto, empresaId: number, id: number): Promise<void> {
  await api.delete(`${base(recurso, empresaId)}/${id}`)
}
