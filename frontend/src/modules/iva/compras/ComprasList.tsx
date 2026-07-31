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
  CFormSelect,
  CTable,
  CTableHead,
  CTableRow,
  CTableHeaderCell,
  CTableBody,
  CTableDataCell,
  CFormCheck,
  CSpinner,
  CAlert,
  CDropdown,
  CDropdownToggle,
  CDropdownMenu,
  CDropdownItem,
  CDropdownDivider,
} from '@coreui/react'
import {
  listCompras,
  listComprasPendientes,
  deleteCompra,
  moverCompra,
  createCompra,
  updateCompra,
  type Compra,
  type ComprasFiltros,
  type CompraInput,
} from '../../../api/compras'
import CompraFormModal from './CompraFormModal'
import MoverComprobanteModal from '../MoverComprobanteModal'
import { COMPRA_PRESETS, type CompraPreset } from './compraPresets'

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
  const [nombre, setNombre] = useState('')
  const [numero, setNumero] = useState('')
  const [orden, setOrden] = useState('')
  const [filtros, setFiltros] = useState<ComprasFiltros>({})
  const [page, setPage] = useState(1)

  const [modalOpen, setModalOpen] = useState(false)
  const [editingId, setEditingId] = useState<number | null>(null)
  const [preset, setPreset] = useState<CompraPreset | null>(null)
  const [formError, setFormError] = useState<string | null>(null)

  const [selected, setSelected] = useState<Set<number>>(new Set())
  const [moviendo, setMoviendo] = useState<Compra | null>(null)
  const [moverError, setMoverError] = useState<string | null>(null)

  const [verPendientes, setVerPendientes] = useState(false)

  const queryKey = ['compras', eId, pId, filtros, page]
  const { data, isLoading, isError, isFetching } = useQuery({
    queryKey,
    queryFn: () => listCompras(eId, pId, filtros, page, PER_PAGE),
    placeholderData: keepPreviousData,
  })

  const { data: pendientes } = useQuery({
    queryKey: ['compras-pendientes', eId, pId],
    queryFn: () => listComprasPendientes(eId, pId),
  })

  const invalidateCompras = () => {
    qc.invalidateQueries({ queryKey: ['compras', eId, pId] })
    qc.invalidateQueries({ queryKey: ['compras-pendientes', eId, pId] })
  }

  const deleteM = useMutation({
    mutationFn: (id: number) => deleteCompra(eId, pId, id),
    onSuccess: invalidateCompras,
  })

  const bulkDeleteM = useMutation({
    mutationFn: (ids: number[]) => Promise.all(ids.map((id) => deleteCompra(eId, pId, id))),
    onSuccess: () => {
      setSelected(new Set())
      invalidateCompras()
    },
  })

  const moverM = useMutation({
    mutationFn: ({ id, destino }: { id: number; destino: number }) => moverCompra(eId, pId, id, destino),
    onSuccess: () => {
      setMoviendo(null)
      setMoverError(null)
      invalidateCompras()
    },
    onError: (e) => setMoverError(apiError(e)),
  })

  const closeModal = () => {
    setModalOpen(false)
    setEditingId(null)
    setPreset(null)
    setFormError(null)
  }

  const saveM = useMutation({
    mutationFn: (v: CompraInput) =>
      editingId == null ? createCompra(eId, pId, v) : updateCompra(eId, pId, editingId, v),
    onSuccess: () => {
      invalidateCompras()
      closeModal()
    },
    onError: (e) => setFormError(apiError(e)),
  })

  const nuevaCompra = (p: CompraPreset | null = null) => {
    setEditingId(null)
    setPreset(p)
    setFormError(null)
    setModalOpen(true)
  }

  const editarCompra = (id: number) => {
    setEditingId(id)
    setPreset(null)
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
      nombre: nombre || undefined,
      numero: numero || undefined,
      orden: orden || undefined,
    })
  }

  const verTodos = () => {
    setFechaDesde('')
    setFechaHasta('')
    setCuit('')
    setNombre('')
    setNumero('')
    setOrden('')
    setPage(1)
    setFiltros({})
  }

  const onDelete = (c: Compra) => {
    if (window.confirm(`¿Eliminar el comprobante ${comprobante(c)}?`)) {
      deleteM.mutate(c.id)
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
  const allChecked = rows.length > 0 && rows.every((c) => selected.has(c.id))
  const toggleAll = () =>
    setSelected((prev) => {
      if (rows.every((c) => prev.has(c.id))) {
        const next = new Set(prev)
        rows.forEach((c) => next.delete(c.id))
        return next
      }
      const next = new Set(prev)
      rows.forEach((c) => next.add(c.id))
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
  const ultimaFecha = rows.reduce((max, c) => (c.fecha && c.fecha > max ? c.fecha : max), '')

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
            <CDropdown variant="btn-group">
              <CButton color="primary" size="sm" onClick={() => nuevaCompra()}>
                Nueva compra
              </CButton>
              <CDropdownToggle color="primary" size="sm" split title="Comprobantes manuales" />
              <CDropdownMenu>
                <CDropdownItem disabled className="small text-body-secondary">
                  Comprobante manual (preset)
                </CDropdownItem>
                <CDropdownDivider />
                {COMPRA_PRESETS.map((p) => (
                  <CDropdownItem key={p.id} role="button" onClick={() => nuevaCompra(p)}>
                    {p.label}
                  </CDropdownItem>
                ))}
              </CDropdownMenu>
            </CDropdown>
          </div>
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
              <CFormLabel className="small mb-1">Proveedor</CFormLabel>
              <CFormInput size="sm" placeholder="Nombre…" value={nombre} onChange={(e) => setNombre(e.target.value)} />
            </div>
            <div className="col-auto">
              <CFormLabel className="small mb-1">Número</CFormLabel>
              <CFormInput size="sm" style={{ width: 110 }} value={numero} onChange={(e) => setNumero(e.target.value)} />
            </div>
            <div className="col-auto">
              <CFormLabel className="small mb-1">Orden</CFormLabel>
              <CFormSelect size="sm" value={orden} onChange={(e) => setOrden(e.target.value)}>
                <option value="">Fecha ↑</option>
                <option value="fecha_desc">Fecha ↓</option>
                <option value="nombre">Proveedor</option>
                <option value="numero">Número</option>
                <option value="total">Total ↑</option>
                <option value="total_desc">Total ↓</option>
              </CFormSelect>
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

          {!!pendientes?.length && (
            <CAlert color="warning" className="d-flex justify-content-between align-items-center">
              <span>
                {pendientes.length} comprobante{pendientes.length === 1 ? '' : 's'} sin proveedor
                identificado del padrón en este período.
              </span>
              <CButton color="warning" variant="outline" size="sm" onClick={() => setVerPendientes((v) => !v)}>
                {verPendientes ? 'Ocultar pendientes' : 'Ver pendientes'}
              </CButton>
            </CAlert>
          )}

          {verPendientes && !!pendientes?.length && (
            <CTable small hover responsive align="middle" className="mb-3">
              <CTableHead>
                <CTableRow>
                  <CTableHeaderCell>Fecha</CTableHeaderCell>
                  <CTableHeaderCell>Comprobante</CTableHeaderCell>
                  <CTableHeaderCell>Proveedor</CTableHeaderCell>
                  <CTableHeaderCell>CUIT</CTableHeaderCell>
                  <CTableHeaderCell className="text-end">Total</CTableHeaderCell>
                  <CTableHeaderCell className="text-end">Acción</CTableHeaderCell>
                </CTableRow>
              </CTableHead>
              <CTableBody>
                {pendientes.map((p) => (
                  <CTableRow key={p.id}>
                    <CTableDataCell>{p.fecha ?? '—'}</CTableDataCell>
                    <CTableDataCell>
                      {(p.letra ?? '') +
                        ' ' +
                        (p.punto_venta ? String(p.punto_venta).padStart(4, '0') : '----') +
                        '-' +
                        (p.numero ? String(p.numero).padStart(8, '0') : '--------')}
                    </CTableDataCell>
                    <CTableDataCell>{p.proveedor_nombre ?? '—'}</CTableDataCell>
                    <CTableDataCell>{p.cuit ?? '—'}</CTableDataCell>
                    <CTableDataCell className="text-end">{formatImporte(p.total)}</CTableDataCell>
                    <CTableDataCell className="text-end">
                      <CButton color="warning" variant="outline" size="sm" onClick={() => editarCompra(p.id)}>
                        Asignar proveedor
                      </CButton>
                    </CTableDataCell>
                  </CTableRow>
                ))}
              </CTableBody>
            </CTable>
          )}

          {isLoading && <CSpinner />}
          {isError && <CAlert color="danger">No se pudieron cargar las compras.</CAlert>}
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
                    <CTableHeaderCell>Proveedor</CTableHeaderCell>
                    <CTableHeaderCell>CUIT</CTableHeaderCell>
                    <CTableHeaderCell className="text-end">Neto</CTableHeaderCell>
                    <CTableHeaderCell className="text-end">IVA</CTableHeaderCell>
                    <CTableHeaderCell className="text-end">Total</CTableHeaderCell>
                    <CTableHeaderCell className="text-end">Acciones</CTableHeaderCell>
                  </CTableRow>
                </CTableHead>
                <CTableBody>
                  {data.results.map((c) => (
                    <CTableRow key={c.id}>
                      <CTableDataCell>
                        <CFormCheck
                          checked={selected.has(c.id)}
                          onChange={() => toggleOne(c.id)}
                          aria-label={`Seleccionar ${comprobante(c)}`}
                        />
                      </CTableDataCell>
                      <CTableDataCell>{c.fecha ?? '—'}</CTableDataCell>
                      <CTableDataCell>{comprobante(c)}</CTableDataCell>
                      <CTableDataCell>{c.proveedor_nombre ?? '—'}</CTableDataCell>
                      <CTableDataCell>{c.cuit ?? '—'}</CTableDataCell>
                      <CTableDataCell className="text-end">{formatImporte(c.neto_gravado)}</CTableDataCell>
                      <CTableDataCell className="text-end">{formatImporte(c.iva)}</CTableDataCell>
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
                        <CButton
                          color="info"
                          variant="outline"
                          size="sm"
                          className="me-2"
                          onClick={() => {
                            setMoverError(null)
                            setMoviendo(c)
                          }}
                        >
                          Mover
                        </CButton>
                        <CButton color="danger" variant="outline" size="sm" onClick={() => onDelete(c)}>
                          Eliminar
                        </CButton>
                      </CTableDataCell>
                    </CTableRow>
                  ))}
                  {data.results.length === 0 && (
                    <CTableRow>
                      <CTableDataCell colSpan={9} className="text-center text-body-secondary py-4">
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
        ultimaFecha={ultimaFecha}
        preset={preset}
        onClose={closeModal}
        onSubmit={(v) => saveM.mutate(v)}
      />

      <MoverComprobanteModal
        visible={moviendo != null}
        empresaId={eId}
        periodoActualId={pId}
        detalle={moviendo ? `la compra ${comprobante(moviendo)}` : undefined}
        moving={moverM.isPending}
        errorMsg={moverError}
        onClose={() => setMoviendo(null)}
        onMove={(destino) => moviendo && moverM.mutate({ id: moviendo.id, destino })}
      />
    </>
  )
}
