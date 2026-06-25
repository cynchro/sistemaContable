import { createContext, useContext, useState, type ReactNode } from 'react'

interface UiState {
  sidebarShow: boolean
  setSidebarShow: (show: boolean) => void
  sidebarUnfoldable: boolean
  setSidebarUnfoldable: (v: boolean) => void
}

const UiContext = createContext<UiState | undefined>(undefined)

export function UiProvider({ children }: { children: ReactNode }) {
  const [sidebarShow, setSidebarShow] = useState(true)
  const [sidebarUnfoldable, setSidebarUnfoldable] = useState(false)
  return (
    <UiContext.Provider value={{ sidebarShow, setSidebarShow, sidebarUnfoldable, setSidebarUnfoldable }}>
      {children}
    </UiContext.Provider>
  )
}

// eslint-disable-next-line react-refresh/only-export-components
export function useUi(): UiState {
  const ctx = useContext(UiContext)
  if (!ctx) {
    throw new Error('useUi debe usarse dentro de <UiProvider>')
  }
  return ctx
}
