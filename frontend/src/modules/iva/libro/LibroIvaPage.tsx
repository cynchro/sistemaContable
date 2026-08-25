import { useMemo, useState } from 'react'
import { useParams, Link } from 'react-router-dom'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import {
  CCard,
  CCardHeader,
  CCardBody,
  CNav,
  CNavItem,
  CNavLink,
  CRow,
  CCol,
  CTable,
  CTableHead,
  CTableRow,
  CTableHeaderCell,
  CTableBody,
  CTableDataCell,
  CButton,
  CButtonGroup,
  CFormInput,
  CFormLabel,
  CFormSelect,
  CSpinner,
  CAlert,
} from '@coreui/react'
import { usePageTour } from '../../../tours/usePageTour'
import { tourLibroIva } from '../../../tours/tours'
import { listCatalogo } from '../../../api/catalogos'
import { getMayorResumen, getMayorDetalle } from '../../../api/mayor'
import { listEmpresas } from '../../../api/empresas'
import { listPeriodos } from '../../../api/periodos'
import {
  getTotales,
  getReintegroT,
  getLibroDetalle,
  getDdjj,
  getIvaSimple,
  presentarIvaSimple,
  descargarSubdiario,
  descargarLibroIvaDigital,
  descargarDjIvaSimple,
  descargarSifere,
  type DetalleAlicuota,
} from '../../../api/libroIva'
import {
  getSubdiarioVentas,
  getSubdiarioCompras,
  getPercepciones,
  type Subdiario,
} from '../../../api/reportes'
import { listCredenciales, crearCredencial, actualizarCredencial } from '../../../api/credenciales'
import { type LibroLiquidacion } from '../../../api/liquidaciones'
import {
  useLiquidacionProceso,
  LiquidacionEstado,
  EstadoBadge,
  VerComprobantesLinks,
} from '../liquidaciones/LiquidacionProceso'

const money = (v?: string) => {
  const n = Number(v)
  return Number.isFinite(n) ? n.toLocaleString('es-AR', { style: 'currency', currency: 'ARS' }) : (v ?? '—')
}
const pct = (v: string | null) => (v == null ? '—' : `${Number(v)}%`)

type Tab = 'resumen' | 'ddjj' | 'simple' | 'reportes' | 'mayor' | 'descargas' | 'liquidar'

export default function LibroIvaPage() {
  const { empresaId, periodoId } = useParams()
  const eId = Number(empresaId)
  const pId = Number(periodoId)
  const qc = useQueryClient()
  const [tab, setTab] = useState<Tab>('resumen')
  const { start: verRecorrido } = usePageTour('libro-iva', tourLibroIva)

  const { data: condiciones } = useQuery({
    queryKey: ['catalogo', 'condiciones-iva'],
    queryFn: () => listCatalogo('condiciones-iva'),
  })
  const condMap = useMemo(() => {
    const m = new Map<number, string>()
    condiciones?.forEach((c) => m.set(c.id, c.nombre))
    return m
  }, [condiciones])
  const condNombre = (id: number | null) => (id != null ? (condMap.get(id) ?? `#${id}`) : '—')

  return (
    <CCard>
      <CCardHeader>
        <div className="d-flex justify-content-between align-items-start">
          <div>
            <Link to="/empresas" className="text-decoration-none small">
              ← Empresas
            </Link>
            <strong className="ms-2">Libro IVA y DDJJ</strong>
          </div>
          <CButton color="secondary" variant="outline" size="sm" className="no-print" onClick={verRecorrido}>
            Ver recorrido
          </CButton>
        </div>
        <CNav id="tour-libro-tabs" variant="tabs" className="mt-3 border-bottom-0 no-print">
          {([
            ['liquidar', 'Liquidar IVA'],
            ['ddjj', 'DDJJ (F2002)'],
            ['simple', 'IVA Simple (F2051)'],
            ['reportes', 'Reportes'],
            ['mayor', 'Mayor'],
            ['descargas', 'Descargas'],
            ['resumen', 'Resumen'],
          ] as [Tab, string][]).map(([k, label]) => (
            <CNavItem key={k}>
              <CNavLink
                id={
                  k === 'descargas'
                    ? 'tour-libro-tab-descargas'
                    : k === 'liquidar'
                      ? 'tour-libro-tab-liquidar'
                      : undefined
                }
                active={tab === k}
                style={{ cursor: 'pointer' }}
                onClick={() => setTab(k)}
              >
                {label}
              </CNavLink>
            </CNavItem>
          ))}
        </CNav>
      </CCardHeader>
      <CCardBody>
        {tab === 'resumen' && <Resumen eId={eId} pId={pId} condNombre={condNombre} />}
        {tab === 'ddjj' && <DdjjF2002 eId={eId} pId={pId} condNombre={condNombre} />}
        {tab === 'simple' && <IvaSimpleTab eId={eId} pId={pId} qc={qc} />}
        {tab === 'reportes' && <Reportes eId={eId} pId={pId} />}
        {tab === 'mayor' && <Mayor eId={eId} pId={pId} />}
        {tab === 'descargas' && <Descargas eId={eId} pId={pId} />}
        {tab === 'liquidar' && <LiquidarIva eId={eId} pId={pId} />}
      </CCardBody>
    </CCard>
  )
}

