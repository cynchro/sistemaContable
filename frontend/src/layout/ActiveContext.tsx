import { createContext, useContext, useState, useCallback, useRef, type ReactNode } from 'react'
import type { Empresa } from '../api/empresas'
import type { Periodo } from '../api/periodos'
import { liberarEmpresa } from '../api/empresaLock'

/**
 * Contexto de "empresa activa + período activo" — el modelo mental del Visual IVA
 * de escritorio trasladado a la web. El header deja elegirlos y las pantallas de
 * IVA (ventas, compras, libro IVA…) operan sobre ese contexto. Los IDs se persisten
 * en localStorage para sobrevivir recargas; los objetos se rehidratan en el selector.
 *
 * `lockEstado`/`lockOcupadoPor` reflejan la "ocupación" de la empresa activa (WhatsApp con el
 * cliente, 11/08/2026) — quién la está usando y si este usuario entró en modo observador
 * (admin, sin poder modificar). El pedido/liberación real del lock vive en `ActiveSelector.tsx`
 * (ahí es donde hay margen para avisar si quedó bloqueado); acá solo se libera automáticamente
 * la empresa ANTERIOR al cambiar de activa, para no dejar locks colgados por descuido.
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
  lockEstado: 'propio' | 'observador' | null
  lockOcupadoPor: string | null
  setLockEstado: (estado: 'propio' | 'observador' | null, ocupadoPor?: string | null) => void
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
  const [lockEstado, setLockEstadoState] = useState<'propio' | 'observador' | null>(null)
  const [lockOcupadoPor, setLockOcupadoPor] = useState<string | null>(null)
  const previousEmpresaId = useRef<number | null>(activeEmpresaId)

  const setEmpresa = useCallback((e: Empresa | null) => {
    const anterior = previousEmpresaId.current
    if (anterior != null && anterior !== (e?.id ?? null)) {
      liberarEmpresa(anterior).catch(() => {
        // Best-effort: si falla (red caída, sesión vencida), el lock igual expira solo por
        // timeout del lado del backend — no bloquea el cambio de empresa en el frontend.
      })
    }
    previousEmpresaId.current = e?.id ?? null

    setEmpresaState(e)
    setActiveEmpresaId(e?.id ?? null)
    setLockEstadoState(null)
    setLockOcupadoPor(null)
    if (e) localStorage.setItem(EMP_KEY, String(e.id))
    else localStorage.removeItem(EMP_KEY)
  }, [])

  const setPeriodo = useCallback((p: Periodo | null) => {
    setPeriodoState(p)
    setActivePeriodoId(p?.id ?? null)
    if (p) localStorage.setItem(PER_KEY, String(p.id))
    else localStorage.removeItem(PER_KEY)
  }, [])

  const setLockEstado = useCallback((estado: 'propio' | 'observador' | null, ocupadoPor: string | null = null) => {
    setLockEstadoState(estado)
    setLockOcupadoPor(ocupadoPor)
  }, [])

  return (
    <ActiveContext.Provider
      value={{
        empresa,
        periodo,
        setEmpresa,
        setPeriodo,
        activeEmpresaId,
        activePeriodoId,
        lockEstado,
        lockOcupadoPor,
        setLockEstado,
      }}
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
