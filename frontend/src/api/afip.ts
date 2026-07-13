import api from './client'

/** Datos del padrón A5 de ARCA (consulta por CUIT). */
/** Ambiente AFIP activo: 'homologacion' (pruebas) o 'produccion' (real). */
export interface AfipAmbiente {
  env: string
  cuit: string | null
}

export async function getAfipAmbiente(): Promise<AfipAmbiente> {
  const { data } = await api.get('/afip/ambiente')
  return data.data as AfipAmbiente
}

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

/** Sugerencia de padrón mapeada a los campos del alta de cliente/proveedor/empresa. */
export interface SugerenciaPadron {
  nombre: string | null
  cuit: string | null
  domicilio: string | null
  localidad: string | null
  provincia: string | null
  provincia_id: number | null
  /** Condición frente al IVA derivada del régimen (texto, para matchear el catálogo). */
  condicion_iva: string | null
  /** Inicio de actividad (YYYY-MM-01) tomado de la actividad principal. */
  inicio_actividad: string | null
  actividad_principal: string | null
  actividad_secundaria: string | null
  actividad1_id: number | null
  actividad2_id: number | null
  padron: {
    tipo_persona: string | null
    estado_clave: string | null
    denominacion: string | null
    domicilio: Record<string, string | null> | null
    impuestos: number[] | null
    actividades: Array<{ id: number; orden: number | null; descripcion: string | null; periodo: string | null }> | null
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
