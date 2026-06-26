import api from './client'

/* ============================ TAREAS (por tenant) ============================ */

export const TAREA_ESTADOS = ['pendiente', 'en_progreso', 'en_revision', 'completada', 'cancelada'] as const
export const TAREA_PRIORIDADES = ['baja', 'media', 'alta', 'urgente'] as const

export interface Tarea {
  id: number
  titulo: string
  descripcion: string | null
  estado: string
  prioridad: string | null
  tipo_id: number | null
  empresa_id: number | null
  fecha_limite: string | null
  assigned_to: number | null
}

export interface TareaInput {
  titulo: string
  tipo_id?: number | null
  empresa_id?: number | null
  descripcion?: string | null
  prioridad?: string | null
  fecha_limite?: string | null
}

export interface TareaTipo {
  id: number
  nombre: string
  categoria: string | null
  prioridad_sugerida: string | null
}

export interface Comentario {
  id: number
  comentario: string
  usuario_id: number | null
  created_at: string
}

export interface HistorialEstado {
  id: number
  estado_anterior: string | null
  estado_nuevo: string
  observacion: string | null
  created_at: string
}

export async function listTareas(): Promise<Tarea[]> {
  const { data } = await api.get('/tareas')
  return data.data as Tarea[]
}
export async function createTarea(input: TareaInput): Promise<Tarea> {
  const { data } = await api.post('/tareas', input)
  return data.data as Tarea
}
export async function deleteTarea(id: number): Promise<void> {
  await api.delete(`/tareas/${id}`)
}
export async function cambiarEstadoTarea(id: number, estado: string, observacion?: string): Promise<unknown> {
  const { data } = await api.put(`/tareas/${id}/estado`, { estado, observacion: observacion ?? null })
  return data.data
}
export async function getComentarios(id: number): Promise<Comentario[]> {
  const { data } = await api.get(`/tareas/${id}/comentarios`)
  return data.data as Comentario[]
}
export async function addComentario(id: number, comentario: string): Promise<unknown> {
  const { data } = await api.post(`/tareas/${id}/comentarios`, { comentario })
  return data.data
}
export async function getHistorial(id: number): Promise<HistorialEstado[]> {
  const { data } = await api.get(`/tareas/${id}/historial`)
  return data.data as HistorialEstado[]
}
export async function listTareaTipos(): Promise<TareaTipo[]> {
  const { data } = await api.get('/tareas/tipos')
  return data.data as TareaTipo[]
}

/* ======================= VENCIMIENTOS (por empresa) ======================= */

export const VENCIMIENTO_ESTADOS = [
  'creado',
  'documentacion_recibida',
  'documentacion_cargada',
  'en_control',
  'presentado',
] as const

export interface Vencimiento {
  id: number
  titulo: string
  agencia: string | null
  jurisdiccion: string | null
  descripcion: string | null
  fecha_vencimiento: string | null
  estado: string
}

export interface VencimientoInput {
  titulo: string
  agencia?: string | null
  jurisdiccion?: string | null
  descripcion?: string | null
  fecha_vencimiento?: string | null
}

const vBase = (empresaId: number) => `/empresas/${empresaId}/vencimientos`

export async function listVencimientos(empresaId: number): Promise<Vencimiento[]> {
  const { data } = await api.get(vBase(empresaId))
  return data.data as Vencimiento[]
}
export async function createVencimiento(empresaId: number, input: VencimientoInput): Promise<Vencimiento> {
  const { data } = await api.post(vBase(empresaId), input)
  return data.data as Vencimiento
}
export async function deleteVencimiento(empresaId: number, id: number): Promise<void> {
  await api.delete(`${vBase(empresaId)}/${id}`)
}
export async function cambiarEstadoVencimiento(empresaId: number, id: number, estado: string): Promise<unknown> {
  const { data } = await api.put(`${vBase(empresaId)}/${id}/estado`, { estado })
  return data.data
}

/* ========================= HONORARIOS (por empresa) ========================= */

export interface Servicio {
  id: number
  codigo: string | null
  descripcion: string
  uc: string | null
}

export interface FactorComplejidad {
  id: number
  nivel: string
  factor: string
  label: string | null
}

export interface HonorarioLinea {
  servicio_id: number
  complejidad?: string | null
  cantidad?: string | number | null
}

export interface HonorarioInput {
  valor_uc: string
  fecha?: string | null
  descripcion?: string | null
  lineas: HonorarioLinea[]
}

export interface Honorario {
  id: number
  fecha: string | null
  descripcion: string | null
  valor_uc: string
  total: string
}

const hBase = (empresaId: number) => `/empresas/${empresaId}/honorarios`

export async function listServicios(): Promise<Servicio[]> {
  const { data } = await api.get('/servicios')
  return data.data as Servicio[]
}
export async function listFactores(): Promise<FactorComplejidad[]> {
  const { data } = await api.get('/factores-complejidad')
  return data.data as FactorComplejidad[]
}
export async function listHonorarios(empresaId: number): Promise<Honorario[]> {
  const { data } = await api.get(hBase(empresaId))
  return data.data as Honorario[]
}
export async function createHonorario(empresaId: number, input: HonorarioInput): Promise<Honorario> {
  const { data } = await api.post(hBase(empresaId), input)
  return data.data as Honorario
}
export async function deleteHonorario(empresaId: number, id: number): Promise<void> {
  await api.delete(`${hBase(empresaId)}/${id}`)
}
