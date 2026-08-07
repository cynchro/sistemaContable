import { createContext, useContext, useState, type ReactNode } from 'react'
import { getToursEnabled, setToursEnabled, resetSeenTours } from './tourStorage'

interface TourState {
  /** Si está apagado, ningún recorrido arranca solo (el botón "Ver recorrido" de cada pantalla sigue andando). */
  enabled: boolean
  setEnabled: (v: boolean) => void
  /** Olvida qué recorridos ya se vieron, para que vuelvan a arrancar solos la próxima vez. */
  resetSeen: () => void
}

const TourContext = createContext<TourState | undefined>(undefined)

export function TourProvider({ children }: { children: ReactNode }) {
  const [enabled, setEnabledState] = useState(getToursEnabled)

  const setEnabled = (v: boolean) => {
    setToursEnabled(v)
    setEnabledState(v)
  }

  return (
    <TourContext.Provider value={{ enabled, setEnabled, resetSeen: resetSeenTours }}>
      {children}
    </TourContext.Provider>
  )
}

// eslint-disable-next-line react-refresh/only-export-components
export function useTourSettings(): TourState {
  const ctx = useContext(TourContext)
  if (!ctx) {
    throw new Error('useTourSettings debe usarse dentro de <TourProvider>')
  }
  return ctx
}
