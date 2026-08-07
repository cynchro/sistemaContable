import { useState } from 'react'
import {
  CCard,
  CCardHeader,
  CCardBody,
  CRow,
  CCol,
  CListGroup,
  CListGroupItem,
  CBadge,
  CAccordion,
  CAccordionItem,
  CAccordionHeader,
  CAccordionBody,
} from '@coreui/react'
import { MANUALES } from './manualesContenido'
import FlujoSistemaDiagrama from './FlujoSistemaDiagrama'

/**
 * Manuales de uso del sistema. Menú de áreas a la izquierda (IVA, Sueldos, Automatización…)
 * y el contenido del área elegida a la derecha, en acordeón por función. El contenido vive en
 * `manualesContenido.ts` para que crezca sin tocar esta vista.
 */
export default function ManualesPage() {
  const [activa, setActiva] = useState(MANUALES[0]?.id ?? '')
  const seccion = MANUALES.find((s) => s.id === activa) ?? MANUALES[0]

  return (
    <>
      <div className="mb-3">
        <h2 className="mb-1">Manuales de uso</h2>
        <div className="text-body-secondary">
          Guía de uso del sistema por área. Elegí un área y expandí cada función para ver cómo se usa.
        </div>
      </div>

      <CRow className="g-3">
        <CCol md={3}>
          <CListGroup>
            {MANUALES.map((s) => (
              <CListGroupItem
                key={s.id}
                as="button"
                active={s.id === seccion?.id}
                onClick={() => setActiva(s.id)}
                className="d-flex justify-content-between align-items-center"
              >
                {s.titulo}
                {s.estado === 'en_preparacion' && (
                  <CBadge color="secondary" shape="rounded-pill">
                    Próximamente
                  </CBadge>
                )}
              </CListGroupItem>
            ))}
          </CListGroup>
        </CCol>

        <CCol md={9}>
          <CCard>
            <CCardHeader className="d-flex justify-content-between align-items-center">
              <strong>{seccion?.titulo}</strong>
              {seccion?.estado === 'en_preparacion' && <CBadge color="secondary">En preparación</CBadge>}
            </CCardHeader>
            <CCardBody>
              <p className="text-body-secondary">{seccion?.intro}</p>

              {seccion?.id === 'navegacion' && <FlujoSistemaDiagrama />}

              {seccion && seccion.subsecciones.length > 0 ? (
                <CAccordion alwaysOpen>
                  {seccion.subsecciones.map((sub, i) => (
                    <CAccordionItem itemKey={i} key={sub.titulo}>
                      <CAccordionHeader>{sub.titulo}</CAccordionHeader>
                      <CAccordionBody>
                        {sub.cuerpo.map((p, j) => (
                          <p key={j} className={j === sub.cuerpo.length - 1 ? 'mb-0' : undefined}>
                            {p}
                          </p>
                        ))}
                      </CAccordionBody>
                    </CAccordionItem>
                  ))}
                </CAccordion>
              ) : (
                <div className="text-body-secondary py-3 text-center">
                  El manual de esta área se publicará próximamente.
                </div>
              )}
            </CCardBody>
          </CCard>
        </CCol>
      </CRow>
    </>
  )
}