interface SubProps {
  eId: number
  pId: number
  condNombre: (id: number | null) => string
}

function TablaDetalle({
  titulo,
  rows,
  condNombre,
  conCf,
}: {
  titulo: string
  rows: DetalleAlicuota[]
  condNombre: (id: number | null) => string
  conCf?: boolean
}) {
  return (
    <>
      <h6 className="mt-2">{titulo}</h6>
      <CTable small bordered responsive align="middle" className="mb-4 ledger">
        <CTableHead>
          <CTableRow>
            <CTableHeaderCell>Condición IVA</CTableHeaderCell>
            <CTableHeaderCell>Alícuota</CTableHeaderCell>
            <CTableHeaderCell className="text-end">Neto gravado</CTableHeaderCell>
            <CTableHeaderCell className="text-end">IVA</CTableHeaderCell>
            {conCf && <CTableHeaderCell className="text-end">CF computable</CTableHeaderCell>}
          </CTableRow>
        </CTableHead>
        <CTableBody>
          {rows.map((r, i) => (
            <CTableRow key={i}>
              <CTableDataCell>{condNombre(r.condicion_iva_id)}</CTableDataCell>
              <CTableDataCell>{pct(r.alicuota)}</CTableDataCell>
              <CTableDataCell className="text-end">{money(r.neto_gravado)}</CTableDataCell>
              <CTableDataCell className="text-end">{money(r.iva)}</CTableDataCell>
              {conCf && <CTableDataCell className="text-end">{money(r.cf_computable)}</CTableDataCell>}
            </CTableRow>
          ))}
          {rows.length === 0 && (
            <CTableRow>
              <CTableDataCell colSpan={conCf ? 5 : 4} className="text-center text-body-secondary py-3">
                Sin datos.
              </CTableDataCell>
            </CTableRow>
          )}
        </CTableBody>
      </CTable>
    </>
  )
}

function Resumen({ eId, pId, condNombre }: SubProps) {
  const totales = useQuery({ queryKey: ['totales', eId, pId], queryFn: () => getTotales(eId, pId) })
  const detalle = useQuery({ queryKey: ['libro-detalle', eId, pId], queryFn: () => getLibroDetalle(eId, pId) })

  if (totales.isLoading || detalle.isLoading) return <CSpinner />
  if (totales.isError || detalle.isError) return <CAlert color="danger">No se pudo cargar el libro IVA.</CAlert>

  const t = totales.data!
  return (
    <>
      <CRow className="mb-4">
        {[
          ['Total ventas', t.total_ventas],
          ['IVA débito (ventas)', t.iva_ventas],
          ['Total compras', t.total_compras],
          ['IVA crédito (compras)', t.iva_compras],
        ].map(([label, val]) => (
          <CCol md={3} key={label}>
            <CCard className="text-center">
              <CCardBody>
                <div className="text-body-secondary small">{label}</div>
                <div className="fs-5 fw-semibold">{money(val)}</div>
              </CCardBody>
            </CCard>
          </CCol>
        ))}
      </CRow>
      <CCard className="mb-4 bg-body-tertiary">
        <CCardBody className="d-flex justify-content-between align-items-center">
          <strong>Saldo de IVA del período (débito − crédito)</strong>
          <span className="fs-4 fw-bold">{money(t.saldo_iva)}</span>
        </CCardBody>
      </CCard>

      <TablaDetalle titulo="Ventas por alícuota" rows={detalle.data!.ventas} condNombre={condNombre} />
      <TablaDetalle titulo="Compras por alícuota" rows={detalle.data!.compras} condNombre={condNombre} conCf />
    </>
  )
}

function DdjjF2002({ eId, pId, condNombre }: SubProps) {
  const { data, isLoading, isError } = useQuery({ queryKey: ['ddjj', eId, pId], queryFn: () => getDdjj(eId, pId) })

  if (isLoading) return <CSpinner />
  if (isError || !data) return <CAlert color="danger">No se pudo cargar la DDJJ.</CAlert>

  const saldoTecnico = Number(data.saldo.saldo_tecnico)
  return (
    <>
      <TablaDetalle titulo="Débito fiscal (ventas)" rows={data.debito_fiscal.por_alicuota} condNombre={condNombre} />
      <TablaDetalle
        titulo="Crédito fiscal (compras)"
        rows={data.credito_fiscal.por_alicuota}
        condNombre={condNombre}
        conCf
      />
      <CCard className="bg-body-tertiary">
        <CCardBody>
          <CRow>
            <CCol md={4}>
              <div className="text-body-secondary small">Débito fiscal</div>
              <div className="fs-5">{money(data.saldo.debito_fiscal)}</div>
            </CCol>
            <CCol md={4}>
              <div className="text-body-secondary small">Crédito fiscal computable</div>
              <div className="fs-5">{money(data.saldo.credito_computable)}</div>
            </CCol>
            <CCol md={4}>
              <div className="text-body-secondary small">
                Saldo técnico ({saldoTecnico >= 0 ? 'a pagar' : 'a favor'})
              </div>
              <div className={`fs-4 fw-bold ${saldoTecnico >= 0 ? 'text-danger' : 'text-success'}`}>
                {money(data.saldo.saldo_tecnico)}
              </div>
            </CCol>
          </CRow>
        </CCardBody>
      </CCard>
    </>
  )
}

