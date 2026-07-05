import api from './client'
import type { VentaInput } from './ventas'
import type { CompraInput } from './compras'

/** Resultado de una importación masiva: cuántos se crearon y los errores por fila. */
export interface ImportResultado {
  total: number
  creados: number
  errores: { fila: number; error: string }[]
}

export async function importVentas(
  empresaId: number,
  periodoId: number,
  comprobantes: VentaInput[],
): Promise<ImportResultado> {
  const { data } = await api.post(
    `/empresas/${empresaId}/periodos/${periodoId}/ventas/import`,
    { comprobantes },
  )
  return data.data as ImportResultado
}

export async function importCompras(
  empresaId: number,
  periodoId: number,
  comprobantes: CompraInput[],
): Promise<ImportResultado> {
  const { data } = await api.post(
    `/empresas/${empresaId}/periodos/${periodoId}/compras/import`,
    { comprobantes },
  )
  return data.data as ImportResultado
}
