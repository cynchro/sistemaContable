import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
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
  listEmpresas,
  createEmpresa,
  updateEmpresa,
  deleteEmpresa,
  type Empresa,
  type EmpresaInput,
} from '../../api/empresas'
import EmpresaFormModal from './EmpresaFormModal'

export default function EmpresasList() {
  const qc = useQueryClient()
  const navigate = useNavigate()
  const { data: empresas, isLoading, isError } = useQuery({
    queryKey: ['empresas'],
    queryFn: listEmpresas,
  })

  const [modalOpen, setModalOpen] = useState(false)
  const [editing, setEditing] = useState<Empresa | null>(null)

  const closeModal = () => {
    setModalOpen(false)
    setEditing(null)
  }

  const saveMutation = useMutation({
    mutationFn: (values: EmpresaInput) =>
      editing ? updateEmpresa(editing.id, values) : createEmpresa(values),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['empresas'] })
      closeModal()
    },
  })

  const deleteMutation = useMutation({
    mutationFn: (id: number) => deleteEmpresa(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['empresas'] }),
  })

  const onDelete = (e: Empresa) => {
    if (window.confirm(`¿Eliminar la empresa "${e.nombre}"?`)) {
      deleteMutation.mutate(e.id)
    }
  }

  return (
    <>
      <CCard>
        <CCardHeader className="d-flex justify-content-between align-items-center">
          <strong>Empresas / Contribuyentes</strong>
          <CButton
            color="primary"
            size="sm"
            onClick={() => {
              setEditing(null)
              setModalOpen(true)
            }}
          >
            Nueva empresa
          </CButton>
        </CCardHeader>
        <CCardBody>
          {isLoading && <CSpinner />}
          {isError && <CAlert color="danger">No se pudieron cargar las empresas.</CAlert>}
          {empresas && (
            <CTable hover responsive align="middle" className="mb-0">
              <CTableHead>
                <CTableRow>
                  <CTableHeaderCell>Nombre</CTableHeaderCell>
                  <CTableHeaderCell>CUIT</CTableHeaderCell>
                  <CTableHeaderCell>Email</CTableHeaderCell>
                  <CTableHeaderCell>Localidad</CTableHeaderCell>
                  <CTableHeaderCell className="text-end">Acciones</CTableHeaderCell>
                </CTableRow>
              </CTableHead>
              <CTableBody>
                {empresas.map((e) => (
                  <CTableRow key={e.id}>
                    <CTableDataCell>{e.nombre}</CTableDataCell>
                    <CTableDataCell>{e.cuit ?? '—'}</CTableDataCell>
                    <CTableDataCell>{e.email ?? '—'}</CTableDataCell>
                    <CTableDataCell>{e.localidad ?? '—'}</CTableDataCell>
                    <CTableDataCell className="text-end">
                      <CButton
                        color="primary"
                        variant="outline"
                        size="sm"
                        className="me-2"
                        onClick={() => navigate(`/empresas/${e.id}/periodos`)}
                      >
                        Períodos
                      </CButton>
                      <CButton
                        color="secondary"
                        variant="outline"
                        size="sm"
                        className="me-2"
                        onClick={() => {
                          setEditing(e)
                          setModalOpen(true)
                        }}
                      >
                        Editar
                      </CButton>
                      <CButton color="danger" variant="outline" size="sm" onClick={() => onDelete(e)}>
                        Eliminar
                      </CButton>
                    </CTableDataCell>
                  </CTableRow>
                ))}
                {empresas.length === 0 && (
                  <CTableRow>
                    <CTableDataCell colSpan={5} className="text-center text-body-secondary py-4">
                      Sin empresas cargadas.
                    </CTableDataCell>
                  </CTableRow>
                )}
              </CTableBody>
            </CTable>
          )}
        </CCardBody>
      </CCard>

      <EmpresaFormModal
        visible={modalOpen}
        empresa={editing}
        saving={saveMutation.isPending}
        onClose={closeModal}
        onSubmit={(values) => saveMutation.mutate(values)}
      />
    </>
  )
}
