import { useState } from 'react'
import { useParams, Link } from 'react-router-dom'
import { useQuery, useMutation, useQueryClient, keepPreviousData } from '@tanstack/react-query'
import {
  CCard,
  CCardHeader,
  CCardBody,
  CButton,
  CForm,
  CFormInput,
  CFormLabel,
  CTable,
  CTableHead,
  CTableRow,
  CTableHeaderCell,
  CTableBody,
  CTableDataCell,
  CFormCheck,
  CBadge,
  CSpinner,
  CAlert,
} from '@coreui/react'
import {
  listVentas,
  deleteVenta,
  moverVenta,
  createVenta,
  updateVenta,
  type Venta,
  type VentasFiltros,
  type VentaInput,
} from '../../../api/ventas'
import { emitirCae } from '../../../api/afip'
import VentaFormModal from './VentaFormModal'
import MoverComprobanteModal from '../MoverComprobanteModal'

/** Mensaje de error de la API (422 con detalle de validación o 409 de conflicto). */
function apiError(e: unknown): string {
  const err = e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
  const data = err.response?.data
  if (data?.errors) {
    const first = Object.values(data.errors)[0]
    if (first?.[0]) return first[0]
  }
  return data?.message ?? 'No se pudo guardar el comprobante.'
}

const PER_PAGE = 50

function formatImporte(v: string): string {
  const n = Number(v)
  return Number.isFinite(n) ? n.toLocaleString('es-AR', { style: 'currency', currency: 'ARS' }) : v
}

function comprobante(v: Venta): string {
  const pv = v.punto_venta != null ? String(v.punto_venta).padStart(4, '0') : '----'
  const nro = v.numero != null ? String(v.numero).padStart(8, '0') : '--------'
  return `${v.letra ?? ''} ${pv}-${nro}`.trim()
}

