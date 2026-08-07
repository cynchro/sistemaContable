import { Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import {
  CRow,
  CCol,
  CCard,
  CCardHeader,
  CCardBody,
  CSpinner,
  CAlert,
  CBadge,
  CButton,
} from '@coreui/react'
import { listEmpresas } from '../../api/empresas'
import { getTotales } from '../../api/libroIva'
import { listVentas } from '../../api/ventas'
import { listCompras } from '../../api/compras'
import { getPercepciones } from '../../api/reportes'
import { listSujetos } from '../../api/sujetos'
import { useActive } from '../../layout/ActiveContext'
import { usePageTour } from '../../tours/usePageTour'
import { tourNavegacion } from '../../tours/tours'

const money = (v?: string) => {
  const n = Number(v)
  return Number.isFinite(n) ? n.toLocaleString('es-AR', { style: 'currency', currency: 'ARS' }) : (v ?? '—')
}

/** Un mosaico de KPI (valor grande + etiqueta), estilo "stat-card" del Visual IVA. */
function Kpi({ label, value, color }: { label: string; value: string; color?: string }) {
  return (
    <div className={`kpi-tile${color ? ` kpi-${color}` : ''}`}>
      <div className="kpi-value">{value}</div>
      <div className="kpi-label">{label}</div>
    </div>
  )
}

/** Resumen de IVA del período activo: grilla de KPIs (comprobantes, totales, IVA,
 * percepciones/retenciones, saldo, clientes/proveedores) replicando el dashboard
 * del Visual IVA, sobre datos reales del backend. */
function ResumenIva({ empresaId, periodoId }: { empresaId: number; periodoId: number }) {
  const totales = useQuery({
    queryKey: ['totales', empresaId, periodoId],
    queryFn: () => getTotales(empresaId, periodoId),
  })
  const ventas = useQuery({
    queryKey: ['ventas-count', empresaId, periodoId],
    queryFn: () => listVentas(empresaId, periodoId, {}, 1, 1),
  })
  const compras = useQuery({
    queryKey: ['compras-count', empresaId, periodoId],
    queryFn: () => listCompras(empresaId, periodoId, {}, 1, 1),
  })
  const percep = useQuery({
    queryKey: ['percepciones', empresaId, periodoId],
    queryFn: () => getPercepciones(empresaId, periodoId),
  })
  const clientes = useQuery({
    queryKey: ['clientes', empresaId],
    queryFn: () => listSujetos('clientes', empresaId),
  })
  const proveedores = useQuery({
    queryKey: ['proveedores', empresaId],
    queryFn: () => listSujetos('proveedores', empresaId),
  })

  if (totales.isLoading) return <CSpinner size="sm" />
  if (totales.isError || !totales.data)
    return <CAlert color="danger" className="mb-0">No se pudo cargar el resumen.</CAlert>

  const t = totales.data
  const saldo = Number(t.saldo_iva)
  const int = (n?: number) => (n == null ? '—' : String(n))

  return (
    <>
      <div className="kpi-grid mb-3">
        <Kpi label="Comprob. venta" value={int(ventas.data?.total)} />
        <Kpi label="Comprob. compra" value={int(compras.data?.total)} />
        <Kpi label="Total ventas" value={money(t.total_ventas)} />
        <Kpi label="Total compras" value={money(t.total_compras)} />
        <Kpi label="IVA débito" value={money(t.iva_ventas)} />
        <Kpi label="IVA crédito" value={money(t.iva_compras)} />
        <Kpi label="Percepciones" value={money(percep.data?.totales.ventas)} />
        <Kpi label="Retenciones" value={money(percep.data?.totales.compras)} />
        <Kpi label="Clientes" value={int(clientes.data?.length)} />
        <Kpi label="Proveedores" value={int(proveedores.data?.length)} />
      </div>

      <div className="d-flex justify-content-between align-items-center border rounded p-3 bg-body-tertiary">
        <span className="fw-semibold">Saldo de IVA del período (débito − crédito)</span>
        <span className="d-flex align-items-center gap-2">
          <CBadge color={saldo >= 0 ? 'danger' : 'success'}>{saldo >= 0 ? 'A pagar' : 'A favor'}</CBadge>
          <span className="fs-4 fw-bold">{money(t.saldo_iva)}</span>
        </span>
      </div>

      <div className="text-end mt-2">
        <Link to={`/empresas/${empresaId}/periodos/${periodoId}/libro-iva`} className="small">
          Ver libro IVA y DDJJ →
        </Link>
      </div>
    </>
  )
}

export default function Dashboard() {
  const { data: empresas, isLoading } = useQuery({ queryKey: ['empresas'], queryFn: listEmpresas })
  const { empresa, periodo, activeEmpresaId, activePeriodoId } = useActive()
  const { start: verRecorrido } = usePageTour('navegacion', tourNavegacion)

  return (
    <>
      <div className="d-flex justify-content-between align-items-center mb-4">
        <h2 className="mb-0">Inicio</h2>
        <CButton color="secondary" variant="outline" size="sm" onClick={verRecorrido}>
          Ver mapa del sistema
        </CButton>
      </div>

      <CRow className="g-3 mb-4">
        <CCol sm={6} xl={3}>
          <CCard className="h-100">
            <CCardBody>
              <div className="text-body-secondary small">Contribuyentes</div>
              <div className="fs-2 fw-bold">{isLoading ? <CSpinner size="sm" /> : (empresas?.length ?? 0)}</div>
              <Link to="/empresas" className="small">
                Administrar →
              </Link>
            </CCardBody>
          </CCard>
        </CCol>
        {[
          { titulo: 'IVA', detalle: 'Comprobantes, libro IVA, DDJJ y exportaciones.', to: '/empresas' },
          { titulo: 'AFIP', detalle: 'Factura electrónica y consulta de padrón.', to: '/afip' },
          { titulo: 'Sueldos', detalle: 'Legajos, conceptos, liquidación y recibos.', to: '/sueldos' },
        ].map((m) => (
          <CCol sm={6} xl={3} key={m.titulo}>
            <CCard className="h-100">
              <CCardHeader>{m.titulo}</CCardHeader>
              <CCardBody className="d-flex flex-column">
                <div className="flex-grow-1">{m.detalle}</div>
                <Link to={m.to} className="small mt-2">
                  Ir →
                </Link>
              </CCardBody>
            </CCard>
          </CCol>
        ))}
      </CRow>

      <CCard>
        <CCardHeader className="d-flex flex-wrap justify-content-between align-items-center gap-2">
          <strong>Resumen de IVA del período activo</strong>
          {empresa && periodo && (
            <span className="text-body-secondary small">
              {empresa.nombre} · {periodo.nombre}
              <CBadge color={periodo.cerrado === 'S' ? 'secondary' : 'success'} className="ms-2">
                {periodo.cerrado === 'S' ? 'Cerrado' : 'Abierto'}
              </CBadge>
            </span>
          )}
        </CCardHeader>
        <CCardBody>
          {activeEmpresaId != null && activePeriodoId != null ? (
            <ResumenIva empresaId={activeEmpresaId} periodoId={activePeriodoId} />
          ) : (
            <div className="text-body-secondary py-3 text-center">
              Elegí una empresa y un período en la barra superior para ver el resumen.
            </div>
          )}
        </CCardBody>
      </CCard>
    </>
  )
}
