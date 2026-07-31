import { useState, useEffect } from 'react'
import { useParams, Link } from 'react-router-dom'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
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
  CSpinner,
  CAlert,
  CForm,
  CFormInput,
  CFormSelect,
} from '@coreui/react'
import {
  listSujetos,
  createSujeto,
  updateSujeto,
  deleteSujeto,
  type Sujeto,
  type SujetoInput,
  type RecursoSujeto,
} from '../../../api/sujetos'
import SujetoFormModal from './SujetoFormModal'

const PER_PAGE = 20

export default function SujetosList({ recurso }: { recurso: RecursoSujeto }) {
  const esProveedor = recurso === 'proveedores'
  const titulo = esProveedor ? 'Proveedores' : 'Clientes'
  const singular = esProveedor ? 'proveedor' : 'cliente'

  const { empresaId } = useParams()
  const id = Number(empresaId)
  const qc = useQueryClient()
  const [modalOpen, setModalOpen] = useState(false)
  const [editing, setEditing] = useState<Sujeto | null>(null)
  const [q, setQ] = useState('')
  const [busqueda, setBusqueda] = useState('')
  const [orden, setOrden] = useState('')
  const [page, setPage] = useState(1)

  const { data, isLoading, isError } = useQuery({
    queryKey: [recurso, id, busqueda, orden],
    queryFn: () => listSujetos(recurso, id, { q: busqueda || undefined, orden: orden || undefined }),
  })

  // Paginación del lado del cliente (la lista se trae completa para el typeahead).
  useEffect(() => setPage(1), [busqueda, orden])
  const total = data?.length ?? 0
  const totalPaginas = Math.max(1, Math.ceil(total / PER_PAGE))
  const pagina = Math.min(page, totalPaginas)
  const paginados = (data ?? []).slice((pagina - 1) * PER_PAGE, pagina * PER_PAGE)
  // Invalida por prefijo: refresca esta lista filtrada y también el typeahead ([recurso, id]).
  const invalidate = () => qc.invalidateQueries({ queryKey: [recurso, id] })
  const closeModal = () => {
    setModalOpen(false)
    setEditing(null)
  }

  const saveM = useMutation({
    mutationFn: (v: SujetoInput) =>
      editing ? updateSujeto(recurso, id, editing.id, v) : createSujeto(recurso, id, v),
    onSuccess: () => {
      invalidate()
      closeModal()
    },
  })
  const deleteM = useMutation({ mutationFn: (sid: number) => deleteSujeto(recurso, id, sid), onSuccess: invalidate })

  const onDelete = (s: Sujeto) => {
    if (window.confirm(`¿Eliminar "${s.nombre}"?`)) {
      deleteM.mutate(s.id)
    }
  }

  return (
    <>
      <CCard>
        <CCardHeader className="d-flex justify-content-between align-items-center">
          <div>
            <Link to="/empresas" className="text-decoration-none small">
              ← Empresas
            </Link>
            <strong className="ms-2">{titulo}</strong>
          </div>
          <CButton
            color="primary"
            size="sm"
            onClick={() => {
              setEditing(null)
              setModalOpen(true)
            }}
          >
            Nuevo {singular}
          </CButton>
        </CCardHeader>
        <CCardBody>
          <CForm
            className="row g-2 align-items-end mb-3"
            onSubmit={(e) => {
              e.preventDefault()
              setBusqueda(q.trim())
            }}
          >
            <div className="col-sm-6 col-md-5">
              <CFormInput
                size="sm"
                placeholder="Buscar por nombre o CUIT…"
                value={q}
                onChange={(e) => setQ(e.target.value)}
              />
            </div>
            <div className="col-auto">
              <CButton type="submit" color="primary" variant="outline" size="sm">
                Buscar
              </CButton>
            </div>
            {busqueda && (
              <div className="col-auto">
                <CButton
                  type="button"
                  color="secondary"
                  variant="outline"
                  size="sm"
                  onClick={() => {
                    setQ('')
                    setBusqueda('')
                  }}
                >
                  Limpiar
                </CButton>
              </div>
            )}
            <div className="col-auto ms-auto">
              <CFormSelect size="sm" value={orden} onChange={(e) => setOrden(e.target.value)} title="Ordenar por">
                <option value="">Orden: Nombre</option>
                <option value="cuit">Orden: CUIT</option>
              </CFormSelect>
            </div>
          </CForm>
          {isLoading && <CSpinner />}
          {isError && <CAlert color="danger">No se pudieron cargar los {titulo.toLowerCase()}.</CAlert>}
          {data && data.length === 0 && busqueda && (
            <CAlert color="info" className="py-2">
              Sin resultados para “{busqueda}”.
            </CAlert>
          )}
          {data && (
            <CTable hover responsive align="middle" className="mb-0">
              <CTableHead>
                <CTableRow>
                  <CTableHeaderCell>Nombre</CTableHeaderCell>
                  <CTableHeaderCell>CUIT</CTableHeaderCell>
                  <CTableHeaderCell>Localidad</CTableHeaderCell>
                  <CTableHeaderCell>Teléfono</CTableHeaderCell>
                  <CTableHeaderCell className="text-end">Acciones</CTableHeaderCell>
                </CTableRow>
              </CTableHead>
              <CTableBody>
                {paginados.map((s) => (
                  <CTableRow key={s.id}>
                    <CTableDataCell>{s.nombre}</CTableDataCell>
                    <CTableDataCell>{s.cuit ?? '—'}</CTableDataCell>
                    <CTableDataCell>{s.localidad ?? '—'}</CTableDataCell>
                    <CTableDataCell>{s.telefono ?? '—'}</CTableDataCell>
                    <CTableDataCell className="text-end">
                      {esProveedor && (
                        <CButton
                          as={Link}
                          to={`/empresas/${id}/proveedores/${s.id}/imputacion`}
                          color="info"
                          variant="outline"
                          size="sm"
                          className="me-2"
                        >
                          Imputación
                        </CButton>
                      )}
                      <CButton
                        color="secondary"
                        variant="outline"
                        size="sm"
                        className="me-2"
                        onClick={() => {
                          setEditing(s)
                          setModalOpen(true)
                        }}
                      >
                        Editar
                      </CButton>
                      <CButton color="danger" variant="outline" size="sm" onClick={() => onDelete(s)}>
                        Eliminar
                      </CButton>
                    </CTableDataCell>
                  </CTableRow>
                ))}
                {total === 0 && !busqueda && (
                  <CTableRow>
                    <CTableDataCell colSpan={5} className="text-center text-body-secondary py-4">
                      Sin {titulo.toLowerCase()} cargados.
                    </CTableDataCell>
                  </CTableRow>
                )}
              </CTableBody>
            </CTable>
          )}
          {total > 0 && (
            <div className="d-flex justify-content-between align-items-center mt-2 small">
              <span className="text-body-secondary">
                {total} {total === 1 ? singular : `${singular}s`}
                {total > PER_PAGE ? ` · pág. ${pagina}/${totalPaginas}` : ''}
              </span>
              {total > PER_PAGE && (
                <div className="d-flex gap-2">
                  <CButton
                    color="secondary"
                    variant="outline"
                    size="sm"
                    disabled={pagina <= 1}
                    onClick={() => setPage((p) => Math.max(1, p - 1))}
                  >
                    ← Anterior
                  </CButton>
                  <CButton
                    color="secondary"
                    variant="outline"
                    size="sm"
                    disabled={pagina >= totalPaginas}
                    onClick={() => setPage((p) => p + 1)}
                  >
                    Siguiente →
                  </CButton>
                </div>
              )}
            </div>
          )}
        </CCardBody>
      </CCard>

      <SujetoFormModal
        visible={modalOpen}
        sujeto={editing}
        esProveedor={esProveedor}
        empresaId={id}
        saving={saveM.isPending}
        onClose={closeModal}
        onSubmit={(v) => saveM.mutate(v)}
      />
    </>
  )
}
