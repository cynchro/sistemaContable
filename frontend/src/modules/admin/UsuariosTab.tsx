import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import {
  CTable,
  CTableHead,
  CTableRow,
  CTableHeaderCell,
  CTableBody,
  CTableDataCell,
  CSpinner,
  CAlert,
  CBadge,
  CButton,
} from '@coreui/react'
import { listUsuarios, listRoles, type Usuario } from '../../api/admin'
import { apiError } from './shared'
import UsuarioEmpresasModal from './UsuarioEmpresasModal'

export default function UsuariosTab() {
  const usuarios = useQuery({ queryKey: ['usuarios'], queryFn: listUsuarios })
  const roles = useQuery({ queryKey: ['roles'], queryFn: listRoles })
  const rolNombre = (id: number | null) => roles.data?.find((r) => r.id === id)?.nombre ?? (id != null ? `#${id}` : '—')
  const [empresasDe, setEmpresasDe] = useState<Usuario | null>(null)

  if (usuarios.isError) {
    return <CAlert color="danger">{apiError(usuarios.error, 'No se pudieron cargar los usuarios.')}</CAlert>
  }

  return (
    <>
      <CAlert color="info" className="small">
        Los usuarios se crean por seeder (CLI): <code>php seeders/AdminSeeder.php &lt;email&gt; &lt;clave&gt; "&lt;estudio&gt;"</code>.
        Desde acá se ven y se gestionan sus roles.
      </CAlert>
      {usuarios.isLoading && <CSpinner />}
      {usuarios.data && (
        <CTable hover responsive align="middle" className="mb-0">
          <CTableHead>
            <CTableRow>
              <CTableHeaderCell>Usuario</CTableHeaderCell>
              <CTableHeaderCell>Rol</CTableHeaderCell>
              <CTableHeaderCell>Tipo</CTableHeaderCell>
              <CTableHeaderCell></CTableHeaderCell>
            </CTableRow>
          </CTableHead>
          <CTableBody>
            {usuarios.data.map((u) => (
              <CTableRow key={u.id}>
                <CTableDataCell>{u.usuario}</CTableDataCell>
                <CTableDataCell>{rolNombre(u.rol)}</CTableDataCell>
                <CTableDataCell>
                  {u.desarrollador === 'S' ? <CBadge color="warning">Desarrollador</CBadge> : <CBadge color="secondary">Estándar</CBadge>}
                </CTableDataCell>
                <CTableDataCell>
                  <CButton color="secondary" variant="outline" size="sm" onClick={() => setEmpresasDe(u)}>
                    Empresas asignadas
                  </CButton>
                </CTableDataCell>
              </CTableRow>
            ))}
            {usuarios.data.length === 0 && (
              <CTableRow>
                <CTableDataCell colSpan={4} className="text-center text-body-secondary py-4">
                  Sin usuarios.
                </CTableDataCell>
              </CTableRow>
            )}
          </CTableBody>
        </CTable>
      )}
      {empresasDe && <UsuarioEmpresasModal usuario={empresasDe} onClose={() => setEmpresasDe(null)} />}
    </>
  )
}
