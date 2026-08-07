import { useState } from 'react'
import { CCard, CCardHeader, CCardBody, CNav, CNavItem, CNavLink, CButton } from '@coreui/react'
import TareasTab from './TareasTab'
import VencimientosTab from './VencimientosTab'
import HonorariosTab from './HonorariosTab'
import { usePageTour } from '../../tours/usePageTour'
import { tourGestion } from '../../tours/tours'

type Tab = 'tareas' | 'vencimientos' | 'honorarios'

export default function GestionPage() {
  const [tab, setTab] = useState<Tab>('tareas')
  const { start: verRecorrido } = usePageTour('gestion', tourGestion)

  return (
    <>
      <div className="d-flex justify-content-between align-items-center mb-4">
        <h2 className="mb-0">Gestión del estudio</h2>
        <CButton color="secondary" variant="outline" size="sm" onClick={verRecorrido}>
          Ver recorrido
        </CButton>
      </div>
      <CCard>
        <CCardHeader>
          <CNav id="tour-gestion-tabs" variant="tabs" className="border-bottom-0">
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
