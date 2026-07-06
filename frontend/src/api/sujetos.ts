import api from './client'

/** Recurso anidado bajo empresa: clientes o proveedores (comparten estructura). */
export type RecursoSujeto = 'clientes' | 'proveedores'

export interface Sujeto {
  id: number
  empresa_id: number
  nombre: string
  cuit: string | null
  condicion_iva_id: number | null
  provincia_id: number | null
  domicilio: string | null
  localidad: string | null
  telefono: string | null
  ingresos_brutos: string | null
  /** 'S' = compartido entre todas las empresas del estudio (tenant). */
  esglobal?: string | null
  cp?: string | null
  cai?: string | null
  fecha_cai?: string | null
  /** CAI adicionales del proveedor (hasta 5). */
  cais?: CaiItem[] | null
}

/** Un CAI de proveedor: número + fecha de vencimiento. */
export interface CaiItem {
  numero: string
  vencimiento: string
}

export interface SujetoInput {
  nombre: string
  cuit?: string | null
  condicion_iva_id?: number | null
  provincia_id?: number | null
  domicilio?: string | null
  localidad?: string | null
  telefono?: string | null
  ingresos_brutos?: string | null
  esglobal?: string | null
  cp?: string | null
  cai?: string | null
  fecha_cai?: string | null
  cais?: CaiItem[] | null
}

const base = (recurso: RecursoSujeto, empresaId: number) => `/empresas/${empresaId}/${recurso}`

export async function listSujetos(recurso: RecursoSujeto, empresaId: number): Promise<Sujeto[]> {
  const { data } = await api.get(base(recurso, empresaId))
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
