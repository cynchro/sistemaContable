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
  CSpinner,
  CAlert,
} from '@coreui/react'
import {
  listCompras,
  deleteCompra,
  createCompra,
  updateCompra,
  type Compra,
  type ComprasFiltros,
  type CompraInput,
} from '../../../api/compras'
import CompraFormModal from './CompraFormModal'

const PER_PAGE = 50

function formatImporte(v: string): string {
  const n = Number(v)
  return Number.isFinite(n) ? n.toLocaleString('es-AR', { style: 'currency', currency: 'ARS' }) : v
}

function comprobante(c: Compra): string {
  const pv = c.punto_venta != null ? String(c.punto_venta).padStart(4, '0') : '----'
  const nro = c.numero != null ? String(c.numero).padStart(8, '0') : '--------'
  return `${c.letra ?? ''} ${pv}-${nro}`.trim()
}

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

export default function ComprasList() {
  const { empresaId, periodoId } = useParams()
  const eId = Number(empresaId)
  const pId = Number(periodoId)
  const qc = useQueryClient()

  const [fechaDesde, setFechaDesde] = useState('')
  const [fechaHasta, setFechaHasta] = useState('')
  const [cuit, setCuit] = useState('')
  const [filtros, setFiltros] = useState<ComprasFiltros>({})
  const [page, setPage] = useState(1)

  const [modalOpen, setModalOpen] = useState(false)
  const [editingId, setEditingId] = useState<number | null>(null)
  const [formError, setFormError] = useState<string | null>(null)

  const queryKey = ['compras', eId, pId, filtros, page]
  const { data, isLoading, isError, isFetching } = useQuery({
    queryKey,
    queryFn: () => listCompras(eId, pId, filtros, page, PER_PAGE),
    placeholderData: keepPreviousData,
  })

  const deleteM = useMutation({
    mutationFn: (id: number) => deleteCompra(eId, pId, id),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['compras', eId, pId] }),
  })

  const closeModal = () => {
    setModalOpen(false)
    setEditingId(null)
    setFormError(null)
  }

  const saveM = useMutation({
    mutationFn: (v: CompraInput) =>
      editingId == null ? createCompra(eId, pId, v) : updateCompra(eId, pId, editingId, v),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['compras', eId, pId] })
      closeModal()
    },
    onError: (e) => setFormError(apiError(e)),
  })

  const nuevaCompra = () => {
    setEditingId(null)
    setFormError(null)
    setModalOpen(true)
  }

  const editarCompra = (id: number) => {
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
      cuit: cuit || undefined,
    })
  }

  const verTodos = () => {
    setFechaDesde('')
    setFechaHasta('')
    setCuit('')
    setPage(1)
    setFiltros({})
  }

  const onDelete = (c: Compra) => {
    if (window.confirm(`¿Eliminar el comprobante ${comprobante(c)}?`)) {
      deleteM.mutate(c.id)
    }
  }

  const total = data?.total ?? 0
  const totalPaginas = Math.max(1, Math.ceil(total / PER_PAGE))

  return (
    <>
      <CCard>
        <CCardHeader className="d-flex justify-content-between align-items-center">
          <div>
            <Link to="/empresas" className="text-decoration-none small">
              ← Empresas
            </Link>
            <strong className="ms-2">Compras</strong>
            {isFetching && <CSpinner size="sm" className="ms-2" />}
          </div>
          <CButton color="primary" size="sm" onClick={nuevaCompra}>
            Nueva compra
          </CButton>
        </CCardHeader>
        <CCardBody>
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
              <CFormLabel className="small mb-1">CUIT</CFormLabel>
              <CFormInput size="sm" style={{ width: 160 }} value={cuit} onChange={(e) => setCuit(e.target.value)} />
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
          {isError && <CAlert color="danger">No se pudieron cargar las compras.</CAlert>}
          {data && (
            <>
              <CTable hover responsive align="middle" className="mb-0">
                <CTableHead>
                  <CTableRow>
                    <CTableHeaderCell>Fecha</CTableHeaderCell>
                    <CTableHeaderCell>Comprobante</CTableHeaderCell>
                    <CTableHeaderCell>Proveedor</CTableHeaderCell>
                    <CTableHeaderCell>CUIT</CTableHeaderCell>
                    <CTableHeaderCell className="text-end">Total</CTableHeaderCell>
                    <CTableHeaderCell className="text-end">Acciones</CTableHeaderCell>
                  </CTableRow>
                </CTableHead>
                <CTableBody>
                  {data.results.map((c) => (
                    <CTableRow key={c.id}>
                      <CTableDataCell>{c.fecha ?? '—'}</CTableDataCell>
                      <CTableDataCell>{comprobante(c)}</CTableDataCell>
                      <CTableDataCell>{c.proveedor_nombre ?? '—'}</CTableDataCell>
                      <CTableDataCell>{c.cuit ?? '—'}</CTableDataCell>
                      <CTableDataCell className="text-end">{formatImporte(c.total)}</CTableDataCell>
                      <CTableDataCell className="text-end">
                        <CButton
                          color="secondary"
                          variant="outline"
                          size="sm"
                          className="me-2"
                          onClick={() => editarCompra(c.id)}
                        >
                          Editar
                        </CButton>
                        <CButton color="danger" variant="outline" size="sm" onClick={() => onDelete(c)}>
                          Eliminar
                        </CButton>
                      </CTableDataCell>
                    </CTableRow>
                  ))}
                  {data.results.length === 0 && (
                    <CTableRow>
                      <CTableDataCell colSpan={6} className="text-center text-body-secondary py-4">
                        Sin comprobantes de compra en este período.
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

      <CompraFormModal
        visible={modalOpen}
        empresaId={eId}
        periodoId={pId}
        compraId={editingId}
        saving={saveM.isPending}
        errorMsg={formError}
        onClose={closeModal}
        onSubmit={(v) => saveM.mutate(v)}
      />
    </>
  )
}
