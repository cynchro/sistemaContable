import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import {
  CModal,
  CModalHeader,
  CModalTitle,
  CModalBody,
  CModalFooter,
  CButton,
  CRow,
  CCol,
  CListGroup,
  CListGroupItem,
  CSpinner,
  CAlert,
} from '@coreui/react'
import { getRol, asignarPermisos, desasignarPermisos, type Rol } from '../../api/admin'
import { apiError } from './shared'

export default function RolPermisosModal({ rol, onClose }: { rol: Rol; onClose: () => void }) {
  const qc = useQueryClient()
  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['rol', rol.id],
    queryFn: () => getRol(rol.id),
  })
  const invalidate = () => qc.invalidateQueries({ queryKey: ['rol', rol.id] })

  const asignar = useMutation({ mutationFn: (pid: number) => asignarPermisos(rol.id, [pid]), onSuccess: invalidate })
  const quitar = useMutation({ mutationFn: (pid: number) => desasignarPermisos(rol.id, [pid]), onSuccess: invalidate })

  return (
    <CModal visible onClose={onClose} alignment="center" size="xl">
      <CModalHeader>
        <CModalTitle>Permisos del rol: {rol.nombre}</CModalTitle>
      </CModalHeader>
      <CModalBody>
        {isLoading && <CSpinner />}
        {isError && <CAlert color="danger">{apiError(error, 'No se pudieron cargar los permisos del rol.')}</CAlert>}
        {data && (
          <CRow>
            <CCol md={6}>
              <h6>Asignados ({data.permisosAsignados.length})</h6>
              <CListGroup>
                {data.permisosAsignados.map((p) => (
                  <CListGroupItem key={p.id_permiso} className="d-flex justify-content-between align-items-center">
                    <code>{p.key}</code>
                    <CButton color="danger" variant="outline" size="sm" onClick={() => quitar.mutate(p.id_permiso)}>
                      Quitar
                    </CButton>
                  </CListGroupItem>
                ))}
                {data.permisosAsignados.length === 0 && (
                  <CListGroupItem className="text-body-secondary">Sin permisos asignados.</CListGroupItem>
                )}
              </CListGroup>
            </CCol>
            <CCol md={6}>
              <h6>Disponibles ({data.permisosDisponibles.length})</h6>
              <CListGroup>
                {data.permisosDisponibles.map((p) => (
                  <CListGroupItem key={p.id_permiso} className="d-flex justify-content-between align-items-center">
                    <code>{p.key}</code>
                    <CButton color="success" variant="outline" size="sm" onClick={() => asignar.mutate(p.id_permiso)}>
                      Asignar
                    </CButton>
                  </CListGroupItem>
                ))}
                {data.permisosDisponibles.length === 0 && (
                  <CListGroupItem className="text-body-secondary">No quedan permisos por asignar.</CListGroupItem>
                )}
              </CListGroup>
            </CCol>
          </CRow>
        )}
      </CModalBody>
      <CModalFooter>
        <CButton color="secondary" variant="outline" onClick={onClose}>
          Cerrar
        </CButton>
      </CModalFooter>
    </CModal>
  )
}
