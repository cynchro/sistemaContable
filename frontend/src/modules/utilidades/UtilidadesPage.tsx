import { useState } from 'react'
import { CCard, CCardHeader, CCardBody, CNav, CNavItem, CNavLink } from '@coreui/react'
import CatalogosTab from './CatalogosTab'
import AuditoriaTab from './AuditoriaTab'

type Tab = 'catalogos' | 'auditoria'

/** Utilidades del estudio (réplica del menú "Utilidades" del Visual IVA): visores de
 * los catálogos base de AFIP y la auditoría de operaciones del módulo IVA. */
export default function UtilidadesPage() {
  const [tab, setTab] = useState<Tab>('catalogos')

  return (
    <CCard>
      <CCardHeader>
        <strong>Utilidades</strong>
        <CNav variant="tabs" className="mt-3 border-bottom-0">
          {(
            [
              ['catalogos', 'Catálogos base'],
              ['auditoria', 'Auditoría de operaciones'],
            ] as [Tab, string][]
          ).map(([k, label]) => (
            <CNavItem key={k}>
              <CNavLink active={tab === k} style={{ cursor: 'pointer' }} onClick={() => setTab(k)}>
                {label}
              </CNavLink>
            </CNavItem>
          ))}
        </CNav>
      </CCardHeader>
      <CCardBody>
        {tab === 'catalogos' && <CatalogosTab />}
        {tab === 'auditoria' && <AuditoriaTab />}
      </CCardBody>
    </CCard>
  )
}
