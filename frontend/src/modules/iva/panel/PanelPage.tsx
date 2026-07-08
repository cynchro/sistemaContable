import { useParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { CCard, CCardHeader, CCardBody, CRow, CCol, CSpinner, CAlert, CBadge } from '@coreui/react'
import { getTotales } from '../../../api/libroIva'
import { listVentas } from '../../../api/ventas'
import { listCompras } from '../../../api/compras'
import { getPercepciones } from '../../../api/reportes'
import { listSujetos } from '../../../api/sujetos'

/**
 * Panel de indicadores de IVA (MUESTRA). Gráficos de ejemplo con los datos reales del
 * período activo — cantidades y montos — pensados como demostración por si se decide
 * implementar un tablero en serio. Sin dependencias de gráficos: SVG/CSS inline.
 *
 * Colores: paleta categórica validada (skill dataviz). Identidad nunca por color solo →
 * cada marca lleva su etiqueta de valor y su leyenda.
 */

// Paleta categórica (pasos que leen en claro y oscuro; con etiquetas de valor para el
// caso de bajo contraste — "relief rule").
const C = {
  blue: '#3987e5',
  aqua: '#199e70',
  yellow: '#c98500',
  violet: '#9085e9',
  red: '#e66767',
  orange: '#d95926',
}

const money = (v?: string | number) => {
  const n = Number(v)
  return Number.isFinite(n) ? n.toLocaleString('es-AR', { style: 'currency', currency: 'ARS' }) : '—'
}
const compact = (v: number) =>
  Math.abs(v) >= 1000 ? v.toLocaleString('es-AR', { notation: 'compact', maximumFractionDigits: 1 }) : String(v)

/** Barras horizontales (magnitud): etiqueta · barra redondeada · valor. */
function Bars({
  data,
  format = (n: number) => String(n),
}: {
  data: Array<{ label: string; value: number; color: string }>
  format?: (n: number) => string
}) {
  const max = Math.max(1, ...data.map((d) => Math.abs(d.value)))
  return (
    <div className="d-flex flex-column gap-3">
      {data.map((d) => (
        <div key={d.label} className="d-flex align-items-center gap-2">
          <div style={{ width: 130 }} className="small text-body-secondary text-truncate" title={d.label}>
            {d.label}
          </div>
          <div className="flex-grow-1 position-relative" style={{ height: 22 }}>
            <div
              className="position-absolute top-0 start-0 h-100"
              style={{ width: '100%', background: 'var(--cui-border-color)', borderRadius: 6, opacity: 0.4 }}
            />
            <div
              className="position-absolute top-0 start-0 h-100 d-flex align-items-center justify-content-end"
              style={{
                width: `${(Math.abs(d.value) / max) * 100}%`,
                minWidth: 4,
                background: d.color,
                borderRadius: 6,
                transition: 'width .3s',
              }}
            />
          </div>
          <div style={{ width: 96 }} className="text-end fw-semibold small">
            {format(d.value)}
          </div>
        </div>
      ))}
    </div>
  )
}

/** Dona (parte-de-un-todo) con dos segmentos + valor central. */
function Donut({
  segments,
  center,
  centerLabel,
}: {
  segments: Array<{ label: string; value: number; color: string }>
  center: string
  centerLabel: string
}) {
  const total = Math.max(1, segments.reduce((s, x) => s + Math.abs(x.value), 0))
  const r = 54
  const circ = 2 * Math.PI * r
  let offset = 0
  return (
    <div className="d-flex align-items-center gap-4 flex-wrap">
      <svg viewBox="0 0 140 140" width="150" height="150" role="img" aria-label={centerLabel}>
        <g transform="rotate(-90 70 70)">
          <circle cx="70" cy="70" r={r} fill="none" stroke="var(--cui-border-color)" strokeWidth="16" opacity="0.4" />
          {segments.map((s) => {
            const frac = Math.abs(s.value) / total
            const dash = frac * circ
            const el = (
              <circle
                key={s.label}
                cx="70"
                cy="70"
                r={r}
                fill="none"
                stroke={s.color}
                strokeWidth="16"
                strokeDasharray={`${dash} ${circ - dash}`}
                strokeDashoffset={-offset}
                strokeLinecap="butt"
              />
            )
            offset += dash
            return el
          })}
        </g>
        <text x="70" y="66" textAnchor="middle" className="fw-bold" style={{ fontSize: 15, fill: 'currentColor' }}>
          {center}
        </text>
        <text x="70" y="84" textAnchor="middle" style={{ fontSize: 9, fill: 'var(--cui-secondary-color)' }}>
          {centerLabel}
        </text>
      </svg>
      <div className="d-flex flex-column gap-2">
        {segments.map((s) => (
          <div key={s.label} className="d-flex align-items-center gap-2">
            <span style={{ width: 12, height: 12, borderRadius: 3, background: s.color, display: 'inline-block' }} />
            <span className="small text-body-secondary" style={{ width: 90 }}>
              {s.label}
            </span>
            <span className="fw-semibold small">{money(s.value)}</span>
          </div>
        ))}
      </div>
    </div>
  )
}

function PanelContenido({ empresaId, periodoId }: { empresaId: number; periodoId: number }) {
  const totales = useQuery({ queryKey: ['totales', empresaId, periodoId], queryFn: () => getTotales(empresaId, periodoId) })
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
  const clientes = useQuery({ queryKey: ['clientes', empresaId], queryFn: () => listSujetos('clientes', empresaId) })
  const proveedores = useQuery({
    queryKey: ['proveedores', empresaId],
    queryFn: () => listSujetos('proveedores', empresaId),
  })

  if (totales.isLoading) return <CSpinner />
  if (totales.isError || !totales.data) return <CAlert color="danger">No se pudieron cargar los datos del período.</CAlert>

  const t = totales.data
  const saldo = Number(t.saldo_iva)
  const debito = Number(t.iva_ventas)
  const credito = Number(t.iva_compras)

  const cantidades = [
    { label: 'Comprob. venta', value: ventas.data?.total ?? 0, color: C.blue },
    { label: 'Comprob. compra', value: compras.data?.total ?? 0, color: C.aqua },
    { label: 'Clientes', value: clientes.data?.length ?? 0, color: C.violet },
    { label: 'Proveedores', value: proveedores.data?.length ?? 0, color: C.orange },
  ]

  const montos = [
    { label: 'Total ventas', value: Number(t.total_ventas), color: C.blue },
    { label: 'Total compras', value: Number(t.total_compras), color: C.aqua },
    { label: 'Percepciones', value: Number(percep.data?.totales.ventas ?? 0), color: C.yellow },
    { label: 'Retenciones', value: Number(percep.data?.totales.compras ?? 0), color: C.orange },
  ]

  return (
    <>
      <CAlert color="info" className="py-2 small">
        <strong>Panel de muestra.</strong> Gráficos de ejemplo con los datos reales del período, a modo de
        demostración de un futuro tablero. No reemplaza a los reportes ni al Libro IVA.
      </CAlert>

      <CRow className="g-3">
        <CCol xl={6}>
          <CCard className="h-100">
            <CCardHeader>
              <strong>Cantidades del período</strong>
            </CCardHeader>
            <CCardBody>
              <Bars data={cantidades} />
            </CCardBody>
          </CCard>
        </CCol>

        <CCol xl={6}>
          <CCard className="h-100">
            <CCardHeader className="d-flex justify-content-between align-items-center">
              <strong>IVA del período</strong>
              <CBadge color={saldo >= 0 ? 'danger' : 'success'}>{saldo >= 0 ? 'Saldo a pagar' : 'Saldo a favor'}</CBadge>
            </CCardHeader>
            <CCardBody>
              <Donut
                segments={[
                  { label: 'Débito', value: debito, color: C.red },
                  { label: 'Crédito', value: credito, color: C.aqua },
                ]}
                center={compact(Math.abs(saldo))}
                centerLabel="saldo"
              />
            </CCardBody>
          </CCard>
        </CCol>

        <CCol xl={12}>
          <CCard>
            <CCardHeader>
              <strong>Montos del período</strong>
            </CCardHeader>
            <CCardBody>
              <Bars data={montos} format={(n) => money(n)} />
            </CCardBody>
          </CCard>
        </CCol>
      </CRow>
    </>
  )
}

export default function PanelPage() {
  const { empresaId, periodoId } = useParams()
  const eId = Number(empresaId)
  const pId = Number(periodoId)

  return (
    <CCard>
      <CCardHeader>
        <strong>Panel — IVA</strong>
      </CCardHeader>
      <CCardBody>
        {Number.isFinite(eId) && Number.isFinite(pId) ? (
          <PanelContenido empresaId={eId} periodoId={pId} />
        ) : (
          <div className="text-body-secondary py-3 text-center">Elegí una empresa y un período.</div>
        )}
      </CCardBody>
    </CCard>
  )
}
