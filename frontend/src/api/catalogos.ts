import api from './client'

export interface CatalogoItem {
  id: number
  codigo: string
  nombre: string
}

/** Catálogos de sólo lectura de AFIP (condiciones de IVA, provincias, etc.). */
export async function listCatalogo(slug: string): Promise<CatalogoItem[]> {
  const { data } = await api.get(`/catalogos/${slug}`)
  return data.data as CatalogoItem[]
}
