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
  CBadge,
  CSpinner,
  CAlert,
} from '@coreui/react'
import {
  listPeriodos,
  createPeriodo,
  deletePeriodo,
  cerrarPeriodo,
  abrirPeriodo,
  type Periodo,
  type PeriodoInput,
} from '../../api/periodos'
import PeriodoFormModal from './PeriodoFormModal'

export default function PeriodosList() {
  const { empresaId } = useParams()
  const id = Number(empresaId)
  const qc = useQueryClient()
  const [modalOpen, setModalOpen] = useState(false)

  const { data: periodos, isLoading, isError } = useQuery({
    queryKey: ['periodos', id],
    queryFn: () => listPeriodos(id),
  })

  const invalidate = () => qc.invalidateQueries({ queryKey: ['periodos', id] })
  const createM = useMutation({
    mutationFn: (v: PeriodoInput) => createPeriodo(id, v),
    onSuccess: () => {
      invalidate()
      setModalOpen(false)
    },
  })
  const cerrarM = useMutation({ mutationFn: (pid: number) => cerrarPeriodo(id, pid), onSuccess: invalidate })
  const abrirM = useMutation({ mutationFn: (pid: number) => abrirPeriodo(id, pid), onSuccess: invalidate })
  const deleteM = useMutation({ mutationFn: (pid: number) => deletePeriodo(id, pid), onSuccess: invalidate })

  const onDelete = (p: Periodo) => {
    if (window.confirm(`¿Eliminar el período "${p.nombre}"?`)) {
      deleteM.mutate(p.id)
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
            <strong className="ms-2">Períodos</strong>
          </div>
          <CButton color="primary" size="sm" onClick={() => setModalOpen(true)}>
            Nuevo período
          </CButton>
        </CCardHeader>
        <CCardBody>
          {isLoading && <CSpinner />}
          {isError && <CAlert color="danger">No se pudieron cargar los períodos.</CAlert>}
          {periodos && (
            <CTable hover responsive align="middle" className="mb-0">
              <CTableHead>
                <CTableRow>
                  <CTableHeaderCell>Nombre</CTableHeaderCell>
                  <CTableHeaderCell>Desde</CTableHeaderCell>
                  <CTableHeaderCell>Hasta</CTableHeaderCell>
                  <CTableHeaderCell>Estado</CTableHeaderCell>
                  <CTableHeaderCell className="text-end">Acciones</CTableHeaderCell>
                </CTableRow>
              </CTableHead>
              <CTableBody>
                {periodos.map((p) => {
                  const cerrado = p.cerrado === 'S'
                  return (
                    <CTableRow key={p.id}>
                      <CTableDataCell>{p.nombre}</CTableDataCell>
                      <CTableDataCell>{p.fecha_ini ?? '—'}</CTableDataCell>
                      <CTableDataCell>{p.fecha_fin ?? '—'}</CTableDataCell>
                      <CTableDataCell>
                        <CBadge color={cerrado ? 'secondary' : 'success'}>
                          {cerrado ? 'Cerrado' : 'Abierto'}
                        </CBadge>
                      </CTableDataCell>
                      <CTableDataCell className="text-end">
                        {cerrado ? (
                          <CButton
                            color="warning"
                            variant="outline"
                            size="sm"
                            className="me-2"
                            onClick={() => abrirM.mutate(p.id)}
                          >
                            Abrir
                          </CButton>
                        ) : (
                          <CButton
                            color="secondary"
                            variant="outline"
                            size="sm"
                            className="me-2"
                            onClick={() => cerrarM.mutate(p.id)}
                          >
                            Cerrar
                          </CButton>
                        )}
                        <CButton color="danger" variant="outline" size="sm" onClick={() => onDelete(p)}>
                          Eliminar
                        </CButton>
                      </CTableDataCell>
                    </CTableRow>
                  )
                })}
                {periodos.length === 0 && (
                  <CTableRow>
                    <CTableDataCell colSpan={5} className="text-center text-body-secondary py-4">
                      Sin períodos cargados.
                    </CTableDataCell>
                  </CTableRow>
                )}
              </CTableBody>
            </CTable>
          )}
        </CCardBody>
      </CCard>

      <PeriodoFormModal
        visible={modalOpen}
        saving={createM.isPending}
        onClose={() => setModalOpen(false)}
        onSubmit={(v) => createM.mutate(v)}
      />
    </>
  )
}