function Fila({ label, value, bold }: { label: string; value: string; bold?: boolean }) {
  return (
    <div className="d-flex justify-content-between py-1 border-bottom">
      <span className={bold ? 'fw-semibold' : ''}>{label}</span>
      <span className={bold ? 'fw-bold' : ''}>{money(value)}</span>
    </div>
  )
}

function IvaSimpleTab({ eId, pId, qc }: { eId: number; pId: number; qc: ReturnType<typeof useQueryClient> }) {
  const [retenciones, setRetenciones] = useState('0')
  const { data, isLoading, isError } = useQuery({
    queryKey: ['iva-simple', eId, pId, retenciones],
    queryFn: () => getIvaSimple(eId, pId, retenciones),
  })

  const presentarM = useMutation({
    mutationFn: () => presentarIvaSimple(eId, pId, retenciones),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['iva-simple', eId, pId] }),
  })

  if (isLoading) return <CSpinner />
  if (isError || !data) return <CAlert color="danger">No se pudo calcular la DDJJ IVA Simple.</CAlert>

  const det = data.determinacion_impuesto
  const pos = data.posicion_mensual
  return (
    <>
      <CAlert color="info" className="small">
        Los arrastres (saldo técnico y libre disponibilidad anteriores) se toman automáticamente de la DDJJ
        presentada del período anterior. Las retenciones/percepciones/pagos sufridos del período se ingresan acá.
      </CAlert>

      <CRow className="mb-4">
        <CCol md={6}>
          <CCard>
            <CCardHeader>Determinación del impuesto</CCardHeader>
            <CCardBody>
              <Fila label="Débito fiscal" value={det.debito_fiscal} />
              <Fila label="Crédito fiscal computable" value={det.credito_fiscal} />
              <Fila label="Saldo técnico a favor anterior" value={det.saldo_tecnico_anterior} />
              <Fila label="Saldo técnico a favor de ARCA" value={det.saldo_tecnico_a_favor_arca} bold />
              <Fila label="Saldo técnico a favor del contribuyente" value={det.saldo_tecnico_a_favor_contribuyente} />
            </CCardBody>
          </CCard>
        </CCol>
        <CCol md={6}>
          <CCard>
            <CCardHeader>Posición mensual</CCardHeader>
            <CCardBody>
              <Fila label="Saldo técnico a favor de ARCA" value={pos.saldo_tecnico_a_favor_arca} />
              <Fila label="Saldo libre disponibilidad anterior" value={pos.saldo_libre_disponibilidad_anterior} />
              <Fila label="Retenciones / percepciones / pagos" value={pos.retenciones_percepciones_pagos} />
              <Fila label="Saldo a pagar" value={pos.saldo_a_pagar} bold />
              <Fila label="Saldo libre disponibilidad del período" value={pos.saldo_libre_disponibilidad_periodo} />
            </CCardBody>
          </CCard>
        </CCol>
      </CRow>

      <div className="row g-2 align-items-end">
        <div className="col-auto">
          <CFormLabel className="small mb-1">Ret./perc./pagos sufridos del período</CFormLabel>
          <CFormInput
            inputMode="decimal"
            style={{ width: 220 }}
            value={retenciones}
            onChange={(e) => setRetenciones(e.target.value)}
          />
        </div>
        <div className="col-auto">
          <CButton color="primary" disabled={presentarM.isPending} onClick={() => presentarM.mutate()}>
            {presentarM.isPending ? 'Presentando…' : 'Presentar DDJJ del período'}
          </CButton>
        </div>
        {presentarM.isSuccess && (
          <div className="col-auto">
            <span className="text-success small">✓ DDJJ presentada.</span>
          </div>
        )}
        {presentarM.isError && (
          <div className="col-auto">
            <span className="text-danger small">No se pudo presentar.</span>
          </div>
        )}
      </div>
    </>
  )
}

