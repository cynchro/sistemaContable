import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import {
  CRow,
  CCol,
  CCard,
  CCardHeader,
  CCardBody,
  CFormSelect,
  CSpinner,
  CAlert,
  CBadge,
} from '@coreui/react'
import { listEmpresas } from '../../api/empresas'
import { listPeriodos } from '../../api/periodos'
import { getTotales } from '../../api/libroIva'

const money = (v?: string) => {
  const n = Number(v)
  return Number.isFinite(n) ? n.toLocaleString('es-AR', { style: 'currency', currency: 'ARS' }) : (v ?? '—')
}

/** Resumen de IVA del período elegido: total ventas/compras y saldo a pagar/favor. */
function ResumenIva({ empresaId, periodoId }: { empresaId: number; periodoId: number }) {
  const { data, isLoading, isError } = useQuery({
    queryKey: ['totales', empresaId, periodoId],
    queryFn: () => getTotales(empresaId, periodoId),
  })

  if (isLoading) return <CSpinner size="sm" />
  if (isError || !data) return <CAlert color="danger" className="mb-0">No se pudo cargar el resumen.</CAlert>

  const saldo = Number(data.saldo_iva)
  return (
    <CRow className="g-3">
      {[
        ['Total ventas', data.total_ventas],
        ['IVA débito', data.iva_ventas],
        ['Total compras', data.total_compras],
        ['IVA crédito', data.iva_compras],
      ].map(([label, val]) => (
        <CCol sm={6} xl={3} key={label}>
          <div className="border rounded p-3 h-100">
            <div className="text-body-secondary small">{label}</div>
            <div className="fs-5 fw-semibold">{money(val)}</div>
          </div>
        </CCol>
      ))}
      <CCol xs={12}>
        <div className="d-flex justify-content-between align-items-center border rounded p-3 bg-body-tertiary">
          <span className="fw-semibold">Saldo de IVA del período</span>
          <span className="d-flex align-items-center gap-2">
            <CBadge color={saldo >= 0 ? 'danger' : 'success'}>{saldo >= 0 ? 'A pagar' : 'A favor'}</CBadge>
            <span className="fs-4 fw-bold">{money(data.saldo_iva)}</span>
          </span>
        </div>
      </CCol>
      <CCol xs={12} className="text-end">
        <Link to={`/empresas/${empresaId}/periodos/${periodoId}/libro-iva`} className="small">
          Ver libro IVA y DDJJ →
        </Link>
      </CCol>
    </CRow>
  )
}

export default function Dashboard() {
  const { data: empresas, isLoading } = useQuery({ queryKey: ['empresas'], queryFn: listEmpresas })

  const [empresaId, setEmpresaId] = useState<number | null>(null)
  const [periodoId, setPeriodoId] = useState<number | null>(null)

  // Selección inicial de la primera empresa cuando llega el listado.
  useEffect(() => {
    if (empresaId == null && empresas && empresas.length > 0) {
      setEmpresaId(empresas[0].id)
    }
  }, [empresas, empresaId])

  const { data: periodos } = useQuery({
    queryKey: ['periodos', empresaId],
    queryFn: () => listPeriodos(empresaId as number),
    enabled: empresaId != null,
  })

  // Cuando cambian los períodos (o la empresa), elegimos el primero disponible.
  useEffect(() => {
    if (periodos) {
      setPeriodoId(periodos.length > 0 ? periodos[0].id : null)
    }
  }, [periodos])

  return (
    <>
      <h2 className="mb-4">Inicio</h2>

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
          <strong>Resumen de IVA por período</strong>
          <div className="d-flex flex-wrap gap-2">
            <CFormSelect
              size="sm"
              style={{ minWidth: 200 }}
              value={empresaId ?? ''}
              onChange={(e) => setEmpresaId(e.target.value ? Number(e.target.value) : null)}
            >
              <option value="">— Empresa —</option>
              {empresas?.map((e) => (
                <option key={e.id} value={e.id}>
                  {e.nombre}
                </option>
              ))}
            </CFormSelect>
            <CFormSelect
              size="sm"
              style={{ minWidth: 160 }}
              value={periodoId ?? ''}
              disabled={!periodos || periodos.length === 0}
              onChange={(e) => setPeriodoId(e.target.value ? Number(e.target.value) : null)}
            >
              <option value="">— Período —</option>
              {periodos?.map((p) => (
                <option key={p.id} value={p.id}>
                  {p.nombre}
                </option>
              ))}
            </CFormSelect>
          </div>
        </CCardHeader>
        <CCardBody>
          {empresaId != null && periodoId != null ? (
            <ResumenIva empresaId={empresaId} periodoId={periodoId} />
          ) : (
            <div className="text-body-secondary py-3 text-center">
              Elegí una empresa y un período para ver el resumen.
            </div>
          )}
        </CCardBody>
      </CCard>
    </>
  )
}
