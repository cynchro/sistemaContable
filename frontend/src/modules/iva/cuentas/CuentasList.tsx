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
  listCuentas,
  createCuenta,
  updateCuenta,
  deleteCuenta,
  type Cuenta,
  type CuentaInput,
} from '../../../api/cuentas'
import CuentaFormModal from './CuentaFormModal'
import { usePageTour } from '../../../tours/usePageTour'
import { tourCuentas } from '../../../tours/tours'

/** Plan de cuentas de la empresa activa (Compartido). Réplica del módulo "Cuentas"
 * del Visual IVA: ABM simple código + nombre, por empresa. */
export default function CuentasList() {
  const { empresaId } = useParams()
  const id = Number(empresaId)
  const qc = useQueryClient()
  const { start: verRecorrido } = usePageTour('cuentas', tourCuentas)
  const [modalOpen, setModalOpen] = useState(false)
  const [editing, setEditing] = useState<Cuenta | null>(null)

  const queryKey = ['cuentas', id]
  const { data, isLoading, isError } = useQuery({ queryKey, queryFn: () => listCuentas(id) })
  const invalidate = () => qc.invalidateQueries({ queryKey })
  const closeModal = () => {
    setModalOpen(false)
    setEditing(null)
  }

  const saveM = useMutation({
    mutationFn: (v: CuentaInput) => (editing ? updateCuenta(id, editing.id, v) : createCuenta(id, v)),
    onSuccess: () => {
      invalidate()
      closeModal()
    },
  })
  const deleteM = useMutation({ mutationFn: (cid: number) => deleteCuenta(id, cid), onSuccess: invalidate })

  const onDelete = (c: Cuenta) => {
    if (window.confirm(`¿Eliminar la cuenta "${c.nombre}"?`)) {
      deleteM.mutate(c.id)
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
            <strong className="ms-2">Plan de cuentas</strong>
          </div>
          <div className="d-flex gap-2">
            <CButton color="secondary" variant="outline" size="sm" onClick={verRecorrido}>
              Ver recorrido
            </CButton>
            <CButton
              id="tour-nueva-cuenta"
              color="primary"
              size="sm"
              onClick={() => {
                setEditing(null)
                setModalOpen(true)
              }}
            >
              Nueva cuenta
            </CButton>
          </div>
        </CCardHeader>
        <CCardBody>
          {isLoading && <CSpinner />}
          {isError && <CAlert color="danger">No se pudieron cargar las cuentas.</CAlert>}
          {data && (
            <CTable hover responsive align="middle" className="mb-0">
              <CTableHead>
                <CTableRow>
                  <CTableHeaderCell style={{ width: 140 }}>Código</CTableHeaderCell>
                  <CTableHeaderCell>Nombre</CTableHeaderCell>
                  <CTableHeaderCell className="text-end">Acciones</CTableHeaderCell>
                </CTableRow>
              </CTableHead>
              <CTableBody>
                {data.map((c) => (
                  <CTableRow key={c.id}>
                    <CTableDataCell>{c.codigo ?? '—'}</CTableDataCell>
                    <CTableDataCell>{c.nombre}</CTableDataCell>
                    <CTableDataCell className="text-end">
                      <CButton
                        color="secondary"
                        variant="outline"
                        size="sm"
                        className="me-2"
                        onClick={() => {
                          setEditing(c)
                          setModalOpen(true)
                        }}
                      >
                        Editar
                      </CButton>
                      <CButton color="danger" variant="outline" size="sm" onClick={() => onDelete(c)}>
                        Eliminar
                      </CButton>
                    </CTableDataCell>
                  </CTableRow>
                ))}
                {data.length === 0 && (
                  <CTableRow>
                    <CTableDataCell colSpan={3} className="text-center text-body-secondary py-4">
                      Sin cuentas cargadas.
                    </CTableDataCell>
                  </CTableRow>
                )}
              </CTableBody>
            </CTable>
          )}
        </CCardBody>
      </CCard>

      <CuentaFormModal
        visible={modalOpen}
        cuenta={editing}
        saving={saveM.isPending}
        onClose={closeModal}
        onSubmit={(v) => saveM.mutate(v)}
      />
    </>
  )
}
