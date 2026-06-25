import api from './client'

export interface Periodo {
  id: number
  empresa_id: number
  nombre: string
  fecha_ini: string | null
  fecha_fin: string | null
  cerrado: string // 'S' | 'N'
}

export interface PeriodoInput {
  nombre: string
  fecha_ini: string
  fecha_fin: string
}

export async function listPeriodos(empresaId: number): Promise<Periodo[]> {
  const { data } = await api.get(`/empresas/${empresaId}/periodos`)
  return data.data as Periodo[]
}

export async function createPeriodo(empresaId: number, input: PeriodoInput): Promise<Periodo> {
  const { data } = await api.post(`/empresas/${empresaId}/periodos`, input)
  return data.data as Periodo
}

export async function deletePeriodo(empresaId: number, id: number): Promise<void> {
  await api.delete(`/empresas/${empresaId}/periodos/${id}`)
}

export async function cerrarPeriodo(empresaId: number, id: number): Promise<void> {
  await api.post(`/empresas/${empresaId}/periodos/${id}/cerrar`)
}

export async function abrirPeriodo(empresaId: number, id: number): Promise<void> {
  await api.post(`/empresas/${empresaId}/periodos/${id}/abrir`)
}
