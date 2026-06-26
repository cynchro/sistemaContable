export function apiError(e: unknown, fallback: string): string {
  const err = e as { response?: { status?: number; data?: { message?: string; errors?: Record<string, string[]> } } }
  if (err.response?.status === 403) return 'No tenés permisos de administrador para esta sección.'
  const data = err.response?.data
  if (data?.errors) {
    const first = Object.values(data.errors)[0]
    if (first?.[0]) return first[0]
  }
  return data?.message ?? fallback
}
