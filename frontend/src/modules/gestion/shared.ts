export function apiError(e: unknown, fallback: string): string {
  const err = e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
  const data = err.response?.data
  if (data?.errors) {
    const first = Object.values(data.errors)[0]
    if (first?.[0]) return first[0]
  }
  return data?.message ?? fallback
}

export const money = (v?: string | null): string => {
  const n = Number(v)
  return Number.isFinite(n) && v != null && v !== ''
    ? n.toLocaleString('es-AR', { style: 'currency', currency: 'ARS' })
    : '—'
}

/** Etiqueta legible para un código snake_case (estado/prioridad). */
export const humaniza = (s?: string | null): string =>
  s ? s.replace(/_/g, ' ').replace(/^\w/, (c) => c.toUpperCase()) : '—'

export const COLOR_ESTADO: Record<string, string> = {
  pendiente: 'secondary',
  en_progreso: 'info',
  en_revision: 'warning',
  completada: 'success',
  cancelada: 'danger',
  creado: 'secondary',
  documentacion_recibida: 'info',
  documentacion_cargada: 'info',
  en_control: 'warning',
  presentado: 'success',
}

export const COLOR_PRIORIDAD: Record<string, string> = {
  baja: 'secondary',
  media: 'info',
  alta: 'warning',
  urgente: 'danger',
}