/** Tabla del subdiario (ventas o compras): renglón por comprobante + fila de totales. */
function TablaSubdiario({ data, sujeto, conCf }: { data: Subdiario; sujeto: string; conCf?: boolean }) {
  const t = data.totales
  return (
    <CTable small bordered responsive align="middle" className="mb-4 ledger">
      <CTableHead>
        <CTableRow>
          <CTableHeaderCell>Fecha</CTableHeaderCell>
          <CTableHeaderCell>Comprobante</CTableHeaderCell>
          <CTableHeaderCell>{sujeto}</CTableHeaderCell>
          <CTableHeaderCell>CUIT</CTableHeaderCell>
          <CTableHeaderCell className="text-end">Neto gravado</CTableHeaderCell>
          <CTableHeaderCell className="text-end">IVA</CTableHeaderCell>
          {conCf && <CTableHeaderCell className="text-end">CF comp.</CTableHeaderCell>}
          <CTableHeaderCell className="text-end">Percep.</CTableHeaderCell>
          <CTableHeaderCell className="text-end">Total</CTableHeaderCell>
        </CTableRow>
      </CTableHead>
      <CTableBody>
        {data.comprobantes.map((c, i) => (
          <CTableRow key={i}>
            <CTableDataCell>{c.fecha ?? '—'}</CTableDataCell>
            <CTableDataCell>
              {`${c.letra ?? ''} ${c.punto_venta ?? ''}-${c.numero ?? ''}`.trim()}
            </CTableDataCell>
            <CTableDataCell>{(sujeto === 'Proveedor' ? c.proveedor_nombre : c.cliente_nombre) ?? '—'}</CTableDataCell>
            <CTableDataCell>{c.cuit ?? '—'}</CTableDataCell>
            <CTableDataCell className="text-end">{money(String(c.neto_gravado ?? '0'))}</CTableDataCell>
            <CTableDataCell className="text-end">{money(String(c.iva ?? '0'))}</CTableDataCell>
            {conCf && <CTableDataCell className="text-end">{money(String(c.cf_computable ?? '0'))}</CTableDataCell>}
            <CTableDataCell className="text-end">{money(String(c.percepcion ?? '0'))}</CTableDataCell>
            <CTableDataCell className="text-end">{money(String(c.total ?? '0'))}</CTableDataCell>
          </CTableRow>
        ))}
        {data.comprobantes.length === 0 && (
          <CTableRow>
            <CTableDataCell colSpan={conCf ? 9 : 8} className="text-center text-body-secondary py-3">
              Sin comprobantes.
            </CTableDataCell>
          </CTableRow>
        )}
      </CTableBody>
      {data.comprobantes.length > 0 && (
        <CTableHead>
          <CTableRow className="fw-semibold">
            <CTableDataCell colSpan={4} className="text-end">Totales</CTableDataCell>
            <CTableDataCell className="text-end">{money(t.neto_gravado)}</CTableDataCell>
            <CTableDataCell className="text-end">{money(t.iva)}</CTableDataCell>
            {conCf && <CTableDataCell className="text-end">{money(t.cf_computable)}</CTableDataCell>}
            <CTableDataCell className="text-end">{money(t.percepcion)}</CTableDataCell>
            <CTableDataCell className="text-end">{money(t.total)}</CTableDataCell>
          </CTableRow>
        </CTableHead>
      )}
    </CTable>
  )
}

/** Encabezado del reporte (estudio + empresa/CUIT + período + fecha). Clave para el PDF.
 * Toma empresa/período de los listados cacheados (confiable en carga directa/impresión). */
function ReporteHeader({ eId, pId, titulo }: { eId: number; pId: number; titulo: string }) {
  const { data: empresas } = useQuery({ queryKey: ['empresas'], queryFn: listEmpresas })
  const { data: periodos } = useQuery({ queryKey: ['periodos', eId], queryFn: () => listPeriodos(eId) })
  const empresa = empresas?.find((e) => e.id === eId)
  const periodo = periodos?.find((p) => p.id === pId)
  const hoy = new Date().toLocaleDateString('es-AR')
  return (
    <div className="reporte-header">
      <div className="d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
          <div className="reporte-estudio">Estudio Haddad</div>
          <div className="fw-bold fs-5">{empresa?.nombre ?? 'Empresa'}</div>
          <div className="text-body-secondary small">CUIT: {empresa?.cuit ?? '—'}</div>
        </div>
        <div className="text-end">
          <div className="fw-semibold">{titulo}</div>
          <div className="small">Período: {periodo?.nombre ?? '—'}</div>
          <div className="text-body-secondary small">Emitido: {hoy}</div>
        </div>
      </div>
    </div>
  )
}

function Reportes({ eId, pId }: { eId: number; pId: number }) {
  const ventas = useQuery({ queryKey: ['subdiario-ventas', eId, pId], queryFn: () => getSubdiarioVentas(eId, pId) })
  const compras = useQuery({ queryKey: ['subdiario-compras', eId, pId], queryFn: () => getSubdiarioCompras(eId, pId) })
  const percep = useQuery({ queryKey: ['percepciones', eId, pId], queryFn: () => getPercepciones(eId, pId) })

  if (ventas.isLoading || compras.isLoading || percep.isLoading) return <CSpinner />
  if (ventas.isError || compras.isError || percep.isError) {
    return <CAlert color="danger">No se pudieron cargar los reportes.</CAlert>
  }

  return (
    <>
      <div className="d-flex justify-content-end mb-3 no-print">
        <CButton color="primary" variant="outline" onClick={() => window.print()}>
          Imprimir / PDF
        </CButton>
      </div>
      <div className="print-area">
        <section className="reporte-seccion">
          <ReporteHeader eId={eId} pId={pId} titulo="Subdiario de IVA — Ventas" />
          <TablaSubdiario data={ventas.data!} sujeto="Cliente" />
        </section>

        <section className="reporte-seccion">
          <ReporteHeader eId={eId} pId={pId} titulo="Subdiario de IVA — Compras" />
          <TablaSubdiario data={compras.data!} sujeto="Proveedor" conCf />
        </section>

        <section className="reporte-seccion">
          <ReporteHeader eId={eId} pId={pId} titulo="Percepciones y retenciones" />
          <TablaPercepciones titulo="Ventas (percepciones)" grupos={percep.data!.ventas} total={percep.data!.totales.ventas} />
          <TablaPercepciones titulo="Compras (retenciones)" grupos={percep.data!.compras} total={percep.data!.totales.compras} />
        </section>
      </div>
    </>
  )
}

