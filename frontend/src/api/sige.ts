import api from './client'

/** Datos de un contribuyente traídos del SIGE (sistemaCuarto) por CUIT. */
export interface SugerenciaSige {
  encontrado: boolean
  sige_persona_id: number | null
  cuit: string | null
  nombre: string | null
  email: string | null
  contacto: string | null
  telefono: string | null
  tipo_persona: string | null
  inscripcion: string | null
  contabilidad: string | null
}

export async function sugerenciaSige(cuit: string): Promise<SugerenciaSige> {
  const { data } = await api.get(`/sige/${cuit}/sugerencia`)
  return data.data as SugerenciaSige
}
