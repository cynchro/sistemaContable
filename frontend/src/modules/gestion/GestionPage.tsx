import { useState } from 'react'
import { CCard, CCardHeader, CCardBody, CNav, CNavItem, CNavLink, CButton } from '@coreui/react'
import TareasTab from './TareasTab'
import HonorariosTab from './HonorariosTab'
import { usePageTour } from '../../tours/usePageTour'
import { tourGestion } from '../../tours/tours'

// Vencimientos se sacó de acá (informe del cliente 10/08/2026, pedido 3): ya vive en el SIGE
// (Vencimientos Fiscales), no se duplica. `VencimientosTab.tsx` y su ruta backend quedan sin
// tocar — solo se retira de esta navegación.
type Tab = 'tareas' | 'honorarios'

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
          {tab === 'honorarios' && <HonorariosTab />}
        </CCardBody>
      </CCard>
    </>
  )
}
