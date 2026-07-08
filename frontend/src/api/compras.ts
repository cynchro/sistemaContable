import api from './client'
import type { Pagina, PercepcionInput, PercepcionDetalle } from './ventas'

export interface Compra {
  id: number
  fecha: string | null
  proveedor_nombre: string | null
  cuit: string | null
  letra: string | null
  punto_venta: number | null
  numero: number | null
  neto_gravado: string
  iva: string
  total: string
}

export interface ComprasFiltros {
  fecha_desde?: string
  fecha_hasta?: string
  proveedor_id?: number
  cuit?: string
  letra?: string
  nombre?: string
  numero?: string
  orden?: string
}

/** Línea de discriminación de IVA de compra: incluye el crédito fiscal computable. */
export interface CompraDiscriminacionInput {
  neto_gravado: string
  iva_alicuota: string
  cf_computable?: string | null
  iva_importe?: string | null
  /** Cuenta de mayorización del neto de la línea (gasto). */
  cuenta_id?: number | null
}

/** Cabecera de la compra enviada al backend (las líneas/total los calcula el motor). */
export interface CompraInput {
  fecha: string
  tipo_comprobante_id?: number | null
  tipo_operacion_compra_id?: number | null
  condicion_iva_id?: number | null
  provincia_id?: number | null
  rubro_id?: number | null
  proveedor_id?: number | null
  proveedor_nombre?: string | null
  cuit?: string | null
  letra?: string | null
  punto_venta?: string | null
  numero?: string | null
  cai?: string | null
  fecha_cai?: string | null
  neto_no_grav?: string | null
  exento?: string | null
  imp_interno?: string | null
  tipo_moneda_id?: number | null
  tipo_cambio?: string | null
  campo_auxiliar?: string | null
  total_informado?: string | null
  cuenta_debe_id?: number | null
  cuenta_haber_id?: number | null
  actividad_id?: number | null
  concepto_dj?: number | null
  discriminaciones: CompraDiscriminacionInput[]
  percepciones?: PercepcionInput[]
}

/** Compra completa (cabecera + líneas) tal como la devuelve el backend para editar. */
export interface CompraDetalle {
  id: number
  fecha: string | null
  tipo_comprobante_id: number | null
  tipo_operacion_compra_id: number | null
  condicion_iva_id: number | null
  provincia_id: number | null
  rubro_id: number | null
  cuenta_debe_id: number | null
  cuenta_haber_id: number | null
  proveedor_id: number | null
  proveedor_nombre: string | null
  cuit: string | null
  letra: string | null
  punto_venta: number | string | null
  numero: number | string | null
  cai: string | null
  fecha_cai: string | null
  neto_no_grav: string | null
  exento: string | null
  imp_interno: string | null
  tipo_moneda_id: number | null
  tipo_cambio: string | null
  campo_auxiliar: string | null
  actividad_id: number | null
  concepto_dj: number | null
  total: string
  total_informado: string | null
  discriminaciones: Array<{
    id: number
    neto_gravado: string
    iva_alicuota: string
    iva_importe: string
    cf_computable: string | null
    cuenta_id: number | null
  }>
  percepciones?: PercepcionDetalle[]
}

const base = (empresaId: number, periodoId: number) =>
  `/empresas/${empresaId}/periodos/${periodoId}/compras`

export async function listCompras(
  empresaId: number,
  periodoId: number,
  filtros: ComprasFiltros,
  page: number,
  perPage: number,
): Promise<Pagina<Compra>> {
  const params: Record<string, string | number> = { page, per_page: perPage }
  if (filtros.fecha_desde) params.fecha_desde = filtros.fecha_desde
  if (filtros.fecha_hasta) params.fecha_hasta = filtros.fecha_hasta
  if (filtros.proveedor_id) params.proveedor_id = filtros.proveedor_id
  if (filtros.cuit) params.cuit = filtros.cuit
  if (filtros.letra) params.letra = filtros.letra
  if (filtros.nombre) params.nombre = filtros.nombre
  if (filtros.numero) params.numero = filtros.numero
  if (filtros.orden) params.orden = filtros.orden

  const { data } = await api.get(base(empresaId, periodoId), { params })
  return data.data as Pagina<Compra>
}

export async function getCompra(empresaId: number, periodoId: number, id: number): Promise<CompraDetalle> {
  const { data } = await api.get(`${base(empresaId, periodoId)}/${id}`)
  return data.data as CompraDetalle
}

export async function createCompra(
  empresaId: number,
  periodoId: number,
  input: CompraInput,
): Promise<CompraDetalle> {
  const { data } = await api.post(base(empresaId, periodoId), input)
  return data.data as CompraDetalle
}

export async function updateCompra(
  empresaId: number,
  periodoId: number,
  id: number,
  input: CompraInput,
): Promise<CompraDetalle> {
  const { data } = await api.put(`${base(empresaId, periodoId)}/${id}`, input)
  return data.data as CompraDetalle
}

export async function deleteCompra(empresaId: number, periodoId: number, id: number): Promise<void> {
  await api.delete(`${base(empresaId, periodoId)}/${id}`)
}

/** Mueve el comprobante a otro período de la misma empresa. */
export async function moverCompra(
  empresaId: number,
  periodoId: number,
  id: number,
  periodoDestinoId: number,
): Promise<void> {
  await api.post(`${base(empresaId, periodoId)}/${id}/mover`, {
    periodo_destino_id: periodoDestinoId,
  })
}
