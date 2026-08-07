import { useState } from 'react'
import { CCard, CCardHeader, CCardBody, CNav, CNavItem, CNavLink, CButton } from '@coreui/react'
import CatalogosTab from './CatalogosTab'
import RubrosTab from './RubrosTab'
import TiposRetencionTab from './TiposRetencionTab'
import ConceptosTab from './ConceptosTab'
import AuditoriaTab from './AuditoriaTab'
import { usePageTour } from '../../tours/usePageTour'
import { tourUtilidades } from '../../tours/tours'

type Tab = 'catalogos' | 'rubros' | 'retenciones' | 'conceptos' | 'auditoria'

/** Utilidades del estudio (réplica del menú "Utilidades" del Visual IVA): visores de
 * los catálogos base de AFIP, ABM de rubros, retenciones/percepciones y conceptos propios, y la
 * auditoría de operaciones del módulo IVA. */
export default function UtilidadesPage() {
  const [tab, setTab] = useState<Tab>('catalogos')
  const { start: verRecorrido } = usePageTour('utilidades', tourUtilidades)

  return (
    <CCard>
      <CCardHeader className="d-flex justify-content-between align-items-start">
        <div>
          <strong>Utilidades</strong>
          <CNav id="tour-utilidades-tabs" variant="tabs" className="mt-3 border-bottom-0">
            {(
              [
                ['catalogos', 'Catálogos base'],
                ['rubros', 'Rubros'],
                ['retenciones', 'Retenciones / Percepciones'],
                ['conceptos', 'Conceptos'],
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
        </div>
        <CButton color="secondary" variant="outline" size="sm" onClick={verRecorrido}>
          Ver recorrido
        </CButton>
      </CCardHeader>
      <CCardBody>
        {tab === 'catalogos' && <CatalogosTab />}
        {tab === 'rubros' && <RubrosTab />}
        {tab === 'retenciones' && <TiposRetencionTab />}
        {tab === 'conceptos' && <ConceptosTab />}
        {tab === 'auditoria' && <AuditoriaTab />}
      </CCardBody>
    </CCard>
  )
}
