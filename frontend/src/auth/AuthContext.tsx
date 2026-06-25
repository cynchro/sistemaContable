import { createContext, useContext, useState, useCallback, type ReactNode } from 'react'
import { decodeJwt, isExpired, type JwtPayload } from './jwt'
import * as authApi from '../api/auth'

interface AuthState {
  user: JwtPayload | null
  login: (usuario: string, clave: string) => Promise<void>
  logout: () => Promise<void>
}

const AuthContext = createContext<AuthState | undefined>(undefined)

/** Restaura la sesión desde localStorage al iniciar (descarta tokens vencidos). */
function loadUser(): JwtPayload | null {
  const token = localStorage.getItem('access_token')
  if (!token) return null
  const payload = decodeJwt(token)
  if (!payload || isExpired(payload)) {
    localStorage.removeItem('access_token')
    localStorage.removeItem('refresh_token')
    return null
  }
  return payload
}

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<JwtPayload | null>(loadUser)

  const login = useCallback(async (usuario: string, clave: string) => {
    const tokens = await authApi.login(usuario, clave)
    localStorage.setItem('access_token', tokens.access_token)
    localStorage.setItem('refresh_token', tokens.refresh_token)
    setUser(decodeJwt(tokens.access_token))
  }, [])

  const logout = useCallback(async () => {
    try {
      await authApi.logout()
    } catch {
      /* best-effort: aunque el backend falle, limpiamos la sesión local */
    }
    localStorage.removeItem('access_token')
    localStorage.removeItem('refresh_token')
    setUser(null)
  }, [])

  return <AuthContext.Provider value={{ user, login, logout }}>{children}</AuthContext.Provider>
}

// eslint-disable-next-line react-refresh/only-export-components
export function useAuth(): AuthState {
  const ctx = useContext(AuthContext)
  if (!ctx) {
    throw new Error('useAuth debe usarse dentro de <AuthProvider>')
  }
  return ctx
}
