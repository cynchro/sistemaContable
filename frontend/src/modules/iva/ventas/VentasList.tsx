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
  CBadge,
  CSpinner,
  CAlert,
} from '@coreui/react'
import { listVentas, deleteVenta, type Venta, type VentasFiltros } from '../../../api/ventas'

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

  const queryKey = ['ventas', eId, pId, filtros, page]
  const { data, isLoading, isError, isFetching } = useQuery({
    queryKey,
    queryFn: () => listVentas(eId, pId, filtros, page, PER_PAGE),
    placeholderData: keepPreviousData,
  })

  const deleteM = useMutation({
    mutationFn: (id: number) => deleteVenta(eId, pId, id),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['ventas', eId, pId] }),
  })

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

  const total = data?.total ?? 0
  const totalPaginas = Math.max(1, Math.ceil(total / PER_PAGE))

  return (
    <CCard>
      <CCardHeader className="d-flex justify-content-between align-items-center">
        <div>
          <Link to="/empresas" className="text-decoration-none small">
            ← Empresas
          </Link>
          <strong className="ms-2">Ventas</strong>
          {isFetching && <CSpinner size="sm" className="ms-2" />}
        </div>
        <CButton color="primary" size="sm" disabled title="Ficha de carga en construcción">
          Nueva venta
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
            <CTable hover responsive align="middle" className="mb-0">
              <CTableHead>
                <CTableRow>
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
                    <CTableDataCell>{v.fecha ?? '—'}</CTableDataCell>
                    <CTableDataCell>{comprobante(v)}</CTableDataCell>
                    <CTableDataCell>{v.cliente_nombre ?? '—'}</CTableDataCell>
                    <CTableDataCell>{v.cuit ?? '—'}</CTableDataCell>
                    <CTableDataCell className="text-end">{formatImporte(v.total)}</CTableDataCell>
                    <CTableDataCell>
                      {v.cae ? <CBadge color="success">CAE</CBadge> : <CBadge color="secondary">—</CBadge>}
                    </CTableDataCell>
                    <CTableDataCell className="text-end">
                      <CButton color="danger" variant="outline" size="sm" onClick={() => onDelete(v)}>
                        Eliminar
                      </CButton>
                    </CTableDataCell>
                  </CTableRow>
                ))}
                {data.results.length === 0 && (
                  <CTableRow>
                    <CTableDataCell colSpan={7} className="text-center text-body-secondary py-4">
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
  )
}
