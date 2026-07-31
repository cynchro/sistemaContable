import api from './client'

/**
 * Motor de alertas estadísticas v1 (documento "Satélite Visual IVA" §7): compara el total del
 * último período de cada empresa contra el promedio de sus períodos anteriores, tanto para
 * compras como para ventas. Calculado al vuelo (sin cron ni columnas persistidas). Umbral de
 * desvío (30%) es un supuesto v1 a confirmar con el contador — ver preguntas.md.
 */
export interface Alerta {
  empresa_id: number
  empresa_nombre: string
  tipo: 'ventas' | 'compras'
  periodo_id: number
  periodo_nombre: string
  actual: string
  promedio: string
  desvio_pct: string
  alerta: boolean
}

export async function listAlertas(): Promise<Alerta[]> {
  const { data } = await api.get('/alertas')
  return data.data as Alerta[]
}
