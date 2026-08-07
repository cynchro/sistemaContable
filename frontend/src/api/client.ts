import axios from 'axios'

/**
 * Cliente HTTP contra la API del sistema contable. La base se configura por env
 * (VITE_API_URL); el token JWT se adjunta como Bearer en cada request. El backend
 * resuelve el tenant y valida el RBAC a partir del token.
 */
const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL || `http://${window.location.hostname}:8077`,
})

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('access_token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

// Sesión expirada/inválida: limpiamos y mandamos al login (salvo en endpoints de
// auth, donde el error lo maneja la propia pantalla de login).
api.interceptors.response.use(
  (response) => response,
  (error) => {
    const status = error.response?.status
    const url: string = error.config?.url ?? ''
    if (status === 401 && !url.includes('/auth/')) {
      localStorage.removeItem('access_token')
      localStorage.removeItem('refresh_token')
      if (window.location.pathname !== '/login') {
        window.location.href = '/login'
      }
    }
    return Promise.reject(error)
  },
)

export default api
