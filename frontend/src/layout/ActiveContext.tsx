import { createContext, useContext, useState, useCallback, type ReactNode } from 'react'
import type { Empresa } from '../api/empresas'
import type { Periodo } from '../api/periodos'

/**
 * Contexto de "empresa activa + período activo" — el modelo mental del Visual IVA
 * de escritorio trasladado a la web. El header deja elegirlos y las pantallas de
 * IVA (ventas, compras, libro IVA…) operan sobre ese contexto. Los IDs se persisten
 * en localStorage para sobrevivir recargas; los objetos se rehidratan en el selector.
 */
interface ActiveState {
  empresa: Empresa | null
  periodo: Periodo | null
  /** Selecciona objetos (persiste su id). No aplica reglas: el llamador decide. */
  setEmpresa: (e: Empresa | null) => void
  setPeriodo: (p: Periodo | null) => void
  /** IDs persistidos, disponibles antes de rehidratar los objetos. */
  activeEmpresaId: number | null
  activePeriodoId: number | null
}

const EMP_KEY = 'active_empresa_id'
const PER_KEY = 'active_periodo_id'

const ActiveContext = createContext<ActiveState | undefined>(undefined)

function readId(key: string): number | null {
  const v = localStorage.getItem(key)
  return v ? Number(v) : null
}

export function ActiveProvider({ children }: { children: ReactNode }) {
  const [empresa, setEmpresaState] = useState<Empresa | null>(null)
  const [periodo, setPeriodoState] = useState<Periodo | null>(null)
  const [activeEmpresaId, setActiveEmpresaId] = useState<number | null>(() => readId(EMP_KEY))
  const [activePeriodoId, setActivePeriodoId] = useState<number | null>(() => readId(PER_KEY))

  const setEmpresa = useCallback((e: Empresa | null) => {
    setEmpresaState(e)
    setActiveEmpresaId(e?.id ?? null)
    if (e) localStorage.setItem(EMP_KEY, String(e.id))
    else localStorage.removeItem(EMP_KEY)
  }, [])

  const setPeriodo = useCallback((p: Periodo | null) => {
    setPeriodoState(p)
    setActivePeriodoId(p?.id ?? null)
    if (p) localStorage.setItem(PER_KEY, String(p.id))
    else localStorage.removeItem(PER_KEY)
  }, [])

  return (
    <ActiveContext.Provider
      value={{ empresa, periodo, setEmpresa, setPeriodo, activeEmpresaId, activePeriodoId }}
    >
      {children}
    </ActiveContext.Provider>
  )
}

// eslint-disable-next-line react-refresh/only-export-components
export function useActive(): ActiveState {
  const ctx = useContext(ActiveContext)
  if (!ctx) {
    throw new Error('useActive debe usarse dentro de <ActiveProvider>')
  }
  return ctx
}
