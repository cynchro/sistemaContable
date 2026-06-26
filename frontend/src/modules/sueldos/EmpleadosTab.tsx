import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import {
  CButton,
  CTable,
  CTableHead,
  CTableRow,
  CTableHeaderCell,
  CTableBody,
  CTableDataCell,
  CSpinner,
  CAlert,
  CBadge,
} from '@coreui/react'
import {
  listEmpleados,
  createEmpleado,
  updateEmpleado,
  deleteEmpleado,
  type Empleado,
  type EmpleadoInput,
} from '../../api/sueldos'
import EmpleadoFormModal from './EmpleadoFormModal'
import { apiError } from './shared'

const money = (v?: string | null) => {
  const n = Number(v)
  return Number.isFinite(n) && v != null ? n.toLocaleString('es-AR', { style: 'currency', currency: 'ARS' }) : '—'
}
const nombre = (e: Empleado) => [e.primer_apellido, e.segundo_apellido].filter(Boolean).join(' ') + `, ${e.nombres}`

export default function EmpleadosTab({ empresaId }: { empresaId: number }) {
  const qc = useQueryClient()
  const [open, setOpen] = useState(false)
  const [editing, setEditing] = useState<Empleado | null>(null)
  const [formError, setFormError] = useState<string | null>(null)

  const { data, isLoading, isError } = useQuery({
    queryKey: ['empleados', empresaId],
    queryFn: () => listEmpleados(empresaId),
  })
  const invalidate = () => qc.invalidateQueries({ queryKey: ['empleados', empresaId] })
  const close = () => {
    setOpen(false)
    setEditing(null)
    setFormError(null)
  }

  const saveM = useMutation({
    mutationFn: (v: EmpleadoInput) => (editing ? updateEmpleado(empresaId, editing.id, v) : createEmpleado(empresaId, v)),
    onSuccess: () => {
      invalidate()
      close()
    },
    onError: (e) => setFormError(apiError(e, 'No se pudo guardar el legajo.')),
  })
  const deleteM = useMutation({ mutationFn: (id: number) => deleteEmpleado(empresaId, id), onSuccess: invalidate })

  const onDelete = (e: Empleado) => {
    if (window.confirm(`¿Eliminar el legajo de ${nombre(e)}?`)) deleteM.mutate(e.id)
  }

  return (
    <>
      <div className="d-flex justify-content-end mb-3">
        <CButton color="primary" size="sm" onClick={() => { setEditing(null); setFormError(null); setOpen(true) }}>
          Nuevo legajo
        </CButton>
      </div>

      {isLoading && <CSpinner />}
      {isError && <CAlert color="danger">No se pudieron cargar los legajos.</CAlert>}
      {data && (
        <CTable hover responsive align="middle" className="mb-0">
          <CTableHead>
            <CTableRow>
              <CTableHeaderCell>Legajo</CTableHeaderCell>
              <CTableHeaderCell>Apellido y nombre</CTableHeaderCell>
              <CTableHeaderCell>CUIL</CTableHeaderCell>
              <CTableHeaderCell>Ingreso</CTableHeaderCell>
              <CTableHeaderCell className="text-end">Básico</CTableHeaderCell>
              <CTableHeaderCell>Estado</CTableHeaderCell>
              <CTableHeaderCell className="text-end">Acciones</CTableHeaderCell>
            </CTableRow>
          </CTableHead>
          <CTableBody>
            {data.map((e) => (
              <CTableRow key={e.id}>
                <CTableDataCell>{e.legajo ?? '—'}</CTableDataCell>
                <CTableDataCell>{nombre(e)}</CTableDataCell>
                <CTableDataCell>{e.cuil ?? '—'}</CTableDataCell>
                <CTableDataCell>{e.fecha_ingreso ?? '—'}</CTableDataCell>
                <CTableDataCell className="text-end">{money(e.basico)}</CTableDataCell>
                <CTableDataCell>
                  <CBadge color={e.activo === 'S' ? 'success' : 'secondary'}>
                    {e.activo === 'S' ? 'Activo' : 'Inactivo'}
                  </CBadge>
                </CTableDataCell>
                <CTableDataCell className="text-end">
                  <CButton
                    color="secondary"
                    variant="outline"
                    size="sm"
                    className="me-2"
                    onClick={() => { setEditing(e); setFormError(null); setOpen(true) }}
                  >
                    Editar
                  </CButton>
                  <CButton color="danger" variant="outline" size="sm" onClick={() => onDelete(e)}>
                    Eliminar
                  </CButton>
                </CTableDataCell>
              </CTableRow>
            ))}
            {data.length === 0 && (
              <CTableRow>
                <CTableDataCell colSpan={7} className="text-center text-body-secondary py-4">
                  Sin legajos cargados.
                </CTableDataCell>
              </CTableRow>
            )}
          </CTableBody>
        </CTable>
      )}

      <EmpleadoFormModal
        visible={open}
        empleado={editing}
        saving={saveM.isPending}
        errorMsg={formError}
        onClose={close}
        onSubmit={(v) => saveM.mutate(v)}
      />
    </>
  )
}
