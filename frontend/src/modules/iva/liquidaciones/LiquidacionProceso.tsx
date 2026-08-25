import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { useQuery, useMutation, useQueryClient, type UseQueryResult } from '@tanstack/react-query'
import {
  CAlert,
  CBadge,
  CButton,
  CSpinner,
  CModal,
  CModalHeader,
  CModalTitle,
  CModalBody,
  CModalFooter,
} from '@coreui/react'
import {
  crearLiquidacion,
  getLiquidacion,
  listLiquidaciones,
  ESTADOS_ABIERTOS,
  type Direccion,
  type LibroLiquidacion,
  type EstadoLiquidacion,
  type Liquidacion,
} from '../../../api/liquidaciones'

export const COLOR_ESTADO: Record<EstadoLiquidacion, string> = {
  pendiente: 'secondary',
  tomada: 'info',
  en_curso: 'info',
  terminada: 'success',
  error: 'danger',
}

export const LABEL_ESTADO: Record<EstadoLiquidacion, string> = {
  pendiente: 'Pendiente',
  tomada: 'Tomada por el bot',
  en_curso: 'En curso',
  terminada: 'Terminada',
  error: 'Error',
}

export function EstadoBadge({ estado }: { estado: EstadoLiquidacion }) {
  return <CBadge color={COLOR_ESTADO[estado]}>{LABEL_ESTADO[estado]}</CBadge>
}

export const LABEL_LIBRO: Record<string, string> = { ventas: 'Ventas', compras: 'Compras' }
const LABEL_DIRECCION: Record<string, string> = { traer: 'Traer', subir: 'Subir' }

/** Links directos a Ventas/Compras del período de la liquidación — "ambos" muestra los dos. */
export function VerComprobantesLinks({
  empresaId,
  periodoId,
  libro,
}: {
  empresaId: number
  periodoId: number
  libro: LibroLiquidacion
}) {
  const links: Array<[string, string]> = []
  if (libro === 'ventas' || libro === 'ambos') links.push(['ventas', 'Ver ventas'])
  if (libro === 'compras' || libro === 'ambos') links.push(['compras', 'Ver compras'])

  return (
    <span className="d-flex gap-2">
      {links.map(([slug, label]) => (
        <Link key={slug} to={`/empresas/${empresaId}/periodos/${periodoId}/${slug}`} className="small">
          {label}
        </Link>
      ))}
    </span>
  )
}

/** Mismo patrón que `errorReporte` de ReporteMayorPage.tsx: prioriza el mensaje de validación
 * del backend (422 con `errors`, 409 con `message`) en vez de un genérico. */
export function errorLiquidacion(e: unknown): string {
  const err = e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
  const first = err.response?.data?.errors ? Object.values(err.response.data.errors)[0]?.[0] : undefined
  return first ?? err.response?.data?.message ?? 'No se pudo pedir la liquidación.'
}

interface ResultadoDireccionJson {
  arca?: { agregados: number; erroneos: number; registros: number; ignorados?: number; modificados?: number }
  ecosistema?: { total: number; creados: number; errores: Array<{ fila: number; error: string }> }
  descartados?: number
}

/** Una línea legible por cada combinación libro+dirección presente en el resultado — el detalle
 * crudo (JSON) queda solo en la base (columna `resultado`), nunca se le muestra al usuario. */
export function resumenLineas(resultado: string): string[] {
  let json: Record<string, Record<string, ResultadoDireccionJson>>
  try {
    json = JSON.parse(resultado)
  } catch {
    return []
  }

  const lineas: string[] = []
  for (const [libro, porDireccion] of Object.entries(json)) {
    for (const [direccion, r] of Object.entries(porDireccion)) {
      const etiqueta = `${LABEL_LIBRO[libro] ?? libro} — ${LABEL_DIRECCION[direccion] ?? direccion}`
      if (r.arca) {
        // "subir" reenvía SIEMPRE el libro completo del período (no solo lo nuevo) — ARCA
        // reconoce lo que ya estaba y lo cuenta acá como "ignorado" en vez de "agregado", para
        // que quede claro que se procesaron todos, no solo los comprobantes nuevos.
        const detalle = [`${r.arca.agregados} agregado(s) nuevo(s)`]
        if (r.arca.ignorados) detalle.push(`${r.arca.ignorados} ya estaban (sin cambios)`)
        if (r.arca.modificados) detalle.push(`${r.arca.modificados} modificado(s)`)
        if (r.arca.erroneos > 0) detalle.push(`${r.arca.erroneos} con error`)
        const sufijo = direccion === 'traer' ? 'en el borrador' : 'procesados'
        lineas.push(`${etiqueta}: ${detalle.join(', ')} en ARCA (de ${r.arca.registros} comprobante(s) ${sufijo}).`)
      }
      if (r.ecosistema) {
        const yaExistian = r.ecosistema.errores.length
        lineas.push(
          `  → ecosistema: ${r.ecosistema.creados} nuevo(s) creado(s)` +
            (yaExistian > 0 ? `, ${yaExistian} ya existían (no se duplicaron)` : '') +
            '.',
        )
      }
      if (r.descartados) {
        lineas.push(`  ⚠ ${r.descartados} comprobante(s) no se pudieron mapear (tipo no soportado).`)
      }
    }
  }
  return lineas
}

/** Mensaje de error: el worker reporta `{mensaje, parcial}` (ver ReportarEstadoLiquidacionRequest). */
export function mensajeError(resultado: string): string {
  try {
    const json = JSON.parse(resultado) as { mensaje?: string }
    return json.mensaje ?? 'La liquidación terminó con error.'
  } catch {
    return 'La liquidación terminó con error.'
  }
}

