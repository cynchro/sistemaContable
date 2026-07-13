import api from './client'

export interface Empresa {
  id: number
  nombre: string
  cuit: string | null
  email: string | null
  domicilio: string | null
  localidad: string | null
  telefono: string | null
  condicion_iva_id: number | null
  provincia_id: number | null
  ingresos_brutos: string | null
  fecha_inicio_actividades: string | null
  actividad1_id: number | null
  actividad2_id: number | null
}

/** Campos editables del alta/edición de empresa (contribuyente). */
export interface EmpresaInput {
  nombre: string
  cuit?: string | null
  email?: string | null
  domicilio?: string | null
  localidad?: string | null
  telefono?: string | null
  condicion_iva_id?: number | null
  provincia_id?: number | null
  ingresos_brutos?: string | null
  fecha_inicio_actividades?: string | null
  actividad1_id?: number | null
  actividad2_id?: number | null
}

export async function listEmpresas(): Promise<Empresa[]> {
  const { data } = await api.get('/empresas')
  return data.data as Empresa[]
}

export async function createEmpresa(input: EmpresaInput): Promise<Empresa> {
  const { data } = await api.post('/empresas', input)
  return data.data as Empresa
}

export async function updateEmpresa(id: number, input: EmpresaInput): Promise<Empresa> {
  const { data } = await api.put(`/empresas/${id}`, input)
  return data.data as Empresa
}

export async function deleteEmpresa(id: number): Promise<void> {
  await api.delete(`/empresas/${id}`)
}
