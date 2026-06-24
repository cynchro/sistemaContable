import { CRow, CCol, CCard, CCardHeader, CCardBody } from '@coreui/react'

/**
 * Página de inicio (placeholder del scaffolding). Las pantallas reales de cada módulo
 * se construyen en sus tasks del feature Frontend.
 */
export default function Dashboard() {
  const modulos = [
    { titulo: 'IVA', detalle: 'Comprobantes, libro IVA, DDJJ y exportaciones.' },
    { titulo: 'AFIP', detalle: 'Factura electrónica y consulta de padrón.' },
    { titulo: 'Sueldos', detalle: 'Legajos, conceptos, liquidación y recibos.' },
    { titulo: 'Gestión del estudio', detalle: 'Vencimientos, tareas y honorarios.' },
  ]

  return (
    <>
      <h2 className="mb-4">Inicio</h2>
      <CRow className="g-3">
        {modulos.map((m) => (
          <CCol md={6} xl={3} key={m.titulo}>
            <CCard className="h-100">
              <CCardHeader>{m.titulo}</CCardHeader>
              <CCardBody>{m.detalle}</CCardBody>
            </CCard>
          </CCol>
        ))}
      </CRow>
    </>
  )
}