function TablaPercepciones({
  titulo,
  grupos,
  total,
}: {
  titulo: string
  grupos: import('../../../api/reportes').PercepcionGrupo[]
  total: string
}) {
  return (
    <>
      <div className="small text-body-secondary mt-2">{titulo}</div>
      <CTable small bordered responsive align="middle" className="mb-3 ledger">
        <CTableHead>
          <CTableRow>
            <CTableHeaderCell>Tipo</CTableHeaderCell>
            <CTableHeaderCell>Cód. AFIP</CTableHeaderCell>
            <CTableHeaderCell>Provincia</CTableHeaderCell>
            <CTableHeaderCell className="text-end">Cantidad</CTableHeaderCell>
            <CTableHeaderCell className="text-end">Base</CTableHeaderCell>
            <CTableHeaderCell className="text-end">Importe</CTableHeaderCell>
          </CTableRow>
        </CTableHead>
        <CTableBody>
          {grupos.map((g, i) => (
            <CTableRow key={i}>
              <CTableDataCell>{g.tipo_nombre ?? '—'}</CTableDataCell>
              <CTableDataCell>{g.tipo_cod_afip ?? '—'}</CTableDataCell>
              <CTableDataCell>{g.provincia_nombre ?? '—'}</CTableDataCell>
              <CTableDataCell className="text-end">{g.cantidad}</CTableDataCell>
              <CTableDataCell className="text-end">{money(g.base)}</CTableDataCell>
              <CTableDataCell className="text-end">{money(g.importe)}</CTableDataCell>
            </CTableRow>
          ))}
          {grupos.length === 0 && (
            <CTableRow>
              <CTableDataCell colSpan={6} className="text-center text-body-secondary py-3">
                Sin percepciones.
              </CTableDataCell>
            </CTableRow>
          )}
        </CTableBody>
        {grupos.length > 0 && (
          <CTableHead>
            <CTableRow className="fw-semibold">
              <CTableDataCell colSpan={5} className="text-end">Total {titulo.toLowerCase()}</CTableDataCell>
              <CTableDataCell className="text-end">{money(total)}</CTableDataCell>
            </CTableRow>
          </CTableHead>
        )}
      </CTable>
    </>
  )
}

