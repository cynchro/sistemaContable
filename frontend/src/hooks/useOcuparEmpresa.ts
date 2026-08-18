import { useState } from 'react'
import type { Empresa } from '../api/empresas'
import { ocuparEmpresa } from '../api/empresaLock'
import { useActive } from '../layout/ActiveContext'

function apiError(e: unknown): string {
  const err = e as { response?: { data?: { message?: string } } }
  return err.response?.data?.message ?? 'No se pudo ocupar la empresa.'
}

/**
 * Pide el lock de una empresa y la activa en el `ActiveContext` — mismo flujo que
 * `ActiveSelector.tsx` (header), extraído acá para reusarlo desde la navegación guiada por
 * contribuyente (informe del cliente 10/08/2026, pedido 5b: "elijo la empresa → se trabaja
 * directamente sobre eso"). Devuelve si pudo activarla, para que el llamador decida si navega.
 */
export function useOcuparEmpresa() {
  const { setEmpresa, setLockEstado } = useActive()
  const [error, setError] = useState<string | null>(null)
  const [isPending, setIsPending] = useState(false)

  const ocupar = async (e: Empresa): Promise<boolean> => {
    setError(null)
    setIsPending(true)
    try {
      const estado = await ocuparEmpresa(e.id)
      setEmpresa(e)
      setLockEstado(estado.modo, estado.ocupado_por)
      return true
    } catch (err) {
      setError(apiError(err))
      return false
    } finally {
      setIsPending(false)
    }
  }

  return { ocupar, error, isPending, reset: () => setError(null) }
}
