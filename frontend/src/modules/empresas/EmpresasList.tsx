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
  CDropdown,
  CDropdownToggle,
  CDropdownMenu,
  CDropdownItem,
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
import { usePageTour } from '../../tours/usePageTour'
import { tourEmpresas } from '../../tours/tours'
import { useOcuparEmpresa } from '../../hooks/useOcuparEmpresa'

export default function EmpresasList() {
  const qc = useQueryClient()
  const navigate = useNavigate()
  const { start: verRecorrido } = usePageTour('empresas', tourEmpresas)
  const { data: empresas, isLoading, isError } = useQuery({
    queryKey: ['empresas'],
    queryFn: listEmpresas,
  })
  const { ocupar, error: lockError, isPending: ocupando, reset: clearLockError } = useOcuparEmpresa()

  /**
   * Navegación guiada por contribuyente (informe del cliente 10/08/2026, pedido 5b: "elijo la
   * empresa → se trabaja directamente sobre eso"): activa la empresa en el `ActiveContext` (pide
   * el lock, igual que el selector del header) y recién ahí navega — así el banner de contexto y
   * el resto de las pantallas ya la ven como activa, sin tener que volver a elegirla arriba.
   */
  const trabajarCon = async (e: Empresa, destino: string) => {
    if (await ocupar(e)) navigate(destino)
  }

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
          <div className="d-flex gap-2">
            <CButton color="secondary" variant="outline" size="sm" onClick={verRecorrido}>
              Ver recorrido
            </CButton>
            <CButton
              id="tour-nueva-empresa"
              color="primary"
              size="sm"
              onClick={() => {
                setEditing(null)
                setModalOpen(true)
              }}
            >
              Nueva empresa
            </CButton>
          </div>
        </CCardHeader>
        <CCardBody>
          {isLoading && <CSpinner />}
          {isError && <CAlert color="danger">No se pudieron cargar las empresas.</CAlert>}
          {lockError && (
            <CAlert color="danger" dismissible onClose={clearLockError}>
              {lockError}
            </CAlert>
          )}
          {empresas && (
            <CTable id="tour-tabla-empresas" hover responsive align="middle" className="mb-0">
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
                      <CDropdown variant="btn-group" className="me-2">
                        <CButton
                          color="primary"
                          size="sm"
                          disabled={ocupando}
                          title="Activar este contribuyente y ver sus períodos"
                          onClick={() => void trabajarCon(e, `/empresas/${e.id}/periodos`)}
                        >
                          Trabajar
                        </CButton>
                        <CDropdownToggle color="primary" size="sm" split title="Otras pantallas" />
                        <CDropdownMenu>
                          <CDropdownItem role="button" onClick={() => void trabajarCon(e, `/empresas/${e.id}/clientes`)}>
                            Clientes
                          </CDropdownItem>
                          <CDropdownItem
                            role="button"
                            onClick={() => void trabajarCon(e, `/empresas/${e.id}/proveedores`)}
                          >
                            Proveedores
                          </CDropdownItem>
                          <CDropdownItem
                            role="button"
                            onClick={() => void trabajarCon(e, `/empresas/${e.id}/actividades`)}
                          >
                            Actividades (IVA)
                          </CDropdownItem>
                        </CDropdownMenu>
                      </CDropdown>
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
