import api from './client'

/** Rubro / Actividad por tenant (estudio). Se usa en la carga de comprobantes para el
 * reporte "Resumen de IVA por Rubro / Actividad" (F2002). */
export interface Rubro {
  id: number
  tenant_id: string | null
  nombre: string
  codigo: string | null
}

export interface RubroInput {
  nombre: string
  codigo?: string | null
}

export async function listRubros(): Promise<Rubro[]> {
  const { data } = await api.get('/rubros')
  return data.data as Rubro[]
}

export async function createRubro(input: RubroInput): Promise<Rubro> {
  const { data } = await api.post('/rubros', input)
  return data.data as Rubro
}

export async function updateRubro(id: number, input: RubroInput): Promise<Rubro> {
  const { data } = await api.put(`/rubros/${id}`, input)
  return data.data as Rubro
}

export async function deleteRubro(id: number): Promise<void> {
  await api.delete(`/rubros/${id}`)
}
