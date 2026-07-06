import api from './client'
import type { TipoRetencion } from './catalogos'

/** ABM de tipos de retención/percepción por tenant. El listado trae los estándar de AFIP
 * (`tenant_id` NULL, read-only) + los propios del estudio; sólo los propios son editables
 * (el backend responde 404 al intentar editar/borrar un estándar). */
export interface TipoRetencionInput {
  nombre: string
  cod_afip?: string | null
  alicuota?: string | null
  tipo_rg3685?: number | null
  provincia_id?: number | null
  base_calculo?: string | null
}

export async function listTiposRetencionAbm(): Promise<TipoRetencion[]> {
  const { data } = await api.get('/tipos-retencion')
  return data.data as TipoRetencion[]
}

export async function createTipoRetencion(input: TipoRetencionInput): Promise<TipoRetencion> {
  const { data } = await api.post('/tipos-retencion', input)
  return data.data as TipoRetencion
}

export async function updateTipoRetencion(id: number, input: TipoRetencionInput): Promise<TipoRetencion> {
  const { data } = await api.put(`/tipos-retencion/${id}`, input)
  return data.data as TipoRetencion
}

export async function deleteTipoRetencion(id: number): Promise<void> {
  await api.delete(`/tipos-retencion/${id}`)
}
