import { useState } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import {
  CCard,
  CCardHeader,
  CCardBody,
  CButton,
  CTable,
  CTableHead,
  CTableRow,
  CTableHeaderCell,
  CTableBody,
  CTableDataCell,
  CFormSelect,
  CBadge,
  CSpinner,
  CAlert,
  CModal,
  CModalHeader,
  CModalTitle,
  CModalBody,
  CModalFooter,
} from '@coreui/react'
import {
  getResumenAuditoria,
  getComprobanteAfip,
  type ResumenAuditoriaItem,
  type ComprobanteAfipDetalle,
} from '../../../api/auditoriaAfip'
import { listPeriodos } from '../../../api/periodos'
import { buildVentaPreset } from './afipPreset'

function apiError(e: unknown): string {
  const err = e as { response?: { data?: { message?: string } } }
  return err.response?.data?.message ?? 'No se pudo consultar ARCA.'
}

const money = (v: number | null) =>
  v == null ? '—' : v.toLocaleString('es-AR', { style: 'currency', currency: 'ARS' })

/** Máximo de números a listar en el selector de un hueco (evita un desplegable gigante). */
const MAX_NUMEROS = 200

interface Seleccion {
  fila: ResumenAuditoriaItem
  numero: string
}

/**
 * Auditoría de ventas contra ARCA (WSFEv1): compara, por punto de venta + tipo + letra
 * ya usados, el último número que ARCA reconoce como autorizado contra el último
 * cargado localmente. Se calcula en vivo (sin tabla propia) — botón "Actualizar" en
 * vez de disparar el llamado SOAP automáticamente al entrar.
 */
