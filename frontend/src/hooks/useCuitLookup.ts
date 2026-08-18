import { useState } from 'react'
import { useMutation } from '@tanstack/react-query'

/**
 * Patrón compartido "botón junto al CUIT → consultar una fuente externa → autocompletar":
 * usado hoy contra AFIP (padrón) y SIGE (sistemaCuarto). Cada fuente se instancia con su
 * propio `fetchFn` (una llamada por hook, no un array dinámico — evita romper las reglas
 * de hooks de React si en el futuro se agregan más fuentes).
 */
export function useCuitLookup<T>(
  fetchFn: (cuit: string) => Promise<T>,
  opts?: { onSuccess?: (data: T) => void; notFoundMessage?: string },
) {
  const [error, setError] = useState<string | null>(null)

  const mutation = useMutation({
    mutationFn: fetchFn,
    onSuccess: (data) => {
      setError(null)
      opts?.onSuccess?.(data)
    },
    onError: (e) => {
      const err = e as { response?: { data?: { message?: string } } }
      setError(err.response?.data?.message ?? opts?.notFoundMessage ?? 'No se pudo consultar.')
    },
  })

  const buscar = (cuit: string) => {
    const digits = cuit.replace(/\D/g, '')
    if (digits.length !== 11) {
      setError('Ingresá un CUIT de 11 dígitos para buscar.')
      return
    }
    setError(null)
    mutation.mutate(digits)
  }

  const reset = () => {
    setError(null)
    mutation.reset()
  }

  return {
    buscar,
    reset,
    isPending: mutation.isPending,
    isSuccess: mutation.isSuccess,
    data: mutation.data,
    error,
  }
}
