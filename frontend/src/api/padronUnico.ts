import api from './client'
import type { Pagina } from './pagina'

/**
 * Vista global del Padrón Único de Sujetos (documento "Satélite Visual IVA" §10, Etapa 4):
 * todos los sujetos del tenant, sin filtrar por empresa, con las empresas donde cada uno está
 * activo. Distinta del padrón de AFIP (`api/padron.ts` si existiera — acá no hay consulta
 * externa, es de solo lectura sobre `iva_sujetos`).
 *
 * Separada en dos vistas por `rol` (informe del cliente 10/08/2026, pedido 5a: "mezclar el
 * padrón de proveedores y el de clientes en una sola integración no es posible... hacelos
 * separados") — nunca se pide sin `rol` desde el frontend.
 */
export type RolPadron = 'proveedor' | 'cliente'

export interface SujetoGlobal {
  id: number
  nombre: string
  cuit: string
  condicion_iva_id: number | null
  provincia_id: number | null
  localidad: string | null
  empresas: { empresa_id: number; empresa_nombre: string; rol: 'cliente' | 'proveedor' }[]
}

export async function listPadronUnico(
  rol: RolPadron,
  q: string | undefined,
  page: number,
  perPage: number,
): Promise<Pagina<SujetoGlobal>> {
  const params: Record<string, string | number> = { rol, page, per_page: perPage }
  if (q) params.q = q
  const { data } = await api.get('/padron-unico', { params })
  return data.data as Pagina<SujetoGlobal>
}

/** "CUIT único" (informe del cliente 10/08/2026, pedido 3): ¿ese CUIT ya está en el padrón? */
export interface SujetoPorCuit {
  encontrado: boolean
  id: number | null
  nombre: string | null
  cuit: string | null
  domicilio: string | null
  localidad: string | null
  provincia_id: number | null
  telefono: string | null
  condicion_iva_id: number | null
}

export async function buscarSujetoPorCuit(cuit: string): Promise<SujetoPorCuit> {
  const { data } = await api.get(`/padron-unico/cuit/${cuit}`)
  return data.data as SujetoPorCuit
}
