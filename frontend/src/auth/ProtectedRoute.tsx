import { Navigate, Outlet } from 'react-router-dom'
import { useAuth } from './AuthContext'

/** Deja pasar sólo si hay sesión; si no, redirige al login. */
export default function ProtectedRoute() {
  const { user } = useAuth()
  return user ? <Outlet /> : <Navigate to="/login" replace />
}