function Mayor({ eId, pId }: { eId: number; pId: number }) {
  const [cuenta, setCuenta] = useState<{ id: number; nombre: string } | null>(null)
  const { data: resumen, isLoading } = useQuery({
    queryKey: ['mayor', eId, pId],
    queryFn: () => getMayorResumen(eId, pId),
  })
  const { data: detalle } = useQuery({
    queryKey: ['mayor-detalle', eId, pId, cuenta?.id],
    queryFn: () => getMayorDetalle(eId, pId, cuenta!.id),
    enabled: cuenta != null,
  })

  if (isLoading) return <CSpinner size="sm" />

  return (
    <>
      <div className="text-body-secondary small mb-2">
        Resumen de movimientos por cuenta (mayorización interna). Hacé clic en una cuenta para ver el
        detalle de los comprobantes imputados.
      </div>
      {!resumen || resumen.length === 0 ? (
        <CAlert color="info">No hay comprobantes mayorizados en este período. Asigná una cuenta por línea (neto) o la cuenta Debe/Haber (total) al cargar ventas o compras.</CAlert>
      ) : (
        <CTable small bordered responsive align="middle" className="ledger">
          <CTableHead>
            <CTableRow>
              <CTableHeaderCell>Cuenta</CTableHeaderCell>
              <CTableHeaderCell className="text-end">Debe</CTableHeaderCell>
              <CTableHeaderCell className="text-end">Haber</CTableHeaderCell>
              <CTableHeaderCell className="text-end">Saldo</CTableHeaderCell>
              <CTableHeaderCell className="text-end">Movim.</CTableHeaderCell>
            </CTableRow>
          </CTableHead>
          <CTableBody>
            {resumen.map((r) => (
              <CTableRow
                key={r.id}
                style={{ cursor: 'pointer' }}
                active={cuenta?.id === r.id}
                onClick={() => setCuenta({ id: r.id, nombre: r.nombre })}
              >
                <CTableDataCell>
                  {r.codigo ? `${r.codigo} — ` : ''}
                  {r.nombre}
                </CTableDataCell>
                <CTableDataCell className="text-end">{money(r.debe)}</CTableDataCell>
                <CTableDataCell className="text-end">{money(r.haber)}</CTableDataCell>
                <CTableDataCell className="text-end">{money(r.saldo)}</CTableDataCell>
                <CTableDataCell className="text-end">{r.movimientos}</CTableDataCell>
              </CTableRow>
            ))}
          </CTableBody>
        </CTable>
      )}

      {cuenta && (
        <div className="mt-4">
          <h6>Detalle — {cuenta.nombre}</h6>
          <CTable small bordered responsive align="middle" className="ledger">
            <CTableHead>
              <CTableRow>
                <CTableHeaderCell>Fecha</CTableHeaderCell>
                <CTableHeaderCell>Comprobante</CTableHeaderCell>
                <CTableHeaderCell>Nombre</CTableHeaderCell>
                <CTableHeaderCell>Concepto</CTableHeaderCell>
                <CTableHeaderCell>Lado</CTableHeaderCell>
                <CTableHeaderCell className="text-end">Importe</CTableHeaderCell>
              </CTableRow>
            </CTableHead>
            <CTableBody>
              {(detalle ?? []).map((m, i) => (
                <CTableRow key={i}>
                  <CTableDataCell>{m.fecha ?? '—'}</CTableDataCell>
                  <CTableDataCell>
                    {`${m.cbte_codigo ?? ''} ${m.letra ?? ''} ${m.punto_venta ?? ''}-${m.numero ?? ''}`.trim()}
                  </CTableDataCell>
                  <CTableDataCell>{m.nombre ?? '—'}</CTableDataCell>
                  <CTableDataCell>{m.nivel === 'linea' ? 'Neto' : 'Total'}</CTableDataCell>
                  <CTableDataCell>{m.lado}</CTableDataCell>
                  <CTableDataCell className="text-end">{money(m.importe)}</CTableDataCell>
                </CTableRow>
              ))}
            </CTableBody>
          </CTable>
        </div>
      )}
    </>
  )
}

function Descargas({ eId, pId }: { eId: number; pId: number }) {
  const [error, setError] = useState<string | null>(null)
  const [sifereProv, setSifereProv] = useState('')
  const { data: reintegro } = useQuery({
    queryKey: ['reintegro-t', eId, pId],
    queryFn: () => getReintegroT(eId, pId),
  })
  const { data: provincias = [] } = useQuery({
    queryKey: ['catalogo', 'provincias'],
    queryFn: () => listCatalogo('provincias'),
  })
  const run = (fn: () => Promise<void>) => {
    setError(null)
    fn().catch((e: unknown) =>
      setError(e instanceof Error && e.message ? e.message : 'No se pudo generar la descarga.'),
    )
  }

  return (
    <>
      {error && <CAlert color="danger">{error}</CAlert>}

      {reintegro && reintegro.comprobantes > 0 && (
        <CAlert color="warning">
          Este período tiene <strong>{reintegro.comprobantes}</strong> Factura(s) T. Al exportar a ARCA,
          ingresá el <strong>reintegro total de {money(reintegro.total)}</strong> en el aplicativo (los
          archivos no lo incluyen; se carga aparte en la pantalla de generación).
        </CAlert>
      )}

      <h6>Subdiario (CSV / TXT)</h6>
      <div className="mb-4 d-flex flex-wrap gap-2">
        <CButtonGroup>
          <CButton color="primary" variant="outline" onClick={() => run(() => descargarSubdiario(eId, pId, 'ventas', 'csv'))}>
            Ventas CSV
          </CButton>
          <CButton color="primary" variant="outline" onClick={() => run(() => descargarSubdiario(eId, pId, 'ventas', 'txt'))}>
            Ventas TXT
          </CButton>
        </CButtonGroup>
        <CButtonGroup>
          <CButton color="primary" variant="outline" onClick={() => run(() => descargarSubdiario(eId, pId, 'compras', 'csv'))}>
            Compras CSV
          </CButton>
          <CButton color="primary" variant="outline" onClick={() => run(() => descargarSubdiario(eId, pId, 'compras', 'txt'))}>
            Compras TXT
          </CButton>
        </CButtonGroup>
      </div>

      <h6>Libro IVA Digital (Portal IVA, ancho fijo)</h6>
      <div className="d-flex flex-wrap gap-2">
        <CButton color="success" variant="outline" onClick={() => run(() => descargarLibroIvaDigital(eId, pId, 'ventas-cbte'))}>
          Ventas — comprobantes
        </CButton>
        <CButton color="success" variant="outline" onClick={() => run(() => descargarLibroIvaDigital(eId, pId, 'ventas-alicuotas'))}>
          Ventas — alícuotas
        </CButton>
        <CButton color="success" variant="outline" onClick={() => run(() => descargarLibroIvaDigital(eId, pId, 'ventas-anulados'))}>
          Ventas — anulados
        </CButton>
        <CButton color="success" variant="outline" onClick={() => run(() => descargarLibroIvaDigital(eId, pId, 'compras-cbte'))}>
          Compras — comprobantes
        </CButton>
        <CButton color="success" variant="outline" onClick={() => run(() => descargarLibroIvaDigital(eId, pId, 'compras-alicuotas'))}>
          Compras — alícuotas
        </CButton>
      </div>

      <h6 className="mt-4">DJ IVA Simple — apertura de otros conceptos por actividad</h6>
      <div className="text-body-secondary small mb-2">
        Distribuye la operatoria del período a la actividad principal de la empresa (v1).
      </div>
      <div className="d-flex flex-wrap gap-2">
        <CButton color="info" variant="outline" onClick={() => run(() => descargarDjIvaSimple(eId, pId, 'debito-fiscal'))}>
          Débito fiscal
        </CButton>
        <CButton color="info" variant="outline" onClick={() => run(() => descargarDjIvaSimple(eId, pId, 'restitucion-debito'))}>
          Restitución de débito
        </CButton>
        <CButton color="info" variant="outline" onClick={() => run(() => descargarDjIvaSimple(eId, pId, 'credito-fiscal'))}>
          Crédito fiscal
        </CButton>
        <CButton color="info" variant="outline" onClick={() => run(() => descargarDjIvaSimple(eId, pId, 'restitucion-credito'))}>
          Restitución de crédito
        </CButton>
      </div>

      <h6 className="mt-4">SIFERE Convenio Multilateral V4 — percepciones de IIBB</h6>
      <div className="text-body-secondary small mb-2">
        Percepciones de IIBB sufridas en compras de una jurisdicción, para cargar en el sistema de
        convenio multilateral (agentes locales que no informan en COMARB).
      </div>
      <div className="d-flex flex-wrap gap-2 align-items-end" style={{ maxWidth: 520 }}>
        <div style={{ flex: 1, minWidth: 220 }}>
          <CFormLabel className="small mb-1">Jurisdicción (provincia)</CFormLabel>
          <CFormSelect size="sm" value={sifereProv} onChange={(e) => setSifereProv(e.target.value)}>
            <option value="">Elegí una provincia…</option>
            {provincias.map((p) => (
              <option key={p.id} value={p.id}>
                {p.nombre}
              </option>
            ))}
          </CFormSelect>
        </div>
        <CButton
          color="warning"
          variant="outline"
          disabled={!sifereProv}
          onClick={() => run(() => descargarSifere(eId, pId, Number(sifereProv)))}
        >
          Descargar percepciones
        </CButton>
      </div>
    </>
  )
}

