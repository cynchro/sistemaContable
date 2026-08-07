import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import {
  CCard,
  CCardHeader,
  CCardBody,
  CTable,
  CTableHead,
  CTableRow,
  CTableHeaderCell,
  CTableBody,
  CTableDataCell,
  CFormCheck,
  CBadge,
  CSpinner,
  CAlert,
  CButton,
} from '@coreui/react'
import { listAlertas } from '../../../api/alertas'
import { usePageTour } from '../../../tours/usePageTour'
import { tourAlertas } from '../../../tours/tours'

/**
 * Motor de alertas estadísticas v1 (documento "Satélite Visual IVA" §7): compara el total del
 * último período de cada empresa contra el promedio de sus períodos anteriores (compras y
 * ventas por separado). Pensado para detectar bienes de uso o movimientos inusuales sin
 * depender de la factura física, que hoy los clientes ya no suelen enviar al estudio.
 */
export default function AlertasPage() {
  const [soloAlertas, setSoloAlertas] = useState(true)
  const { data, isLoading, isError } = useQuery({ queryKey: ['alertas'], queryFn: listAlertas })
  const { start: verRecorrido } = usePageTour('alertas', tourAlertas)

  const filas = (data ?? []).filter((a) => !soloAlertas || a.alerta)

  return (
    <CCard>
      <CCardHeader className="d-flex justify-content-between align-items-start">
        <div>
          <strong>Alertas estadísticas</strong>
          <div className="text-body-secondary small mt-1">
            Compara el último período de cada empresa contra el promedio de sus períodos anteriores (mínimo 3),
            tanto para compras como para ventas. Un desvío por encima del 30% puede señalar la compra de un bien
            de uso u otro movimiento fuera de lo habitual.{' '}
            <strong>v1 con umbral por defecto</strong> (pendiente de confirmar con el contador).
          </div>
        </div>
        <CButton color="secondary" variant="outline" size="sm" onClick={verRecorrido}>
          Ver recorrido
        </CButton>
      </CCardHeader>
      <CCardBody>
        <CFormCheck
          id="tour-alertas-checkbox"
          className="mb-3"
          label="Mostrar solo las que disparan alerta"
          checked={soloAlertas}
          onChange={(e) => setSoloAlertas(e.target.checked)}
        />

        {isLoading && <CSpinner />}
        {isError && <CAlert color="danger">No se pudieron cargar las alertas.</CAlert>}
        {data && (
          <CTable id="tour-alertas-tabla" hover responsive align="middle" className="ledger mb-0">
            <CTableHead>
              <CTableRow>
                <CTableHeaderCell>Empresa</CTableHeaderCell>
                <CTableHeaderCell>Tipo</CTableHeaderCell>
                <CTableHeaderCell>Período</CTableHeaderCell>
                <CTableHeaderCell className="text-end">Actual</CTableHeaderCell>
                <CTableHeaderCell className="text-end">Promedio histórico</CTableHeaderCell>
                <CTableHeaderCell className="text-end">Desvío</CTableHeaderCell>
                <CTableHeaderCell></CTableHeaderCell>
              </CTableRow>
            </CTableHead>
            <CTableBody>
              {filas.map((a) => (
                <CTableRow key={`${a.empresa_id}-${a.tipo}-${a.periodo_id}`}>
                  <CTableDataCell>{a.empresa_nombre}</CTableDataCell>
                  <CTableDataCell className="text-capitalize">{a.tipo}</CTableDataCell>
                  <CTableDataCell>{a.periodo_nombre}</CTableDataCell>
                  <CTableDataCell className="text-end">{a.actual}</CTableDataCell>
                  <CTableDataCell className="text-end">{a.promedio}</CTableDataCell>
                  <CTableDataCell className="text-end">{a.desvio_pct}%</CTableDataCell>
                  <CTableDataCell>
                    {a.alerta && <CBadge color="warning">Fuera de lo habitual</CBadge>}
                  </CTableDataCell>
                </CTableRow>
              ))}
              {filas.length === 0 && (
                <CTableRow>
                  <CTableDataCell colSpan={7} className="text-center text-body-secondary py-4">
                    {soloAlertas ? 'Sin alertas por el momento.' : 'Sin datos suficientes para evaluar.'}
                  </CTableDataCell>
                </CTableRow>
              )}
            </CTableBody>
          </CTable>
        )}
      </CCardBody>
    </CCard>
  )
}
