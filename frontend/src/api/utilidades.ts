import api from './client'

/** Registro de la auditoría de operaciones del módulo IVA (escrituras 2xx). */
export interface AuditoriaLog {
  id: number
  user_id: number | null
  metodo: string
  uri: string
  params: string | null
  datos: string | null
  status: number
  created_at: string
}

export interface AuditoriaPagina {
  total: number
  cantidad_total: number
  cantidad_por_pagina: number
  pagina: number
  results: AuditoriaLog[]
}

export async function listAuditoria(page: number, perPage: number): Promise<AuditoriaPagina> {
  const { data } = await api.get('/iva/auditoria', { params: { page, per_page: perPage } })
  return data.data as AuditoriaPagina
}