export default function VentasList() {
  const { empresaId, periodoId } = useParams()
  const eId = Number(empresaId)
  const pId = Number(periodoId)
  const qc = useQueryClient()

  const [fechaDesde, setFechaDesde] = useState('')
  const [fechaHasta, setFechaHasta] = useState('')
  const [letra, setLetra] = useState('')
  const [filtros, setFiltros] = useState<VentasFiltros>({})
  const [page, setPage] = useState(1)

  const [modalOpen, setModalOpen] = useState(false)
  const [editingId, setEditingId] = useState<number | null>(null)
  const [formError, setFormError] = useState<string | null>(null)

  const [selected, setSelected] = useState<Set<number>>(new Set())
  const [moviendo, setMoviendo] = useState<Venta | null>(null)
  const [moverError, setMoverError] = useState<string | null>(null)

  const queryKey = ['ventas', eId, pId, filtros, page]
  const { data, isLoading, isError, isFetching } = useQuery({
    queryKey,
    queryFn: () => listVentas(eId, pId, filtros, page, PER_PAGE),
    placeholderData: keepPreviousData,
  })

  const invalidateVentas = () => qc.invalidateQueries({ queryKey: ['ventas', eId, pId] })

  const deleteM = useMutation({
    mutationFn: (id: number) => deleteVenta(eId, pId, id),
    onSuccess: invalidateVentas,
  })

  const bulkDeleteM = useMutation({
    mutationFn: (ids: number[]) => Promise.all(ids.map((id) => deleteVenta(eId, pId, id))),
    onSuccess: () => {
      setSelected(new Set())
      invalidateVentas()
    },
  })

  const moverM = useMutation({
    mutationFn: ({ id, destino }: { id: number; destino: number }) => moverVenta(eId, pId, id, destino),
    onSuccess: () => {
      setMoviendo(null)
      setMoverError(null)
      invalidateVentas()
    },
    onError: (e) => setMoverError(apiError(e)),
  })

  const [caeMsg, setCaeMsg] = useState<{ ok: boolean; text: string } | null>(null)
  const caeM = useMutation({
    mutationFn: (id: number) => emitirCae(eId, pId, id),
    onSuccess: (r) => {
      qc.invalidateQueries({ queryKey: ['ventas', eId, pId] })
      setCaeMsg({ ok: true, text: `CAE ${r.cae} obtenido (vence ${r.cae_vto ?? '—'}).` })
    },
    onError: (e) => setCaeMsg({ ok: false, text: apiError(e) }),
  })

  const closeModal = () => {
    setModalOpen(false)
    setEditingId(null)
    setFormError(null)
  }

  const saveM = useMutation({
    mutationFn: (v: VentaInput) =>
      editingId == null ? createVenta(eId, pId, v) : updateVenta(eId, pId, editingId, v),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['ventas', eId, pId] })
      closeModal()
    },
    onError: (e) => setFormError(apiError(e)),
  })

  const nuevaVenta = () => {
    setEditingId(null)
    setFormError(null)
    setModalOpen(true)
  }

  const editarVenta = (id: number) => {
    setEditingId(id)
    setFormError(null)
    setModalOpen(true)
  }

  const aplicarFiltros = (e: React.FormEvent) => {
    e.preventDefault()
    setPage(1)
    setFiltros({
      fecha_desde: fechaDesde || undefined,
      fecha_hasta: fechaHasta || undefined,
      letra: letra || undefined,
    })
  }

  const verTodos = () => {
    setFechaDesde('')
    setFechaHasta('')
    setLetra('')
    setPage(1)
    setFiltros({})
  }

  const onDelete = (v: Venta) => {
    if (window.confirm(`¿Eliminar el comprobante ${comprobante(v)}?`)) {
      deleteM.mutate(v.id)
    }
  }

  const rows = data?.results ?? []
  const toggleOne = (id: number) =>
    setSelected((prev) => {
      const next = new Set(prev)
      if (next.has(id)) next.delete(id)
      else next.add(id)
      return next
    })
  const allChecked = rows.length > 0 && rows.every((v) => selected.has(v.id))
  const toggleAll = () =>
    setSelected((prev) => {
      if (rows.every((v) => prev.has(v.id))) {
        const next = new Set(prev)
        rows.forEach((v) => next.delete(v.id))
        return next
      }
      const next = new Set(prev)
      rows.forEach((v) => next.add(v.id))
      return next
    })
  const onBulkDelete = () => {
    const ids = [...selected]
    if (ids.length && window.confirm(`¿Eliminar ${ids.length} comprobante(s) seleccionado(s)?`)) {
      bulkDeleteM.mutate(ids)
    }
  }

  const total = data?.total ?? 0
  const totalPaginas = Math.max(1, Math.ceil(total / PER_PAGE))
  // Última fecha cargada (para pre-cargar en una venta nueva; YYYY-MM-DD compara lexicográfico).
  const ultimaFecha = rows.reduce((max, v) => (v.fecha && v.fecha > max ? v.fecha : max), '')

  return (
    <>
    <CCard>
      <CCardHeader className="d-flex justify-content-between align-items-center">
        <div>
          <Link to="/empresas" className="text-decoration-none small">
            ← Empresas
          </Link>
          <strong className="ms-2">Ventas</strong>
          {isFetching && <CSpinner size="sm" className="ms-2" />}
        </div>
        <div className="d-flex gap-2">
          {selected.size > 0 && (
            <CButton
              color="danger"
              variant="outline"
              size="sm"
              disabled={bulkDeleteM.isPending}
              onClick={onBulkDelete}
            >
              Eliminar seleccionados ({selected.size})
            </CButton>
          )}
          <CButton color="primary" size="sm" onClick={nuevaVenta}>
            Nueva venta
          </CButton>
        </div>
      </CCardHeader>
      <CCardBody>
        {caeMsg && (
          <CAlert color={caeMsg.ok ? 'success' : 'danger'} dismissible onClose={() => setCaeMsg(null)}>
            {caeMsg.text}
          </CAlert>
        )}
        <CForm className="row g-2 align-items-end mb-3" onSubmit={aplicarFiltros}>
          <div className="col-auto">
            <CFormLabel className="small mb-1">Desde</CFormLabel>
            <CFormInput type="date" size="sm" value={fechaDesde} onChange={(e) => setFechaDesde(e.target.value)} />
          </div>
          <div className="col-auto">
            <CFormLabel className="small mb-1">Hasta</CFormLabel>
            <CFormInput type="date" size="sm" value={fechaHasta} onChange={(e) => setFechaHasta(e.target.value)} />
          </div>
          <div className="col-auto">
            <CFormLabel className="small mb-1">Letra</CFormLabel>
            <CFormInput
              size="sm"
              style={{ width: 70 }}
              maxLength={1}
              value={letra}
              onChange={(e) => setLetra(e.target.value.toUpperCase())}
            />
          </div>
          <div className="col-auto">
            <CButton type="submit" color="primary" variant="outline" size="sm" className="me-2">
              Filtrar
            </CButton>
            <CButton type="button" color="secondary" variant="outline" size="sm" onClick={verTodos}>
              Ver todos
            </CButton>
          </div>
        </CForm>

        {isLoading && <CSpinner />}
        {isError && <CAlert color="danger">No se pudieron cargar las ventas.</CAlert>}
        {data && (
          <>
            <CTable hover responsive align="middle" className="mb-0 ledger">
              <CTableHead>
                <CTableRow>
                  <CTableHeaderCell style={{ width: 32 }}>
                    <CFormCheck checked={allChecked} onChange={toggleAll} aria-label="Seleccionar todos" />
                  </CTableHeaderCell>
                  <CTableHeaderCell>Fecha</CTableHeaderCell>
                  <CTableHeaderCell>Comprobante</CTableHeaderCell>
                  <CTableHeaderCell>Cliente</CTableHeaderCell>
                  <CTableHeaderCell>CUIT</CTableHeaderCell>
                  <CTableHeaderCell className="text-end">Total</CTableHeaderCell>
                  <CTableHeaderCell>CAE</CTableHeaderCell>
                  <CTableHeaderCell className="text-end">Acciones</CTableHeaderCell>
                </CTableRow>
              </CTableHead>
              <CTableBody>
                {data.results.map((v) => (
                  <CTableRow key={v.id}>
                    <CTableDataCell>
                      <CFormCheck
                        checked={selected.has(v.id)}
                        onChange={() => toggleOne(v.id)}
                        aria-label={`Seleccionar ${comprobante(v)}`}
                      />
                    </CTableDataCell>
                    <CTableDataCell>{v.fecha ?? '—'}</CTableDataCell>
                    <CTableDataCell>{comprobante(v)}</CTableDataCell>
                    <CTableDataCell>{v.cliente_nombre ?? '—'}</CTableDataCell>
                    <CTableDataCell>{v.cuit ?? '—'}</CTableDataCell>
                    <CTableDataCell className="text-end">{formatImporte(v.total)}</CTableDataCell>
                    <CTableDataCell>
                      {v.cae ? <CBadge color="success">CAE</CBadge> : <CBadge color="secondary">—</CBadge>}
                    </CTableDataCell>
                    <CTableDataCell className="text-end">
                      {!v.cae && (
                        <CButton
                          color="success"
                          variant="outline"
                          size="sm"
                          className="me-2"
                          disabled={caeM.isPending}
                          onClick={() => {
                            setCaeMsg(null)
                            caeM.mutate(v.id)
                          }}
                          title="Solicitar CAE a ARCA (WSFEv1)"
                        >
                          Emitir CAE
                        </CButton>
                      )}
                      <CButton
                        color="secondary"
                        variant="outline"
                        size="sm"
                        className="me-2"
                        onClick={() => editarVenta(v.id)}
                      >
                        Editar
                      </CButton>
                      <CButton
                        color="info"
                        variant="outline"
                        size="sm"
                        className="me-2"
                        onClick={() => {
                          setMoverError(null)
                          setMoviendo(v)
                        }}
                      >
                        Mover
                      </CButton>
                      <CButton color="danger" variant="outline" size="sm" onClick={() => onDelete(v)}>
                        Eliminar
                      </CButton>
                    </CTableDataCell>
                  </CTableRow>
                ))}
                {data.results.length === 0 && (
                  <CTableRow>
                    <CTableDataCell colSpan={8} className="text-center text-body-secondary py-4">
                      Sin comprobantes de venta en este período.
                    </CTableDataCell>
                  </CTableRow>
                )}
              </CTableBody>
            </CTable>

            <div className="d-flex justify-content-between align-items-center mt-3">
              <small className="text-body-secondary">
                {total} comprobante{total === 1 ? '' : 's'} · página {page} de {totalPaginas}
              </small>
              <div>
                <CButton
                  color="secondary"
                  variant="outline"
                  size="sm"
                  className="me-2"
                  disabled={page <= 1}
                  onClick={() => setPage((p) => Math.max(1, p - 1))}
                >
                  Anterior
                </CButton>
                <CButton
                  color="secondary"
                  variant="outline"
                  size="sm"
                  disabled={page >= totalPaginas}
                  onClick={() => setPage((p) => p + 1)}
                >
                  Siguiente
                </CButton>
              </div>
            </div>
          </>
        )}
      </CCardBody>
    </CCard>

    <VentaFormModal
      visible={modalOpen}
      empresaId={eId}
      periodoId={pId}
      ventaId={editingId}
      saving={saveM.isPending}
      errorMsg={formError}
      ultimaFecha={ultimaFecha}
      onClose={closeModal}
      onSubmit={(v) => saveM.mutate(v)}
    />

    <MoverComprobanteModal
      visible={moviendo != null}
      empresaId={eId}
      periodoActualId={pId}
      detalle={moviendo ? `la venta ${comprobante(moviendo)}` : undefined}
      moving={moverM.isPending}
      errorMsg={moverError}
      onClose={() => setMoviendo(null)}
      onMove={(destino) => moviendo && moverM.mutate({ id: moviendo.id, destino })}
    />
    </>
  )
}
