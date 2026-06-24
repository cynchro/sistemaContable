import axios from 'axios'

/**
 * Cliente HTTP contra la API del sistema contable. La base se configura por env
 * (VITE_API_URL); el token JWT se adjunta como Bearer en cada request. El backend
 * resuelve el tenant y valida el RBAC a partir del token.
 */
const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL ?? 'http://localhost:8080',
})

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('access_token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

export default api
