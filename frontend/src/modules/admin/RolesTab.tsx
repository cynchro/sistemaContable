import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import {
  CButton,
  CForm,
  CFormInput,
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
import { listRoles, createRol, type Rol } from '../../api/admin'
import { apiError } from './shared'
import RolPermisosModal from './RolPermisosModal'

export default function RolesTab() {
  const qc = useQueryClient()
  const [nombre, setNombre] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [rolActivo, setRolActivo] = useState<Rol | null>(null)

  const { data, isLoading, isError, error: qErr } = useQuery({ queryKey: ['roles'], queryFn: listRoles })
  const invalidate = () => qc.invalidateQueries({ queryKey: ['roles'] })

  const createM = useMutation({
    mutationFn: () => createRol(nombre.trim()),
    onSuccess: () => {
      setNombre('')
      setError(null)
      invalidate()
    },
    onError: (e) => setError(apiError(e, 'No se pudo crear el rol.')),
  })

  if (isError) return <CAlert color="danger">{apiError(qErr, 'No se pudieron cargar los roles.')}</CAlert>

  return (
    <>
      {error && <CAlert color="danger">{error}</CAlert>}
      <CForm
        className="row g-2 align-items-end mb-3"
        onSubmit={(e) => {
          e.preventDefault()
          if (nombre.trim()) createM.mutate()
        }}
      >
        <div className="col-auto">
          <CFormInput placeholder="Nombre del rol" value={nombre} onChange={(e) => setNombre(e.target.value)} style={{ width: 260 }} />
        </div>
        <div className="col-auto">
          <CButton type="submit" color="primary" disabled={createM.isPending || !nombre.trim()}>
            Nuevo rol
          </CButton>
        </div>
      </CForm>

      {isLoading && <CSpinner />}
      {data && (
        <CTable hover responsive align="middle" className="mb-0">
          <CTableHead>
            <CTableRow>
              <CTableHeaderCell>Rol</CTableHeaderCell>
              <CTableHeaderCell>Estado</CTableHeaderCell>
              <CTableHeaderCell className="text-end">Permisos</CTableHeaderCell>
            </CTableRow>
          </CTableHead>
          <CTableBody>
            {data.map((r) => (
              <CTableRow key={r.id}>
                <CTableDataCell>{r.nombre}</CTableDataCell>
                <CTableDataCell>
                  {(() => {
                    const activo = r.estado === 'activo' || r.estado === 1
                    return <CBadge color={activo ? 'success' : 'secondary'}>{activo ? 'Activo' : 'Inactivo'}</CBadge>
                  })()}
                </CTableDataCell>
                <CTableDataCell className="text-end">
                  <CButton color="primary" variant="outline" size="sm" onClick={() => setRolActivo(r)}>
                    Gestionar permisos
                  </CButton>
                </CTableDataCell>
              </CTableRow>
            ))}
            {data.length === 0 && (
              <CTableRow>
                <CTableDataCell colSpan={3} className="text-center text-body-secondary py-4">
                  Sin roles.
                </CTableDataCell>
              </CTableRow>
            )}
          </CTableBody>
        </CTable>
      )}

      {rolActivo && <RolPermisosModal rol={rolActivo} onClose={() => setRolActivo(null)} />}
    </>
  )
}
