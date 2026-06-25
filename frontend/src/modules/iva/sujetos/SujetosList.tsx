import { useState } from 'react'
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

export default function SujetosList({ recurso }: { recurso: RecursoSujeto }) {
  const esProveedor = recurso === 'proveedores'
  const titulo = esProveedor ? 'Proveedores' : 'Clientes'
  const singular = esProveedor ? 'proveedor' : 'cliente'

  const { empresaId } = useParams()
  const id = Number(empresaId)
  const qc = useQueryClient()
  const [modalOpen, setModalOpen] = useState(false)
  const [editing, setEditing] = useState<Sujeto | null>(null)

  const queryKey = [recurso, id]
  const { data, isLoading, isError } = useQuery({ queryKey, queryFn: () => listSujetos(recurso, id) })
  const invalidate = () => qc.invalidateQueries({ queryKey })
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
          {isLoading && <CSpinner />}
          {isError && <CAlert color="danger">No se pudieron cargar los {titulo.toLowerCase()}.</CAlert>}
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
                {data.map((s) => (
                  <CTableRow key={s.id}>
                    <CTableDataCell>{s.nombre}</CTableDataCell>
                    <CTableDataCell>{s.cuit ?? '—'}</CTableDataCell>
                    <CTableDataCell>{s.localidad ?? '—'}</CTableDataCell>
                    <CTableDataCell>{s.telefono ?? '—'}</CTableDataCell>
                    <CTableDataCell className="text-end">
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
                {data.length === 0 && (
                  <CTableRow>
                    <CTableDataCell colSpan={5} className="text-center text-body-secondary py-4">
                      Sin {titulo.toLowerCase()} cargados.
                    </CTableDataCell>
                  </CTableRow>
                )}
              </CTableBody>
            </CTable>
          )}
        </CCardBody>
      </CCard>

      <SujetoFormModal
        visible={modalOpen}
        sujeto={editing}
        esProveedor={esProveedor}
        saving={saveM.isPending}
        onClose={closeModal}
        onSubmit={(v) => saveM.mutate(v)}
      />
    </>
  )
}
