import { useState } from 'react'
import { CCard, CCardHeader, CCardBody, CNav, CNavItem, CNavLink } from '@coreui/react'
import TareasTab from './TareasTab'
import VencimientosTab from './VencimientosTab'
import HonorariosTab from './HonorariosTab'

type Tab = 'tareas' | 'vencimientos' | 'honorarios'

export default function GestionPage() {
  const [tab, setTab] = useState<Tab>('tareas')

  return (
    <>
      <h2 className="mb-4">Gestión del estudio</h2>
      <CCard>
        <CCardHeader>
          <CNav variant="tabs" className="border-bottom-0">
            {([
              ['tareas', 'Tareas'],
              ['vencimientos', 'Vencimientos'],
              ['honorarios', 'Honorarios'],
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
          {tab === 'tareas' && <TareasTab />}
          {tab === 'vencimientos' && <VencimientosTab />}
          {tab === 'honorarios' && <HonorariosTab />}
        </CCardBody>
      </CCard>
    </>
  )
}
