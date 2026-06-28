import api from './client'

export interface Actividad {
  id: number
  codigo: string
  descripcion: string | null
}

export interface PuntoVentaActividad {
  id: number
  punto_venta: string
  actividad_id: number
  actividad_codigo: string
  actividad_descripcion: string | null
}

const base = (empresaId: number) => `/empresas/${empresaId}/actividades`

export async function listActividades(empresaId: number): Promise<Actividad[]> {
  const { data } = await api.get(base(empresaId))
  return data.data as Actividad[]
}
export async function createActividad(empresaId: number, codigo: string, descripcion: string): Promise<Actividad> {
  const { data } = await api.post(base(empresaId), { codigo, descripcion })
  return data.data as Actividad
}
export async function deleteActividad(empresaId: number, id: number): Promise<void> {
  await api.delete(`${base(empresaId)}/${id}`)
}

export async function listPuntosVenta(empresaId: number): Promise<PuntoVentaActividad[]> {
  const { data } = await api.get(`${base(empresaId)}-punto-venta`)
  return data.data as PuntoVentaActividad[]
}
export async function setPuntoVenta(empresaId: number, puntoVenta: string, actividadId: number): Promise<void> {
  await api.post(`${base(empresaId)}-punto-venta`, { punto_venta: puntoVenta, actividad_id: actividadId })
}
export async function deletePuntoVenta(empresaId: number, id: number): Promise<void> {
  await api.delete(`${base(empresaId)}-punto-venta/${id}`)
}

/* ---- Estrategia por alícuota (construcción) ---- */
export interface AlicuotaActividad {
  id: number
  alicuota: string
  actividad_id: number
  actividad_codigo: string
  actividad_descripcion: string | null
}
export async function listAlicuotas(empresaId: number): Promise<AlicuotaActividad[]> {
  const { data } = await api.get(`${base(empresaId)}-alicuota`)
  return data.data as AlicuotaActividad[]
}
export async function setAlicuota(empresaId: number, alicuota: string, actividadId: number): Promise<void> {
  await api.post(`${base(empresaId)}-alicuota`, { alicuota, actividad_id: actividadId })
}
export async function deleteAlicuota(empresaId: number, id: number): Promise<void> {
  await api.delete(`${base(empresaId)}-alicuota/${id}`)
}

/* ---- Estrategia por receptor (cliente / CUIT) ---- */
export interface ReceptorActividad {
  id: number
  cliente_id: number
  cliente_nombre: string | null
  cliente_cuit: string | null
  actividad_id: number
  actividad_codigo: string
  actividad_descripcion: string | null
}
export async function listReceptores(empresaId: number): Promise<ReceptorActividad[]> {
  const { data } = await api.get(`${base(empresaId)}-receptor`)
  return data.data as ReceptorActividad[]
}
export async function setReceptor(empresaId: number, clienteId: number, actividadId: number): Promise<void> {
  await api.post(`${base(empresaId)}-receptor`, { cliente_id: clienteId, actividad_id: actividadId })
}
export async function deleteReceptor(empresaId: number, id: number): Promise<void> {
  await api.delete(`${base(empresaId)}-receptor/${id}`)
}
