import api from './client'

/**
 * Vista global del Padrón Único de Sujetos (documento "Satélite Visual IVA" §10, Etapa 4):
 * todos los sujetos del tenant, sin filtrar por empresa, con las empresas donde cada uno está
 * activo. Distinta del padrón de AFIP (`api/padron.ts` si existiera — acá no hay consulta
 * externa, es de solo lectura sobre `iva_sujetos`).
 */
export interface SujetoGlobal {
  id: number
  nombre: string
  cuit: string
  condicion_iva_id: number | null
  provincia_id: number | null
  localidad: string | null
  empresas: { empresa_id: number; empresa_nombre: string; rol: 'cliente' | 'proveedor' }[]
}

export async function listPadronUnico(q?: string): Promise<SujetoGlobal[]> {
  const { data } = await api.get('/padron-unico', { params: q ? { q } : {} })
  return data.data as SujetoGlobal[]
}
