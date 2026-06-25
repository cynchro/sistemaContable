export interface JwtPayload {
  sub: number
  rol: number
  tenant_id: string
  exp: number
  iat: number
}

/** Decodifica el payload de un JWT (sin verificar la firma; eso lo hace el backend). */
export function decodeJwt(token: string): JwtPayload | null {
  try {
    const payload = token.split('.')[1]
    const json = atob(payload.replace(/-/g, '+').replace(/_/g, '/'))
    return JSON.parse(json) as JwtPayload
  } catch {
    return null
  }
}

/** ¿El token está vencido (con margen de 10s)? */
export function isExpired(payload: JwtPayload): boolean {
  return payload.exp * 1000 < Date.now() + 10_000
}
