/** Mensaje de error de la API (422 con detalle de validación o 409 de conflicto). */
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