export default function AuditoriaAfipPage() {
  const { empresaId } = useParams()
  const eId = Number(empresaId)
  const navigate = useNavigate()

  const { data: periodos } = useQuery({ queryKey: ['periodos', eId], queryFn: () => listPeriodos(eId) })

  const {
    data: filas,
    isFetching,
    isError,
    error,
    refetch,
    isFetched,
  } = useQuery({
    queryKey: ['auditoria-afip', eId],
    queryFn: () => getResumenAuditoria(eId),
    enabled: false,
  })

  const [numeroPorFila, setNumeroPorFila] = useState<Record<string, string>>({})
  const [sel, setSel] = useState<Seleccion | null>(null)
  const [detalle, setDetalle] = useState<ComprobanteAfipDetalle | null>(null)
  const [cargandoDetalle, setCargandoDetalle] = useState(false)
  const [detalleError, setDetalleError] = useState<string | null>(null)
  const [avisoPeriodo, setAvisoPeriodo] = useState<string | null>(null)

  const claveFila = (f: ResumenAuditoriaItem) => `${f.punto_venta}|${f.tipo_comprobante_id}|${f.letra}`

  const consultar = async (fila: ResumenAuditoriaItem) => {
    const numero = numeroPorFila[claveFila(fila)] ?? String(fila.ultimo_local + 1)
    setSel({ fila, numero })
    setDetalle(null)
    setDetalleError(null)
    setAvisoPeriodo(null)
    setCargandoDetalle(true)
    try {
      const d = await getComprobanteAfip(eId, {
        tipoComprobanteId: fila.tipo_comprobante_id,
        puntoVenta: fila.punto_venta,
        letra: fila.letra,
        numero,
      })
      setDetalle(d)
    } catch (e) {
      setDetalleError(apiError(e))
    } finally {
      setCargandoDetalle(false)
    }
  }

  const cargarComoVenta = () => {
    if (!sel || !detalle) return
    setAvisoPeriodo(null)

    const periodo = (periodos ?? []).find(
      (p) => detalle.fecha && p.fecha_ini && p.fecha_fin && detalle.fecha >= p.fecha_ini && detalle.fecha <= p.fecha_fin,
    )
    if (!periodo) {
      setAvisoPeriodo(
        'No hay un período que incluya la fecha ' +
          `${detalle.fecha ?? 'del comprobante'} — creá o abrí el período correspondiente primero.`,
      )
      return
    }

    const preset = buildVentaPreset(sel.fila, sel.numero, detalle)
    navigate(`/empresas/${eId}/periodos/${periodo.id}/ventas`, { state: { preset } })
  }

  return (
    <CCard>
      <CCardHeader className="d-flex justify-content-between align-items-center">
        <strong>Auditoría de ventas vs. ARCA</strong>
        <CButton color="primary" size="sm" onClick={() => refetch()} disabled={isFetching}>
          {isFetching ? <CSpinner size="sm" /> : isFetched ? 'Actualizar' : 'Consultar ARCA'}
        </CButton>
      </CCardHeader>
      <CCardBody>
        <p className="text-body-secondary small">
          Compara, para cada punto de venta + tipo + letra que ya cargaste alguna vez, el último número que
          ARCA reconoce como autorizado contra el último que tenés cargado acá. Solo compara combinaciones ya
          usadas localmente — si nunca cargaste un tipo de comprobante, no aparece.
        </p>

        {isError && <CAlert color="danger">{apiError(error)}</CAlert>}

        {isFetched && !isError && (filas ?? []).length === 0 && (
          <CAlert color="secondary">Esta empresa todavía no tiene ventas cargadas para comparar.</CAlert>
        )}

        {isFetched && !isError && (filas ?? []).length > 0 && (
          <CTable hover responsive align="middle" className="ledger">
            <CTableHead>
              <CTableRow>
                <CTableHeaderCell>Punto de venta</CTableHeaderCell>
                <CTableHeaderCell>Tipo</CTableHeaderCell>
                <CTableHeaderCell>Letra</CTableHeaderCell>
                <CTableHeaderCell className="text-end">Último en ARCA</CTableHeaderCell>
                <CTableHeaderCell className="text-end">Último local</CTableHeaderCell>
                <CTableHeaderCell>Estado</CTableHeaderCell>
                <CTableHeaderCell>Consultar número</CTableHeaderCell>
              </CTableRow>
            </CTableHead>
            <CTableBody>
              {(filas ?? []).map((f) => {
                const clave = claveFila(f)
                const opciones: number[] = []
                for (let n = f.ultimo_local + 1; n <= f.ultimo_arca && opciones.length < MAX_NUMEROS; n++) {
                  opciones.push(n)
                }
                return (
                  <CTableRow key={clave} color={f.faltantes > 0 ? 'warning' : undefined}>
                    <CTableDataCell>{f.punto_venta}</CTableDataCell>
                    <CTableDataCell>{f.tipo_comprobante}</CTableDataCell>
                    <CTableDataCell>{f.letra}</CTableDataCell>
                    <CTableDataCell className="text-end">{f.ultimo_arca}</CTableDataCell>
                    <CTableDataCell className="text-end">{f.ultimo_local}</CTableDataCell>
                    <CTableDataCell>
                      {f.faltantes === 0 ? (
                        <CBadge color="success">Al día</CBadge>
                      ) : (
                        <CBadge color="warning">{f.faltantes} faltante(s)</CBadge>
                      )}
                    </CTableDataCell>
                    <CTableDataCell>
                      {f.faltantes > 0 ? (
                        <div className="d-flex gap-2">
                          <CFormSelect
                            size="sm"
                            style={{ width: '9rem' }}
                            value={numeroPorFila[clave] ?? String(f.ultimo_local + 1)}
                            onChange={(e) => setNumeroPorFila((s) => ({ ...s, [clave]: e.target.value }))}
                          >
                            {opciones.map((n) => (
                              <option key={n} value={n}>
                                {n}
                              </option>
                            ))}
                          </CFormSelect>
                          <CButton color="secondary" variant="outline" size="sm" onClick={() => consultar(f)}>
                            Consultar
                          </CButton>
                        </div>
                      ) : (
                        <span className="text-body-secondary">—</span>
                      )}
                    </CTableDataCell>
                  </CTableRow>
                )
              })}
            </CTableBody>
          </CTable>
        )}
      </CCardBody>

      <CModal visible={sel != null} onClose={() => setSel(null)} alignment="center">
        <CModalHeader>
          <CModalTitle>
            Comprobante {sel?.fila.letra} {sel?.fila.punto_venta}-{sel?.numero}
          </CModalTitle>
        </CModalHeader>
        <CModalBody>
          {cargandoDetalle && (
            <div className="text-center py-4">
              <CSpinner />
            </div>
          )}
          {detalleError && <CAlert color="danger">{detalleError}</CAlert>}
          {detalle && !detalle.encontrado && (
            <CAlert color="secondary">ARCA no tiene registrado este número (todavía no fue emitido).</CAlert>
          )}
          {detalle?.encontrado && (
            <>
              <dl className="row mb-0">
                <dt className="col-5">Fecha</dt>
                <dd className="col-7">{detalle.fecha ?? '—'}</dd>
                <dt className="col-5">Total</dt>
                <dd className="col-7">{money(detalle.total)}</dd>
                <dt className="col-5">Neto</dt>
                <dd className="col-7">{money(detalle.neto)}</dd>
                <dt className="col-5">CAE</dt>
                <dd className="col-7">{detalle.cae ?? '—'}</dd>
                <dt className="col-5">Vto. CAE</dt>
                <dd className="col-7">{detalle.cae_vto ?? '—'}</dd>
              </dl>
              {detalle.alicuotas.length > 0 && (
                <CTable small bordered className="mt-3">
                  <CTableHead>
                    <CTableRow>
                      <CTableHeaderCell>Alícuota</CTableHeaderCell>
                      <CTableHeaderCell className="text-end">Base</CTableHeaderCell>
                      <CTableHeaderCell className="text-end">IVA</CTableHeaderCell>
                    </CTableRow>
                  </CTableHead>
                  <CTableBody>
                    {detalle.alicuotas.map((a, i) => (
                      <CTableRow key={i}>
                        <CTableDataCell>{a.alicuota}%</CTableDataCell>
                        <CTableDataCell className="text-end">{money(a.base)}</CTableDataCell>
                        <CTableDataCell className="text-end">{money(a.importe)}</CTableDataCell>
                      </CTableRow>
                    ))}
                  </CTableBody>
                </CTable>
              )}
              {detalle.ya_cargado ? (
                <CAlert color="info" className="mt-3 mb-0 py-2 small">
                  Ya está cargado localmente (venta #{detalle.venta_id_local}).
                </CAlert>
              ) : (
                avisoPeriodo && (
                  <CAlert color="warning" className="mt-3 mb-0 py-2 small">
                    {avisoPeriodo}
                  </CAlert>
                )
              )}
            </>
          )}
        </CModalBody>
        <CModalFooter>
          <CButton color="secondary" variant="outline" onClick={() => setSel(null)}>
            Cerrar
          </CButton>
          {detalle?.encontrado && !detalle.ya_cargado && (
            <CButton color="primary" onClick={cargarComoVenta}>
              Cargar como venta
            </CButton>
          )}
        </CModalFooter>
      </CModal>
    </CCard>
  )
}
