import api from './client'

const base = (empresaId: number, periodoId: number) =>
  `/empresas/${empresaId}/periodos/${periodoId}`

/** Totales del período (con signo: las NC restan). */
export interface Totales {
  total_ventas: string
  total_compras: string
  iva_ventas: string
  iva_compras: string
  saldo_iva: string
}

/** Renglón del libro IVA detallado, agrupado por condición de IVA + alícuota. */
export interface DetalleAlicuota {
  condicion_iva_id: number | null
  alicuota: string | null
  neto_gravado: string
  iva: string
  cf_computable?: string
}

export interface LibroDetalle {
  ventas: DetalleAlicuota[]
  compras: DetalleAlicuota[]
}

/** DDJJ F2002: débito vs crédito fiscal computable y saldo técnico. */
export interface Ddjj {
  debito_fiscal: { por_alicuota: DetalleAlicuota[]; neto_gravado: string; iva: string }
  credito_fiscal: { por_alicuota: DetalleAlicuota[]; neto_gravado: string; iva: string; computable: string }
  conceptos_ventas: Record<string, string>
  conceptos_compras: Record<string, string>
  saldo: { debito_fiscal: string; credito_computable: string; saldo_tecnico: string }
}

/** DDJJ IVA Simple (F.2051) — determinación del impuesto y posición mensual. */
export interface IvaSimple {
  debito_fiscal: Ddjj['debito_fiscal']
  credito_fiscal: Ddjj['credito_fiscal']
  determinacion_impuesto: {
    debito_fiscal: string
    credito_fiscal: string
    saldo_tecnico_anterior: string
    saldo_tecnico_a_favor_arca: string
    saldo_tecnico_a_favor_contribuyente: string
  }
  posicion_mensual: {
    saldo_tecnico_a_favor_arca: string
    saldo_libre_disponibilidad_anterior: string
    retenciones_percepciones_pagos: string
    saldo_a_pagar: string
    saldo_libre_disponibilidad_periodo: string
  }
}

export async function getTotales(empresaId: number, periodoId: number): Promise<Totales> {
  const { data } = await api.get(`${base(empresaId, periodoId)}/totales`)
  return data.data as Totales
}

export async function getLibroDetalle(empresaId: number, periodoId: number): Promise<LibroDetalle> {
  const { data } = await api.get(`${base(empresaId, periodoId)}/libro-iva`)
  return data.data as LibroDetalle
}

export async function getDdjj(empresaId: number, periodoId: number): Promise<Ddjj> {
  const { data } = await api.get(`${base(empresaId, periodoId)}/ddjj`)
  return data.data as Ddjj
}

export async function getIvaSimple(
  empresaId: number,
  periodoId: number,
  retencionesPercepcionesPagos?: string,
): Promise<IvaSimple> {
  const params: Record<string, string> = {}
  if (retencionesPercepcionesPagos) params.retenciones_percepciones_pagos = retencionesPercepcionesPagos
  const { data } = await api.get(`${base(empresaId, periodoId)}/iva-simple`, { params })
  return data.data as IvaSimple
}

export async function presentarIvaSimple(
  empresaId: number,
  periodoId: number,
  retencionesPercepcionesPagos: string,
): Promise<IvaSimple> {
  const { data } = await api.post(`${base(empresaId, periodoId)}/iva-simple`, {
    retenciones_percepciones_pagos: retencionesPercepcionesPagos,
  })
  return data.data as IvaSimple
}

/**
 * Descarga autenticada: pide el archivo como blob (con el Bearer del interceptor) y
 * dispara la descarga en el navegador con el nombre que sugiere el backend.
 */
async function descargar(url: string, params?: Record<string, string>): Promise<void> {
  const res = await api.get(url, { params, responseType: 'blob' })
  const disposition: string = res.headers['content-disposition'] ?? ''
  const match = /filename="?([^"]+)"?/.exec(disposition)
  const nombre = match?.[1] ?? url.split('/').pop() ?? 'descarga.txt'

  const href = URL.createObjectURL(res.data as Blob)
  const a = document.createElement('a')
  a.href = href
  a.download = nombre
  document.body.appendChild(a)
  a.click()
  a.remove()
  URL.revokeObjectURL(href)
}

/** Subdiario de ventas/compras como CSV o TXT (ancho delimitado). */
export function descargarSubdiario(
  empresaId: number,
  periodoId: number,
  tipo: 'ventas' | 'compras',
  formato: 'csv' | 'txt',
): Promise<void> {
  return descargar(`${base(empresaId, periodoId)}/exportar/${tipo}`, { formato })
}

/** Archivos del Libro IVA Digital (Portal IVA, ancho fijo). */
export type ArchivoDigital = 'ventas-cbte' | 'ventas-alicuotas' | 'compras-cbte' | 'compras-alicuotas'

export function descargarLibroIvaDigital(
  empresaId: number,
  periodoId: number,
  archivo: ArchivoDigital,
): Promise<void> {
  return descargar(`${base(empresaId, periodoId)}/libro-iva-digital/${archivo}`)
}
