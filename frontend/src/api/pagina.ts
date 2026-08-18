/** Página estándar del backend (`App\Helpers\PaginatorHelper`). */
export interface Pagina<T> {
  total: number
  pagina: number
  cantidad_por_pagina: number
  results: T[]
}
