import api from './client'
import type { Pagina } from './pagina'

export type { Pagina }

/** Botón "Liquidar IVA" (plan 25/08/2026): cola de pedidos consumida por el worker del bot
 * (`cositasVarias/extractor`), fuera de este repo. */
export type Direccion = 'traer' | 'subir' | 'ambos'
export type LibroLiquidacion = 'ventas' | 'compras' | 'ambos'
export type EstadoLiquidacion = 'pendiente' | 'tomada' | 'en_curso' | 'terminada' | 'error'

export const ESTADOS_ABIERTOS: EstadoLiquidacion[] = ['pendiente', 'tomada', 'en_curso']

export interface Liquidacion {
  id: number
  empresa_id: number
  periodo_id: number
  direccion: Direccion
  libro: LibroLiquidacion
  periodo_arca: string
  estado: EstadoLiquidacion
  resultado: string | null
  creado_por: number
  tomada_en: string | null
  terminada_en: string | null
  created_at: string
  updated_at: string
}

export async function crearLiquidacion(
  empresaId: number,
  periodoId: number,
  direccion: Direccion,
  libro: LibroLiquidacion,
): Promise<Liquidacion> {
  const { data } = await api.post(`/empresas/${empresaId}/periodos/${periodoId}/liquidaciones`, {
    direccion,
    libro,
  })
  return data.data as Liquidacion
}

export async function getLiquidacion(empresaId: number, periodoId: number, id: number): Promise<Liquidacion> {
  const { data } = await api.get(`/empresas/${empresaId}/periodos/${periodoId}/liquidaciones/${id}`)
  return data.data as Liquidacion
}

export async function listLiquidaciones(
  empresaId: number,
  periodoId: number,
  page = 1,
  perPage = 10,
): Promise<Pagina<Liquidacion>> {
  const { data } = await api.get(`/empresas/${empresaId}/periodos/${periodoId}/liquidaciones`, {
    params: { page, per_page: perPage },
  })
  return data.data as Pagina<Liquidacion>
}
