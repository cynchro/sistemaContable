import api from './client'

const base = (empresaId: number, periodoId: number) =>
  `/empresas/${empresaId}/periodos/${periodoId}/reportes`

/** Renglón del subdiario (comprobante enriquecido con importes calculados). */
export type ComprobanteSubdiario = Record<string, string | number | null>

export interface Subdiario {
  comprobantes: ComprobanteSubdiario[]
  totales: Record<string, string>
}

export async function getSubdiarioVentas(empresaId: number, periodoId: number): Promise<Subdiario> {
  const { data } = await api.get(`${base(empresaId, periodoId)}/ventas`)
  return data.data as Subdiario
}

export async function getSubdiarioCompras(empresaId: number, periodoId: number): Promise<Subdiario> {
  const { data } = await api.get(`${base(empresaId, periodoId)}/compras`)
  return data.data as Subdiario
}

/** Percepción agrupada por tipo (y provincia) en el reporte secundario. */
export interface PercepcionGrupo {
  tipo_retencion_id: number | null
  tipo_nombre: string | null
  tipo_cod_afip: string | null
  provincia_nombre: string | null
  cantidad: number | string
  base: string
  importe: string
}

export interface ReportePercepciones {
  ventas: PercepcionGrupo[]
  compras: PercepcionGrupo[]
  totales: { ventas: string; compras: string }
}

export async function getPercepciones(empresaId: number, periodoId: number): Promise<ReportePercepciones> {
  const { data } = await api.get(`${base(empresaId, periodoId)}/percepciones`)
  return data.data as ReportePercepciones
}
