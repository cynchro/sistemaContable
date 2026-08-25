import api from './client'

/** Credenciales de acceso del contribuyente (portales fiscales + procesadoras de tarjeta).
 * Devuelve la clave EN CLARO — el estudio la necesita para operar (cifrada en reposo del lado
 * del backend). Requiere el permiso `contribuyentes.credenciales`. */
export interface Credencial {
  id: number
  empresa_id: number
  tipo: 'fiscal' | 'tarjeta'
  sistema: string
  usuario: string | null
  clave: string | null
  estado: 'activa' | 'inactiva' | 'bloqueada' | 'cerrada' | null
  observaciones: string | null
}

export interface CredencialInput {
  tipo?: string
  sistema: string
  usuario?: string
  clave?: string
}

export async function listCredenciales(empresaId: number): Promise<Credencial[]> {
  const { data } = await api.get(`/empresas/${empresaId}/credenciales`)
  return data.data as Credencial[]
}

export async function crearCredencial(empresaId: number, payload: CredencialInput): Promise<Credencial> {
  const { data } = await api.post(`/empresas/${empresaId}/credenciales`, payload)
  return data.data as Credencial
}

export async function actualizarCredencial(
  empresaId: number,
  id: number,
  payload: Partial<CredencialInput>,
): Promise<Credencial> {
  const { data } = await api.put(`/empresas/${empresaId}/credenciales/${id}`, payload)
  return data.data as Credencial
}
