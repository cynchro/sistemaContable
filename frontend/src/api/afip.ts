import api from './client'

/** Datos del padrón A5 de ARCA (consulta por CUIT). */
export interface PersonaPadron {
  cuit: string | null
  tipo_persona: string | null
  estado_clave: string | null
  denominacion: string | null
  domicilio: Record<string, string | null> | null
  impuestos: Array<Record<string, unknown>> | null
}

export async function consultarPadron(cuit: string): Promise<PersonaPadron> {
  const { data } = await api.get(`/padron/${cuit}`)
  return data.data as PersonaPadron
}

/** Sugerencia de padrón mapeada a los campos del alta de cliente/proveedor. */
export interface SugerenciaPadron {
  nombre: string | null
  cuit: string | null
  domicilio: string | null
  localidad: string | null
  padron: {
    tipo_persona: string | null
    estado_clave: string | null
    denominacion: string | null
    domicilio: Record<string, string | null> | null
    impuestos: Array<Record<string, unknown>> | null
  }
}

export async function sugerenciaPadron(cuit: string): Promise<SugerenciaPadron> {
  const { data } = await api.get(`/padron/${cuit}/sugerencia`)
  return data.data as SugerenciaPadron
}

/** Resultado de la emisión de CAE (WSFEv1). */
export interface ResultadoCae {
  numero: number
  cae: string
  cae_vto: string | null
}

export async function emitirCae(empresaId: number, periodoId: number, ventaId: number): Promise<ResultadoCae> {
  const { data } = await api.post(`/empresas/${empresaId}/periodos/${periodoId}/ventas/${ventaId}/cae`)
  return data.data as ResultadoCae
}

/** Punto de venta de la empresa (numeración de comprobantes). */
export interface PuntoVenta {
  id: number
  numero: number
  descripcion: string | null
  tipo_emision: string | null
  activo: string // 'S' | 'N'
}

export interface PuntoVentaInput {
  numero: number
  descripcion?: string | null
  tipo_emision?: string | null
  activo?: string | null
}

const pvBase = (empresaId: number) => `/empresas/${empresaId}/puntos-venta`

export async function listPuntosVenta(empresaId: number): Promise<PuntoVenta[]> {
  const { data } = await api.get(pvBase(empresaId))
  return data.data as PuntoVenta[]
}

export async function createPuntoVenta(empresaId: number, input: PuntoVentaInput): Promise<PuntoVenta> {
  const { data } = await api.post(pvBase(empresaId), input)
  return data.data as PuntoVenta
}

export async function deletePuntoVenta(empresaId: number, id: number): Promise<void> {
  await api.delete(`${pvBase(empresaId)}/${id}`)
}
