import api from './client'

/**
 * Catálogo de conceptos del Padrón Único (documento "Satélite Visual IVA" §5.2/§5.4), por
 * tenant — no depende de ninguna empresa. Nivel intermedio entre la regla de imputación (que
 * referencia un concepto, global) y la cuenta real, que cada empresa mapea aparte (ver
 * `api/imputacionContable.ts`).
 */
export interface Concepto {
  id: number
  nombre: string
}

export interface ConceptoInput {
  nombre: string
}

export async function listConceptos(): Promise<Concepto[]> {
  const { data } = await api.get('/iva/conceptos')
  return data.data as Concepto[]
}

export async function createConcepto(input: ConceptoInput): Promise<Concepto> {
  const { data } = await api.post('/iva/conceptos', input)
  return data.data as Concepto
}

export async function updateConcepto(id: number, input: ConceptoInput): Promise<Concepto> {
  const { data } = await api.put(`/iva/conceptos/${id}`, input)
  return data.data as Concepto
}

export async function deleteConcepto(id: number): Promise<void> {
  await api.delete(`/iva/conceptos/${id}`)
}
