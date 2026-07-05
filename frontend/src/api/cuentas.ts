import api from './client'

/** Cuenta del plan de cuentas de una empresa (Compartido). */
export interface Cuenta {
  id: number
  empresa_id: number
  codigo: string | null
  nombre: string
}

export interface CuentaInput {
  nombre: string
  codigo?: string | null
}

export async function listCuentas(empresaId: number): Promise<Cuenta[]> {
  const { data } = await api.get(`/empresas/${empresaId}/cuentas`)
  return data.data as Cuenta[]
}

export async function createCuenta(empresaId: number, input: CuentaInput): Promise<Cuenta> {
  const { data } = await api.post(`/empresas/${empresaId}/cuentas`, input)
  return data.data as Cuenta
}

export async function updateCuenta(
  empresaId: number,
  id: number,
  input: CuentaInput,
): Promise<Cuenta> {
  const { data } = await api.put(`/empresas/${empresaId}/cuentas/${id}`, input)
  return data.data as Cuenta
}

export async function deleteCuenta(empresaId: number, id: number): Promise<void> {
  await api.delete(`/empresas/${empresaId}/cuentas/${id}`)
}
