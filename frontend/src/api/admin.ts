import api from './client'

/* ------------------------------ Roles ------------------------------ */

export interface Rol {
  id: number
  nombre: string
  parent_id: number | null
  estado: string | number // la API lo devuelve como 'activo'/'inactivo'
}

/** Permiso tal como lo devuelven las listas disponibles/asignadas de un rol. */
export interface PermisoRef {
  id_permiso: number
  key: string
}

export interface RolDetalle {
  rol: Rol
  permisosDisponibles: PermisoRef[]
  permisosAsignados: PermisoRef[]
}

export async function listRoles(): Promise<Rol[]> {
  const { data } = await api.get('/admin/roles')
  return data.data as Rol[]
}
export async function getRol(id: number): Promise<RolDetalle> {
  const { data } = await api.get(`/admin/roles/${id}`)
  return data.data as RolDetalle
}
export async function createRol(nombre: string, parentId?: number | null): Promise<{ id: number }> {
  const { data } = await api.post('/admin/roles', { nombre, parent_id: parentId ?? null })
  return data.data as { id: number }
}
export async function updateRol(id: number, nombre: string, estado: number, parentId?: number | null): Promise<unknown> {
  const { data } = await api.put(`/admin/roles/${id}`, { nombre, estado, parent_id: parentId ?? null })
  return data.data
}
export async function asignarPermisos(rolId: number, permisos: number[]): Promise<unknown> {
  const { data } = await api.post(`/admin/roles/${rolId}/assign`, { permisos })
  return data.data
}
export async function desasignarPermisos(rolId: number, permisos: number[]): Promise<unknown> {
  const { data } = await api.delete(`/admin/roles/${rolId}/assign`, { data: { permisos } })
  return data.data
}

/* ------------------------------ Permisos ------------------------------ */

export interface Permiso {
  id: number
  key: string
  descripcion: string | null
  estado: number
}

export async function listPermisos(): Promise<Permiso[]> {
  const { data } = await api.get('/admin/permisos')
  return data.data as Permiso[]
}
export async function createPermiso(key: string, descripcion: string): Promise<unknown> {
  const { data } = await api.post('/admin/permisos', { key, descripcion })
  return data.data
}

/* ------------------------------ Usuarios ------------------------------ */

export interface Usuario {
  id: number
  usuario: string
  rol: number | null
  desarrollador?: string | null
}

export async function listUsuarios(): Promise<Usuario[]> {
  const { data } = await api.get('/admin/users')
  return data.data as Usuario[]
}

/* -------------------------- Empresas asignadas -------------------------- */

/**
 * Asignación opcional de empresas a un usuario (WhatsApp con el cliente, 11/08/2026:
 * "me gustaría que de última él pueda ver sus empresas asignadas"). Un usuario sin
 * ninguna asignación sigue viendo todas las empresas del tenant.
 */
export interface EmpresaAsignada {
  id: number
  nombre: string
  cuit: string | null
}

export async function empresasDeUsuario(usuarioId: number): Promise<EmpresaAsignada[]> {
  const { data } = await api.get(`/admin/users/${usuarioId}/empresas`)
  return data.data as EmpresaAsignada[]
}

export async function asignarEmpresas(usuarioId: number, empresaIds: number[]): Promise<unknown> {
  const { data } = await api.put(`/admin/users/${usuarioId}/empresas`, { empresa_ids: empresaIds })
  return data.data
}
