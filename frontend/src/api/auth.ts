import api from './client'

export interface Tokens {
  access_token: string
  refresh_token: string
}

/** POST /auth/login → { access_token, refresh_token }. Credenciales: email + clave. */
export async function login(usuario: string, clave: string): Promise<Tokens> {
  const { data } = await api.post('/auth/login', { usuario, clave })
  return data.data as Tokens
}

/** POST /auth/logout (revoca el token del lado del servidor). */
export async function logout(): Promise<void> {
  await api.post('/auth/logout')
}

/** POST /auth/refresh → nuevos tokens a partir del refresh_token. */
export async function refresh(refreshToken: string): Promise<Tokens> {
  const { data } = await api.post('/auth/refresh', { refresh_token: refreshToken })
  return data.data as Tokens
}
