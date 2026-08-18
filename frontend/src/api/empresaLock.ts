import api from './client'

/**
 * "Ocupación" de una empresa (WhatsApp con el cliente, 11/08/2026): un usuario a la vez trabaja
 * sobre un contribuyente. `ocuparEmpresa` se llama al elegirla activa en el header; si otro
 * usuario NO-admin ya la tiene ocupada, el backend responde 409 (bloqueo total). Un admin recibe
 * `modo: 'observador'` en vez de un error — puede ver, pero el backend le bloquea cualquier
 * escritura (403) mientras dure.
 */
export interface EstadoOcupacion {
  modo: 'propio' | 'observador'
  ocupado_por: string | null
  desde: string | null
}

export async function ocuparEmpresa(empresaId: number): Promise<EstadoOcupacion> {
  const { data } = await api.post(`/empresas/${empresaId}/ocupar`)
  return data.data as EstadoOcupacion
}

export async function pingEmpresa(empresaId: number): Promise<void> {
  await api.post(`/empresas/${empresaId}/ping`)
}

export async function liberarEmpresa(empresaId: number): Promise<void> {
  await api.post(`/empresas/${empresaId}/liberar`)
}