/**
 * Botón "Liquidar IVA" (plan 25/08/2026): pide al worker del bot externo (`cositasVarias/
 * extractor`, fuera de este repo) que traiga el Libro IVA Digital desde el Portal IVA de ARCA.
 * El "subir" (ecosistema → ARCA) vive en las pantallas de Compras/Ventas (botón "Procesar" debajo
 * de la grilla) — ahí es donde se edita/agrega comprobantes, tiene sentido subir desde ahí y no
 * desde acá. Requiere que la empresa tenga CUIT y una Clave Fiscal cargada (se guarda cifrada del
 * lado del backend, `App\Support\Crypto`) — el formulario de acá cubre esa carga inline, no hay
 * todavía una pantalla propia de "Credenciales" en Contribuyentes (decisión: mantener esto
 * acotado a lo que la feature necesita, no construir un CRUD genérico sin que lo pidan).
 */
function LiquidarIva({ eId, pId }: { eId: number; pId: number }) {
  const qc = useQueryClient()
  const [libro, setLibro] = useState<LibroLiquidacion | ''>('')
  const [usuarioFiscal, setUsuarioFiscal] = useState('')
  const [claveFiscal, setClaveFiscal] = useState('')

  const { data: empresas } = useQuery({ queryKey: ['empresas'], queryFn: listEmpresas })
  const empresa = empresas?.find((e) => e.id === eId)

  const { data: credenciales } = useQuery({
    queryKey: ['credenciales', eId],
    queryFn: () => listCredenciales(eId),
  })
  const credencialFiscal = credenciales?.find((c) => c.tipo === 'fiscal' && c.sistema === 'AFIP')

  const { historial, enCursoId, setEnCursoId, actual, crear, enCurso, error, modalVisible, setModalVisible } =
    useLiquidacionProceso(eId, pId)

  const guardarCredencial = useMutation({
    mutationFn: () =>
      credencialFiscal
        ? actualizarCredencial(eId, credencialFiscal.id, {
            usuario: usuarioFiscal || undefined,
            clave: claveFiscal,
          })
        : crearCredencial(eId, {
            tipo: 'fiscal',
            sistema: 'AFIP',
            usuario: usuarioFiscal || undefined,
            clave: claveFiscal,
          }),
    onSuccess: () => {
      setClaveFiscal('')
      qc.invalidateQueries({ queryKey: ['credenciales', eId] })
    },
  })

  const sinCuit = Boolean(empresa) && !empresa?.cuit
  const sinCredencial = !credencialFiscal
  const puedeLiquidar = !sinCuit && !sinCredencial && !enCurso && libro !== ''

  return (
    <>
      {error && <CAlert color="danger">{error}</CAlert>}

      {sinCuit && (
        <CAlert color="warning">
          Esta empresa no tiene CUIT cargado — hace falta para automatizar contra ARCA. Cargalo desde
          el alta/edición de la empresa.
        </CAlert>
      )}

      <h6>Clave Fiscal de ARCA</h6>
      <div className="text-body-secondary small mb-2">
        Se guarda cifrada del lado del servidor y solo se usa para automatizar el login en el
        Portal IVA cuando hace falta — la sesión se reutiliza mientras siga vigente, no se pide en
        cada corrida.
      </div>
      <div className="d-flex flex-wrap gap-2 align-items-end mb-4" style={{ maxWidth: 560 }}>
        <div style={{ flex: 1, minWidth: 180 }}>
          <CFormLabel className="small mb-1">CUIT / usuario</CFormLabel>
          <CFormInput
            size="sm"
            autoComplete="off"
            value={usuarioFiscal}
            onChange={(e) => setUsuarioFiscal(e.target.value)}
            placeholder={credencialFiscal?.usuario ?? empresa?.cuit ?? 'CUIT'}
          />
        </div>
        <div style={{ flex: 1, minWidth: 180 }}>
          <CFormLabel className="small mb-1">Clave Fiscal</CFormLabel>
          <CFormInput
            size="sm"
            type="password"
            autoComplete="new-password"
            value={claveFiscal}
            onChange={(e) => setClaveFiscal(e.target.value)}
            placeholder={credencialFiscal ? '•••••••• (ya cargada)' : 'Sin cargar'}
          />
        </div>
        <CButton
          color="secondary"
          size="sm"
          disabled={!claveFiscal || guardarCredencial.isPending}
          onClick={() => guardarCredencial.mutate()}
        >
          {credencialFiscal ? 'Actualizar' : 'Guardar'}
        </CButton>
      </div>

      <h6>Comprobantes</h6>
      {sinCredencial && <CAlert color="info">Cargá la Clave Fiscal de ARCA arriba antes de liquidar.</CAlert>}

      <div className="d-flex flex-wrap gap-2 align-items-end mb-3" style={{ maxWidth: 580 }}>
        <div style={{ minWidth: 180 }}>
          <CFormLabel className="small mb-1">Libro</CFormLabel>
          <CFormSelect
            size="sm"
            value={libro}
            onChange={(e) => setLibro(e.target.value as LibroLiquidacion)}
            disabled={enCurso}
          >
            <option value="">Seleccione una opción</option>
            <option value="ventas">Ventas</option>
            <option value="compras">Compras</option>
          </CFormSelect>
        </div>
        <CButton
          color="primary"
          size="sm"
          disabled={!puedeLiquidar || crear.isPending}
          onClick={() => crear.mutate({ direccion: 'traer', libro: libro as LibroLiquidacion })}
        >
          Procesar
        </CButton>
      </div>

      <LiquidacionEstado
        enCursoId={enCursoId}
        actual={actual}
        enCurso={enCurso}
        modalVisible={modalVisible}
        setModalVisible={setModalVisible}
        setEnCursoId={setEnCursoId}
      />

      <h6 className="mt-4">Historial</h6>
      <CTable small hover responsive>
        <CTableHead>
          <CTableRow>
            <CTableHeaderCell>Fecha</CTableHeaderCell>
            <CTableHeaderCell>Dirección</CTableHeaderCell>
            <CTableHeaderCell>Libro</CTableHeaderCell>
            <CTableHeaderCell>Período ARCA</CTableHeaderCell>
            <CTableHeaderCell>Estado</CTableHeaderCell>
            <CTableHeaderCell></CTableHeaderCell>
          </CTableRow>
        </CTableHead>
        <CTableBody>
          {historial?.results.length === 0 && (
            <CTableRow>
              <CTableDataCell colSpan={6} className="text-center text-body-secondary">
                Sin liquidaciones todavía.
              </CTableDataCell>
            </CTableRow>
          )}
          {historial?.results.map((l) => (
            <CTableRow key={l.id}>
              <CTableDataCell>{new Date(l.created_at).toLocaleString('es-AR')}</CTableDataCell>
              <CTableDataCell>{l.direccion}</CTableDataCell>
              <CTableDataCell>{l.libro}</CTableDataCell>
              <CTableDataCell>{l.periodo_arca}</CTableDataCell>
              <CTableDataCell>
                <EstadoBadge estado={l.estado} />
              </CTableDataCell>
              <CTableDataCell>
                {l.estado === 'terminada' && (
                  <VerComprobantesLinks empresaId={l.empresa_id} periodoId={l.periodo_id} libro={l.libro} />
                )}
              </CTableDataCell>
            </CTableRow>
          ))}
        </CTableBody>
      </CTable>
    </>
  )
}
