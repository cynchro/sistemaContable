import { useState } from 'react'
import { useParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import {
  CCard,
  CCardHeader,
  CCardBody,
  CForm,
  CFormLabel,
  CFormInput,
  CFormSelect,
  CButton,
  CSpinner,
  CAlert,
  CRow,
  CCol,
} from '@coreui/react'
import { listCuentas } from '../../../api/cuentas'
import { listCatalogo } from '../../../api/catalogos'
import {
  getReporteMayor,
  type MayorReporteFiltros,
  type MayorReporteGrupo,
  type MayorReporteHoja,
  type MayorTotales,
} from '../../../api/mayor'

const money = (v?: string) => {
  const n = Number(v)
  return Number.isFinite(n) ? n.toLocaleString('es-AR', { style: 'currency', currency: 'ARS' }) : (v ?? '—')
}

/** Combinaciones de agrupación más útiles (R2: convenio anual, proveedores, provincias). */
const AGRUPACIONES: Array<{ value: string; label: string }> = [
  { value: 'cuenta,proveedor', label: 'Cuenta → Proveedor' },
  { value: 'cuenta,provincia', label: 'Cuenta → Provincia' },
  { value: 'provincia,cuenta', label: 'Provincia → Cuenta (convenio)' },
  { value: 'provincia,proveedor', label: 'Provincia → Proveedor' },
  { value: 'proveedor', label: 'Proveedor (principales)' },
  { value: 'cuenta', label: 'Cuenta' },
  { value: 'provincia', label: 'Provincia' },
]

function esHoja(hijos: MayorReporteGrupo[] | MayorReporteHoja[]): hijos is MayorReporteHoja[] {
  return hijos.length > 0 && (hijos[0] as MayorReporteHoja).comprobante !== undefined
}

/** Reporte analítico del Mayor por rango de fechas, con filtros y subtotales en cascada (R2). */
export default function ReporteMayorPage() {
  const { empresaId } = useParams()
  const id = Number(empresaId)

  const { data: cuentas } = useQuery({ queryKey: ['cuentas', id], queryFn: () => listCuentas(id) })
  const { data: provincias } = useQuery({
    queryKey: ['catalogo', 'provincias'],
    queryFn: () => listCatalogo('provincias'),
  })

  const [filtros, setFiltros] = useState<MayorReporteFiltros>({ agrupar: 'cuenta,proveedor' })
  const [aplicados, setAplicados] = useState<MayorReporteFiltros | null>(null)

  const reporte = useQuery({
    queryKey: ['reporte-mayor', id, aplicados],
    queryFn: () => getReporteMayor(id, aplicados!),
    enabled: aplicados != null,
  })

  const set = (campo: keyof MayorReporteFiltros, valor: string) =>
    setFiltros((f) => ({ ...f, [campo]: valor }))

  return (
    <CCard>
      <CCardHeader className="d-flex justify-content-between align-items-center">
        <strong>Reportes de Mayor (por rango de fechas)</strong>
        {reporte.data && (
          <CButton color="primary" variant="outline" size="sm" onClick={() => window.print()}>
            Imprimir / PDF
          </CButton>
        )}
      </CCardHeader>
      <CCardBody>
        <div className="text-body-secondary small mb-3">
          Cruza compras y ventas de todos los períodos de la empresa por rango de fechas, con el neto
          imputado a cada cuenta del mayor. Útil para la DDJJ anual de convenio ("gastos por provincia") y
          reportes por proveedor.
        </div>

        <CForm
          className="d-print-none"
          onSubmit={(e) => {
            e.preventDefault()
            setAplicados({ ...filtros })
          }}
        >
          <CRow className="g-3 align-items-end">
            <CCol md={2}>
              <CFormLabel>Desde</CFormLabel>
              <CFormInput type="date" value={filtros.desde ?? ''} onChange={(e) => set('desde', e.target.value)} />
            </CCol>
            <CCol md={2}>
              <CFormLabel>Hasta</CFormLabel>
              <CFormInput type="date" value={filtros.hasta ?? ''} onChange={(e) => set('hasta', e.target.value)} />
            </CCol>
            <CCol md={3}>
              <CFormLabel>Cuenta (mayor)</CFormLabel>
              <CFormSelect
                value={filtros.cuenta_id != null ? String(filtros.cuenta_id) : ''}
                onChange={(e) => set('cuenta_id', e.target.value)}
              >
                <option value="">Todas</option>
                {cuentas?.map((c) => (
                  <option key={c.id} value={c.id}>
                    {c.codigo ? `${c.codigo} — ` : ''}
                    {c.nombre}
                  </option>
                ))}
              </CFormSelect>
            </CCol>
            <CCol md={3}>
              <CFormLabel>Provincia</CFormLabel>
              <CFormSelect
                value={filtros.provincia_id != null ? String(filtros.provincia_id) : ''}
                onChange={(e) => set('provincia_id', e.target.value)}
              >
                <option value="">Todas</option>
                {provincias?.map((p) => (
                  <option key={p.id} value={p.id}>
                    {p.nombre}
                  </option>
                ))}
              </CFormSelect>
            </CCol>
            <CCol md={2}>
              <CFormLabel>Origen</CFormLabel>
              <CFormSelect value={filtros.origen ?? ''} onChange={(e) => set('origen', e.target.value)}>
                <option value="">Ambos</option>
                <option value="compra">Compras</option>
                <option value="venta">Ventas</option>
              </CFormSelect>
            </CCol>
            <CCol md={3}>
              <CFormLabel>CUIT proveedor/cliente</CFormLabel>
              <CFormInput
                placeholder="exacto (opcional)"
                value={filtros.cuit ?? ''}
                onChange={(e) => set('cuit', e.target.value)}
              />
            </CCol>
            <CCol md={4}>
              <CFormLabel>Agrupar (cascada)</CFormLabel>
              <CFormSelect value={filtros.agrupar ?? ''} onChange={(e) => set('agrupar', e.target.value)}>
                {AGRUPACIONES.map((a) => (
                  <option key={a.value} value={a.value}>
                    {a.label}
                  </option>
                ))}
              </CFormSelect>
            </CCol>
            <CCol md={2}>
              <CButton type="submit" color="primary" className="w-100">
                Generar
              </CButton>
            </CCol>
          </CRow>
        </CForm>

        <div className="mt-4">
          {reporte.isFetching && <CSpinner />}
          {reporte.isError && <CAlert color="danger">No se pudo generar el reporte.</CAlert>}
          {reporte.data && (
            <>
              <div className="d-flex justify-content-between align-items-center mb-2">
                <h6 className="mb-0">
                  Resultado
                  {aplicados?.desde || aplicados?.hasta ? (
                    <span className="text-body-secondary">
                      {' '}
                      ({aplicados?.desde || '…'} → {aplicados?.hasta || '…'})
                    </span>
                  ) : null}
                </h6>
                <div>
                  Total neto: <strong>{money(reporte.data.totales.neto)}</strong> · IVA:{' '}
                  {money(reporte.data.totales.iva)} · {reporte.data.totales.cantidad} mov.
                </div>
              </div>
              {reporte.data.grupos.length === 0 ? (
                <CAlert color="info">No hay movimientos mayorizados para esos filtros.</CAlert>
              ) : (
                <div className="ledger">
                  {reporte.data.grupos.map((g, i) => (
                    <Grupo key={i} grupo={g} nivel={0} />
                  ))}
                </div>
              )}
            </>
          )}
        </div>
      </CCardBody>
    </CCard>
  )
}

function Totales({ t }: { t: MayorTotales }) {
  return (
    <span className="text-nowrap">
      {money(t.neto)} <span className="text-body-secondary small">({t.cantidad})</span>
    </span>
  )
}

/** Nodo de grupo colapsable con subtotal; al llegar a las hojas muestra la tabla de movimientos. */
function Grupo({ grupo, nivel }: { grupo: MayorReporteGrupo; nivel: number }) {
  const [abierto, setAbierto] = useState(nivel === 0)
  const hojas = esHoja(grupo.hijos)

  return (
    <div style={{ marginLeft: nivel * 16 }} className="mb-1">
      <div
        className="d-flex justify-content-between align-items-center py-1 px-2 rounded bg-body-tertiary"
        style={{ cursor: 'pointer' }}
        onClick={() => setAbierto((a) => !a)}
      >
        <span>
          <span className="text-body-secondary me-1">{abierto ? '▾' : '▸'}</span>
          <span className="text-uppercase small text-body-secondary me-1">{grupo.dimension}:</span>
          <strong>{grupo.etiqueta || '—'}</strong>
        </span>
        <Totales t={grupo.subtotal} />
      </div>
      {abierto &&
        (hojas ? (
          <table className="table table-sm table-borderless mb-2 mt-1" style={{ marginLeft: 16 }}>
            <thead>
              <tr className="text-body-secondary small">
                <th>Fecha</th>
                <th>Comprobante</th>
                <th>Sujeto</th>
                <th>Provincia</th>
                <th className="text-end">Neto</th>
                <th className="text-end">IVA</th>
              </tr>
            </thead>
            <tbody>
              {(grupo.hijos as MayorReporteHoja[]).map((h, i) => (
                <tr key={i}>
                  <td>{h.fecha ?? '—'}</td>
                  <td>{h.comprobante}</td>
                  <td>{h.sujeto ?? '—'}</td>
                  <td>{h.provincia ?? '—'}</td>
                  <td className="text-end">{money(h.neto)}</td>
                  <td className="text-end">{money(h.iva)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        ) : (
          (grupo.hijos as MayorReporteGrupo[]).map((h, i) => <Grupo key={i} grupo={h} nivel={nivel + 1} />)
        ))}
    </div>
  )
}
