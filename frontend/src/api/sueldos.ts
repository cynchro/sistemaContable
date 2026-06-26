import api from './client'

/* ------------------------------ Empleados (legajo) ------------------------------ */

export interface Empleado {
  id: number
  legajo: number | null
  nombres: string
  primer_apellido: string | null
  segundo_apellido: string | null
  cuil: string | null
  fecha_ingreso: string | null
  basico: string | null
  email: string | null
  activo: string // 'S' | 'N'
}

export interface EmpleadoInput {
  nombres: string
  primer_apellido?: string | null
  segundo_apellido?: string | null
  legajo?: number | null
  cuil?: string | null
  fecha_ingreso?: string | null
  basico?: string | null
  email?: string | null
  activo?: string | null
}

const empBase = (empresaId: number) => `/empresas/${empresaId}/empleados`

export async function listEmpleados(empresaId: number): Promise<Empleado[]> {
  const { data } = await api.get(empBase(empresaId))
  return data.data as Empleado[]
}
export async function createEmpleado(empresaId: number, input: EmpleadoInput): Promise<Empleado> {
  const { data } = await api.post(empBase(empresaId), input)
  return data.data as Empleado
}
export async function updateEmpleado(empresaId: number, id: number, input: EmpleadoInput): Promise<Empleado> {
  const { data } = await api.put(`${empBase(empresaId)}/${id}`, input)
  return data.data as Empleado
}
export async function deleteEmpleado(empresaId: number, id: number): Promise<void> {
  await api.delete(`${empBase(empresaId)}/${id}`)
}

/* ------------------------------ Conceptos ------------------------------ */

export interface Concepto {
  id: number
  codigo: string
  descripcion: string
  formula: string | null
  tipo: number | null
  orden: number | null
  imprimir: string | null
}

export interface ConceptoInput {
  codigo: string
  descripcion: string
  formula?: string | null
  tipo?: number | null
  orden?: number | null
  imprimir?: string | null
}

const conBase = (empresaId: number) => `/empresas/${empresaId}/conceptos`

export async function listConceptos(empresaId: number): Promise<Concepto[]> {
  const { data } = await api.get(conBase(empresaId))
  return data.data as Concepto[]
}
export async function createConcepto(empresaId: number, input: ConceptoInput): Promise<Concepto> {
  const { data } = await api.post(conBase(empresaId), input)
  return data.data as Concepto
}
export async function updateConcepto(empresaId: number, id: number, input: ConceptoInput): Promise<Concepto> {
  const { data } = await api.put(`${conBase(empresaId)}/${id}`, input)
  return data.data as Concepto
}
export async function deleteConcepto(empresaId: number, id: number): Promise<void> {
  await api.delete(`${conBase(empresaId)}/${id}`)
}

/* ------------------------------ Liquidaciones ------------------------------ */

export interface Liquidacion {
  id: number
  periodo_liquidado: string
  descripcion: string | null
  tipo: number | null
  fecha_pago: string | null
  bloqueada: string // 'S' | 'N'
}

export interface LiquidacionInput {
  periodo_liquidado: string
  descripcion?: string | null
  fecha_pago?: string | null
}

const liqBase = (empresaId: number) => `/empresas/${empresaId}/liquidaciones`

export async function listLiquidaciones(empresaId: number): Promise<Liquidacion[]> {
  const { data } = await api.get(liqBase(empresaId))
  return data.data as Liquidacion[]
}
export async function createLiquidacion(empresaId: number, input: LiquidacionInput): Promise<Liquidacion> {
  const { data } = await api.post(liqBase(empresaId), input)
  return data.data as Liquidacion
}
export async function deleteLiquidacion(empresaId: number, id: number): Promise<void> {
  await api.delete(`${liqBase(empresaId)}/${id}`)
}

/* ---- Novedades / liquidar / recibo (por liquidación + empleado) ---- */

export interface Novedad {
  concepto_id: number
  cantidad: string
  importe: string
}

export interface LineaRecibo {
  item: number
  concepto_id: number | null
  codigo: string | null
  descripcion: string | null
  cantidad: string
  importe: string
  tipo: number
}

export interface Recibo {
  empleado: Record<string, string | number | null>
  lineas: LineaRecibo[]
  neto?: string
}

const nest = (empresaId: number, liqId: number, empId: number) =>
  `/empresas/${empresaId}/liquidaciones/${liqId}/empleados/${empId}`

export async function getNovedades(empresaId: number, liqId: number, empId: number): Promise<Novedad[]> {
  const { data } = await api.get(`${nest(empresaId, liqId, empId)}/novedades`)
  return data.data as Novedad[]
}
export async function setNovedades(
  empresaId: number,
  liqId: number,
  empId: number,
  novedades: Novedad[],
): Promise<unknown> {
  const { data } = await api.put(`${nest(empresaId, liqId, empId)}/novedades`, { novedades })
  return data.data
}
export async function liquidar(empresaId: number, liqId: number, empId: number): Promise<Recibo> {
  const { data } = await api.post(`${nest(empresaId, liqId, empId)}/liquidar`)
  return data.data as Recibo
}
export async function getRecibo(empresaId: number, liqId: number, empId: number): Promise<Recibo> {
  const { data } = await api.get(`${nest(empresaId, liqId, empId)}/recibo`)
  return data.data as Recibo
}