/**
 * Estado + polling compartido entre la pestaña "Liquidar IVA" (trae) y el botón "Procesar" de
 * Compras/Ventas (sube) — ambos disparan la misma cola de liquidaciones y el backend solo permite
 * una liquidación abierta por empresa+período a la vez (sin importar libro/dirección), así que el
 * "retomar al montar" busca cualquier liquidación abierta, no solo la de este llamador.
 */
export function useLiquidacionProceso(eId: number, pId: number) {
  const qc = useQueryClient()
  const [enCursoId, setEnCursoId] = useState<number | null>(null)
  const [modalMostradoId, setModalMostradoId] = useState<number | null>(null)
  const [modalVisible, setModalVisible] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const { data: historial } = useQuery({
    queryKey: ['liquidaciones', eId, pId],
    queryFn: () => listLiquidaciones(eId, pId),
  })

  useEffect(() => {
    if (enCursoId != null) return
    const abierta = historial?.results.find((l) => ESTADOS_ABIERTOS.includes(l.estado))
    if (abierta) setEnCursoId(abierta.id)
  }, [historial, enCursoId])

  const actual = useQuery({
    queryKey: ['liquidacion', eId, pId, enCursoId],
    queryFn: () => getLiquidacion(eId, pId, enCursoId as number),
    enabled: enCursoId != null,
    refetchInterval: (query) => {
      const estado = query.state.data?.estado
      return estado && ESTADOS_ABIERTOS.includes(estado) ? 5000 : false
    },
  })

  useEffect(() => {
    const liq = actual.data
    if (!liq || ESTADOS_ABIERTOS.includes(liq.estado)) return

    qc.invalidateQueries({ queryKey: ['liquidaciones', eId, pId] })

    // Recién terminó (o falló) — informar con el modal, una sola vez por liquidación.
    if (modalMostradoId !== liq.id) {
      setModalMostradoId(liq.id)
      setModalVisible(true)
    }
  }, [actual.data, qc, eId, pId, modalMostradoId])

  const crear = useMutation({
    mutationFn: (vars: { direccion: Direccion; libro: LibroLiquidacion }) =>
      crearLiquidacion(eId, pId, vars.direccion, vars.libro),
    onSuccess: (liq) => {
      setError(null)
      setEnCursoId(liq.id)
      qc.invalidateQueries({ queryKey: ['liquidaciones', eId, pId] })
    },
    onError: (e: unknown) => setError(errorLiquidacion(e)),
  })

  const enCurso = enCursoId != null && actual.data != null && ESTADOS_ABIERTOS.includes(actual.data.estado)

  return { historial, enCursoId, setEnCursoId, actual, crear, enCurso, error, modalVisible, setModalVisible }
}

/** CAlert de estado en curso/terminado + CModal de resultado — reusa `resumenLineas`/`mensajeError`
 * en vez de mostrar el JSON crudo (ese queda solo en la base). */
export function LiquidacionEstado({
  enCursoId,
  actual,
  enCurso,
  modalVisible,
  setModalVisible,
  setEnCursoId,
}: {
  enCursoId: number | null
  actual: UseQueryResult<Liquidacion>
  enCurso: boolean
  modalVisible: boolean
  setModalVisible: (v: boolean) => void
  setEnCursoId: (v: number | null) => void
}) {
  if (enCursoId == null || !actual.data) return null

  return (
    <>
      <CAlert color={COLOR_ESTADO[actual.data.estado]} className="d-flex align-items-center gap-2">
        <span>
          Liquidación #{enCursoId}: <EstadoBadge estado={actual.data.estado} />
        </span>
        {enCurso && <CSpinner size="sm" />}
        {!enCurso && (
          <>
            {actual.data.estado === 'terminada' && (
              <VerComprobantesLinks
                empresaId={actual.data.empresa_id}
                periodoId={actual.data.periodo_id}
                libro={actual.data.libro}
              />
            )}
            <CButton size="sm" color="secondary" variant="ghost" className="ms-auto" onClick={() => setModalVisible(true)}>
              Ver resultado
            </CButton>
            <CButton size="sm" color="secondary" variant="ghost" onClick={() => setEnCursoId(null)}>
              Cerrar
            </CButton>
          </>
        )}
      </CAlert>

      <CModal visible={modalVisible} onClose={() => setModalVisible(false)} alignment="center">
        <CModalHeader>
          <CModalTitle>
            {actual.data.estado === 'error' ? 'La liquidación terminó con error' : 'Liquidación terminada'}
          </CModalTitle>
        </CModalHeader>
        <CModalBody>
          {actual.data.estado === 'error' && actual.data.resultado && (
            <CAlert color="danger" className="mb-0">
              {mensajeError(actual.data.resultado)}
            </CAlert>
          )}
          {actual.data.estado === 'terminada' && actual.data.resultado && (
            <>
              {resumenLineas(actual.data.resultado).map((linea, i) => (
                <div key={i} className="small">
                  {linea}
                </div>
              ))}
              <div className="mt-3">
                <VerComprobantesLinks
                  empresaId={actual.data.empresa_id}
                  periodoId={actual.data.periodo_id}
                  libro={actual.data.libro}
                />
              </div>
            </>
          )}
        </CModalBody>
        <CModalFooter>
          <CButton color="secondary" onClick={() => setModalVisible(false)}>
            Cerrar
          </CButton>
        </CModalFooter>
      </CModal>
    </>
  )
}
