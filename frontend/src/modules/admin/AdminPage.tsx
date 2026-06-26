import { useState } from 'react'
import { CCard, CCardHeader, CCardBody, CNav, CNavItem, CNavLink } from '@coreui/react'
import RolesTab from './RolesTab'
import PermisosTab from './PermisosTab'
import UsuariosTab from './UsuariosTab'

type Tab = 'roles' | 'permisos' | 'usuarios'

export default function AdminPage() {
  const [tab, setTab] = useState<Tab>('roles')

  return (
    <>
      <h2 className="mb-4">Administración</h2>
      <CCard>
        <CCardHeader>
          <CNav variant="tabs" className="border-bottom-0">
            {([
              ['roles', 'Roles y permisos'],
              ['permisos', 'Permisos'],
              ['usuarios', 'Usuarios'],
            ] as [Tab, string][]).map(([k, label]) => (
              <CNavItem key={k}>
                <CNavLink active={tab === k} style={{ cursor: 'pointer' }} onClick={() => setTab(k)}>
                  {label}
                </CNavLink>
              </CNavItem>
            ))}
          </CNav>
        </CCardHeader>
        <CCardBody>
          {tab === 'roles' && <RolesTab />}
          {tab === 'permisos' && <PermisosTab />}
          {tab === 'usuarios' && <UsuariosTab />}
        </CCardBody>
      </CCard>
    </>
  )
}
