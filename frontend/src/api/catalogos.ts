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

/**
 * Tipo de retención/percepción. Es el mismo catálogo para percepciones (ventas) y
 * retenciones (compras); `base_calculo` parametriza cómo el motor calcula la base.
 * `tenant_id` NULL = estándar AFIP (read-only); con valor = propio del estudio.
 */
export interface TipoRetencion {
  id: number
  cod_afip: string | null
  nombre: string
  alicuota: string
  tipo_rg3685: number | null
  provincia_id: number | null
  tenant_id: string | null
  base_calculo: string
}

export async function listTiposRetencion(): Promise<TipoRetencion[]> {
  const { data } = await api.get('/catalogos/tipos-retencion')
  return data.data as TipoRetencion[]
}
