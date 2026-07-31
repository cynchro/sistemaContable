import { useState } from 'react'
import { useParams, Link } from 'react-router-dom'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import {
  CCard,
  CCardHeader,
  CCardBody,
  CRow,
  CCol,
  CForm,
  CFormInput,
  CFormLabel,
  CFormSelect,
  CButton,
  CTable,
  CTableHead,
  CTableRow,
  CTableHeaderCell,
  CTableBody,
  CTableDataCell,
  CSpinner,
  CAlert,
} from '@coreui/react'
import {
  listActividades,
  createActividad,
  deleteActividad,
  listPuntosVenta,
  setPuntoVenta,
  deletePuntoVenta,
  listAlicuotas,
  setAlicuota,
  deleteAlicuota,
  listReceptores,
  setReceptor,
  deleteReceptor,
  listCoeficientes,
  setCoeficiente,
  deleteCoeficiente,
} from '../../../api/actividades'
import { listSujetos } from '../../../api/sujetos'
import { listCuentas } from '../../../api/cuentas'
import { listCatalogo } from '../../../api/catalogos'
import { listConceptos } from '../../../api/conceptos'
import { listMapeoEmpresa, setMapeoEmpresa, deleteMapeoEmpresa } from '../../../api/imputacionContable'
import {
  listPuntoVenta as listVentaPuntoVenta,
  setPuntoVenta as setVentaPuntoVenta,
  deletePuntoVenta as deleteVentaPuntoVenta,
  listPorTipo as listVentaPorTipo,
  setPorTipo as setVentaPorTipo,
  deletePorTipo as deleteVentaPorTipo,
} from '../../../api/ventaClasificacion'

function apiError(e: unknown, fallback: string): string {
  const err = e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
  const data = err.response?.data
  if (data?.errors) {
    const first = Object.values(data.errors)[0]
    if (first?.[0]) return first[0]
  }
  return data?.message ?? fallback
}

export default function ActividadesPage() {
  const { empresaId } = useParams()
  const eId = Number(empresaId)
  const qc = useQueryClient()

  const actividades = useQuery({ queryKey: ['actividades', eId], queryFn: () => listActividades(eId) })
  const puntos = useQuery({ queryKey: ['actividades-pv', eId], queryFn: () => listPuntosVenta(eId) })
  const alicuotas = useQuery({ queryKey: ['actividades-alic', eId], queryFn: () => listAlicuotas(eId) })
  const receptores = useQuery({ queryKey: ['actividades-rec', eId], queryFn: () => listReceptores(eId) })
  const coefs = useQuery({ queryKey: ['actividades-coef', eId], queryFn: () => listCoeficientes(eId) })
  const clientes = useQuery({ queryKey: ['clientes', eId], queryFn: () => listSujetos('clientes', eId) })
  const cuentas = useQuery({ queryKey: ['cuentas', eId], queryFn: () => listCuentas(eId) })
  const tiposComprobante = useQuery({
    queryKey: ['catalogo', 'tipos-comprobante'],
    queryFn: () => listCatalogo('tipos-comprobante'),
  })
  const ventaPv = useQuery({ queryKey: ['venta-clasif-pv', eId], queryFn: () => listVentaPuntoVenta(eId) })
  const ventaTipo = useQuery({ queryKey: ['venta-clasif-tipo', eId], queryFn: () => listVentaPorTipo(eId) })
  const conceptos = useQuery({ queryKey: ['conceptos'], queryFn: () => listConceptos() })
  const mapeoConceptos = useQuery({ queryKey: ['mapeo-conceptos', eId], queryFn: () => listMapeoEmpresa(eId) })

  const [codigo, setCodigo] = useState('')
  const [descripcion, setDescripcion] = useState('')
  const [pv, setPv] = useState('')
  const [actividadId, setActividadId] = useState('')
  const [alic, setAlic] = useState('')
  const [alicAct, setAlicAct] = useState('')
  const [clienteId, setClienteId] = useState('')
  const [recAct, setRecAct] = useState('')
  const [coefAct, setCoefAct] = useState('')
  const [coefPct, setCoefPct] = useState('')
  const [ventaPv2, setVentaPv2] = useState('')
  const [ventaPvCuenta, setVentaPvCuenta] = useState('')
  const [tipoPv, setTipoPv] = useState('')
  const [tipoComprobanteId, setTipoComprobanteId] = useState('')
  const [tipoCuenta, setTipoCuenta] = useState('')
  const [mapeoConceptoId, setMapeoConceptoId] = useState('')
  const [mapeoCuentaId, setMapeoCuentaId] = useState('')
  const [error, setError] = useState<string | null>(null)

  const crearAct = useMutation({
    mutationFn: () => createActividad(eId, codigo.trim(), descripcion.trim()),
    onSuccess: () => {
      setCodigo('')
      setDescripcion('')
      setError(null)
      qc.invalidateQueries({ queryKey: ['actividades', eId] })
    },
    onError: (e) => setError(apiError(e, 'No se pudo crear la actividad.')),
  })
  const borrarAct = useMutation({
    mutationFn: (id: number) => deleteActividad(eId, id),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['actividades', eId] })
      qc.invalidateQueries({ queryKey: ['actividades-pv', eId] })
    },
  })
  const mapearPv = useMutation({
    mutationFn: () => setPuntoVenta(eId, pv.trim(), Number(actividadId)),
    onSuccess: () => {
      setPv('')
      setActividadId('')
      setError(null)
      qc.invalidateQueries({ queryKey: ['actividades-pv', eId] })
    },
    onError: (e) => setError(apiError(e, 'No se pudo guardar el mapeo.')),
  })
  const borrarPv = useMutation({
    mutationFn: (id: number) => deletePuntoVenta(eId, id),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['actividades-pv', eId] }),
  })
  const mapearAlic = useMutation({
    mutationFn: () => setAlicuota(eId, alic.trim(), Number(alicAct)),
    onSuccess: () => {
      setAlic('')
      setAlicAct('')
      setError(null)
      qc.invalidateQueries({ queryKey: ['actividades-alic', eId] })
    },
    onError: (e) => setError(apiError(e, 'No se pudo guardar el mapeo por alícuota.')),
  })
  const borrarAlic = useMutation({
    mutationFn: (id: number) => deleteAlicuota(eId, id),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['actividades-alic', eId] }),
  })
  const mapearRec = useMutation({
    mutationFn: () => setReceptor(eId, Number(clienteId), Number(recAct)),
    onSuccess: () => {
      setClienteId('')
      setRecAct('')
      setError(null)
      qc.invalidateQueries({ queryKey: ['actividades-rec', eId] })
    },
    onError: (e) => setError(apiError(e, 'No se pudo guardar el mapeo por receptor.')),
  })
  const borrarRec = useMutation({
    mutationFn: (id: number) => deleteReceptor(eId, id),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['actividades-rec', eId] }),
  })
  const mapearCoef = useMutation({
    // El usuario carga un porcentaje; se guarda como coeficiente (0..1).
    mutationFn: () => setCoeficiente(eId, Number(coefAct), String(Number(coefPct) / 100)),
    onSuccess: () => {
      setCoefAct('')
      setCoefPct('')
      setError(null)
      qc.invalidateQueries({ queryKey: ['actividades-coef', eId] })
    },
    onError: (e) => setError(apiError(e, 'No se pudo guardar el coeficiente.')),
  })
  const borrarCoef = useMutation({
    mutationFn: (id: number) => deleteCoeficiente(eId, id),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['actividades-coef', eId] }),
  })
  const sumaCoef = (coefs.data ?? []).reduce((a, c) => a + Number(c.coeficiente), 0)

  // Clasificación de ventas por PV + tipo de comprobante → cuenta (documento "Satélite Visual
  // IVA" §4, Pantalla D). No depende de ningún sujeto: el PV es del propio contribuyente.
  const mapearVentaPv = useMutation({
    mutationFn: () => setVentaPuntoVenta(eId, ventaPv2.trim(), Number(ventaPvCuenta)),
    onSuccess: () => {
      setVentaPv2('')
      setVentaPvCuenta('')
      setError(null)
      qc.invalidateQueries({ queryKey: ['venta-clasif-pv', eId] })
    },
    onError: (e) => setError(apiError(e, 'No se pudo guardar la regla.')),
  })
  const borrarVentaPv = useMutation({
    mutationFn: (id: number) => deleteVentaPuntoVenta(eId, id),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['venta-clasif-pv', eId] }),
  })
  const mapearVentaTipo = useMutation({
    mutationFn: () => setVentaPorTipo(eId, tipoPv.trim(), Number(tipoComprobanteId), Number(tipoCuenta)),
    onSuccess: () => {
      setTipoPv('')
      setTipoComprobanteId('')
      setTipoCuenta('')
      setError(null)
      qc.invalidateQueries({ queryKey: ['venta-clasif-tipo', eId] })
    },
    onError: (e) => setError(apiError(e, 'No se pudo guardar la excepción.')),
  })
  const borrarVentaTipo = useMutation({
    mutationFn: (id: number) => deleteVentaPorTipo(eId, id),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['venta-clasif-tipo', eId] }),
  })

  // Mapeo concepto→cuenta de esta empresa (documento "Satélite Visual IVA" §5.2/§5.4, migración
  // 0051): traduce el catálogo de conceptos (tenant-level, Utilidades → Conceptos) al plan de
  // cuentas real de esta empresa. Lo usan las reglas de imputación del proveedor (Pantalla B).
  const mapearConcepto = useMutation({
    mutationFn: () => setMapeoEmpresa(eId, Number(mapeoConceptoId), Number(mapeoCuentaId)),
    onSuccess: () => {
      setMapeoConceptoId('')
      setMapeoCuentaId('')
      setError(null)
      qc.invalidateQueries({ queryKey: ['mapeo-conceptos', eId] })
    },
    onError: (e) => setError(apiError(e, 'No se pudo guardar el mapeo.')),
  })
  const borrarConcepto = useMutation({
    mutationFn: (conceptoId: number) => deleteMapeoEmpresa(eId, conceptoId),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['mapeo-conceptos', eId] }),
  })

  return (
    <CCard>
      <CCardHeader>
        <Link to="/empresas" className="text-decoration-none small">
          ← Empresas
        </Link>
        <strong className="ms-2">Actividades (apertura de la DJ IVA por actividad)</strong>
      </CCardHeader>
      <CCardBody>
        {error && <CAlert color="danger">{error}</CAlert>}
        <CAlert color="info" className="small">
          Cargá las actividades (código NAES) de la empresa y, opcionalmente, mapeá cada punto de venta a
          una actividad. Al cargar una venta podés elegir la actividad directamente (override) o dejar que
          se resuelva por su punto de venta. La actividad define IIBB y tasa municipal, no el IVA.
        </CAlert>

        <CRow>
          <CCol md={6}>
            <h6>Actividades (NAES)</h6>
            <CForm
              className="row g-2 align-items-end mb-3"
              onSubmit={(e) => {
                e.preventDefault()
                if (codigo.trim()) crearAct.mutate()
              }}
            >
              <div className="col-auto">
                <CFormLabel className="small mb-1">Código NAES</CFormLabel>
                <CFormInput style={{ width: 120 }} value={codigo} onChange={(e) => setCodigo(e.target.value.replace(/\D/g, ''))} />
              </div>
              <div className="col">
                <CFormLabel className="small mb-1">Descripción</CFormLabel>
                <CFormInput value={descripcion} onChange={(e) => setDescripcion(e.target.value)} />
              </div>
              <div className="col-auto">
                <CButton type="submit" color="primary" disabled={crearAct.isPending || !codigo.trim()}>
                  Agregar
                </CButton>
              </div>
            </CForm>
            {actividades.isLoading && <CSpinner />}
            {actividades.data && (
              <CTable small hover responsive align="middle">
                <CTableHead>
                  <CTableRow>
                    <CTableHeaderCell>Código</CTableHeaderCell>
                    <CTableHeaderCell>Descripción</CTableHeaderCell>
                    <CTableHeaderCell />
                  </CTableRow>
                </CTableHead>
                <CTableBody>
                  {actividades.data.map((a) => (
                    <CTableRow key={a.id}>
                      <CTableDataCell>{a.codigo}</CTableDataCell>
                      <CTableDataCell>{a.descripcion ?? '—'}</CTableDataCell>
                      <CTableDataCell className="text-end">
                        <CButton color="danger" variant="ghost" size="sm" onClick={() => borrarAct.mutate(a.id)}>
                          ✕
                        </CButton>
                      </CTableDataCell>
                    </CTableRow>
                  ))}
                  {actividades.data.length === 0 && (
                    <CTableRow>
                      <CTableDataCell colSpan={3} className="text-center text-body-secondary py-3">
                        Sin actividades cargadas.
                      </CTableDataCell>
                    </CTableRow>
                  )}
                </CTableBody>
              </CTable>
            )}
          </CCol>

          <CCol md={6}>
            <h6>Mapa de puntos de venta → actividad</h6>
            <CForm
              className="row g-2 align-items-end mb-3"
              onSubmit={(e) => {
                e.preventDefault()
                if (pv.trim() && actividadId) mapearPv.mutate()
              }}
            >
              <div className="col-auto">
                <CFormLabel className="small mb-1">Punto de venta</CFormLabel>
                <CFormInput style={{ width: 110 }} value={pv} onChange={(e) => setPv(e.target.value.replace(/\D/g, ''))} />
              </div>
              <div className="col">
                <CFormLabel className="small mb-1">Actividad</CFormLabel>
                <CFormSelect value={actividadId} onChange={(e) => setActividadId(e.target.value)}>
                  <option value="">—</option>
                  {actividades.data?.map((a) => (
                    <option key={a.id} value={a.id}>
                      {a.codigo} — {a.descripcion}
                    </option>
                  ))}
                </CFormSelect>
              </div>
              <div className="col-auto">
                <CButton type="submit" color="primary" disabled={mapearPv.isPending || !pv.trim() || !actividadId}>
                  Mapear
                </CButton>
              </div>
            </CForm>
            {puntos.isLoading && <CSpinner />}
            {puntos.data && (
              <CTable small hover responsive align="middle">
                <CTableHead>
                  <CTableRow>
                    <CTableHeaderCell>PV</CTableHeaderCell>
                    <CTableHeaderCell>Actividad</CTableHeaderCell>
                    <CTableHeaderCell />
                  </CTableRow>
                </CTableHead>
                <CTableBody>
                  {puntos.data.map((p) => (
                    <CTableRow key={p.id}>
                      <CTableDataCell>{String(p.punto_venta).padStart(4, '0')}</CTableDataCell>
                      <CTableDataCell>
                        {p.actividad_codigo} — {p.actividad_descripcion}
                      </CTableDataCell>
                      <CTableDataCell className="text-end">
                        <CButton color="danger" variant="ghost" size="sm" onClick={() => borrarPv.mutate(p.id)}>
                          ✕
                        </CButton>
                      </CTableDataCell>
                    </CTableRow>
                  ))}
                  {puntos.data.length === 0 && (
                    <CTableRow>
                      <CTableDataCell colSpan={3} className="text-center text-body-secondary py-3">
                        Sin mapeos de punto de venta.
                      </CTableDataCell>
                    </CTableRow>
                  )}
                </CTableBody>
              </CTable>
            )}
          </CCol>
        </CRow>

        <hr />
        <div className="text-body-secondary small mb-3">
          Precedencia al resolver la actividad de una venta: <strong>actividad del comprobante</strong> →
          <strong> receptor</strong> → <strong>punto de venta</strong> → <strong>alícuota</strong> → primera actividad.
        </div>

        <CRow>
          <CCol md={6}>
            <h6>Por alícuota (construcción)</h6>
            <div className="text-body-secondary small mb-2">Ej.: 10,5% → residencial, 21% → no residencial.</div>
            <CForm
              className="row g-2 align-items-end mb-3"
              onSubmit={(e) => {
                e.preventDefault()
                if (alic.trim() && alicAct) mapearAlic.mutate()
              }}
            >
              <div className="col-auto">
                <CFormLabel className="small mb-1">Alícuota %</CFormLabel>
                <CFormInput style={{ width: 100 }} inputMode="decimal" value={alic} onChange={(e) => setAlic(e.target.value)} />
              </div>
              <div className="col">
                <CFormLabel className="small mb-1">Actividad</CFormLabel>
                <CFormSelect value={alicAct} onChange={(e) => setAlicAct(e.target.value)}>
                  <option value="">—</option>
                  {actividades.data?.map((a) => (
                    <option key={a.id} value={a.id}>
                      {a.codigo} — {a.descripcion}
                    </option>
                  ))}
                </CFormSelect>
              </div>
              <div className="col-auto">
                <CButton type="submit" color="primary" disabled={mapearAlic.isPending || !alic.trim() || !alicAct}>
                  Mapear
                </CButton>
              </div>
            </CForm>
            {alicuotas.data && (
              <CTable small hover responsive align="middle">
                <CTableHead>
                  <CTableRow>
                    <CTableHeaderCell>Alícuota</CTableHeaderCell>
                    <CTableHeaderCell>Actividad</CTableHeaderCell>
                    <CTableHeaderCell />
                  </CTableRow>
                </CTableHead>
                <CTableBody>
                  {alicuotas.data.map((a) => (
                    <CTableRow key={a.id}>
                      <CTableDataCell>{Number(a.alicuota)}%</CTableDataCell>
                      <CTableDataCell>{a.actividad_codigo} — {a.actividad_descripcion}</CTableDataCell>
                      <CTableDataCell className="text-end">
                        <CButton color="danger" variant="ghost" size="sm" onClick={() => borrarAlic.mutate(a.id)}>
                          ✕
                        </CButton>
                      </CTableDataCell>
                    </CTableRow>
                  ))}
                  {alicuotas.data.length === 0 && (
                    <CTableRow>
                      <CTableDataCell colSpan={3} className="text-center text-body-secondary py-3">
                        Sin mapeos por alícuota.
                      </CTableDataCell>
                    </CTableRow>
                  )}
                </CTableBody>
              </CTable>
            )}
          </CCol>

          <CCol md={6}>
            <h6>Por receptor (cliente)</h6>
            <div className="text-body-secondary small mb-2">Ej.: todo lo facturado a un CUIT va a una actividad.</div>
            <CForm
              className="row g-2 align-items-end mb-3"
              onSubmit={(e) => {
                e.preventDefault()
                if (clienteId && recAct) mapearRec.mutate()
              }}
            >
              <div className="col">
                <CFormLabel className="small mb-1">Cliente</CFormLabel>
                <CFormSelect value={clienteId} onChange={(e) => setClienteId(e.target.value)}>
                  <option value="">—</option>
                  {clientes.data?.map((c) => (
                    <option key={c.id} value={c.id}>
                      {c.nombre}
                    </option>
                  ))}
                </CFormSelect>
              </div>
              <div className="col">
                <CFormLabel className="small mb-1">Actividad</CFormLabel>
                <CFormSelect value={recAct} onChange={(e) => setRecAct(e.target.value)}>
                  <option value="">—</option>
                  {actividades.data?.map((a) => (
                    <option key={a.id} value={a.id}>
                      {a.codigo} — {a.descripcion}
                    </option>
                  ))}
                </CFormSelect>
              </div>
              <div className="col-auto">
                <CButton type="submit" color="primary" disabled={mapearRec.isPending || !clienteId || !recAct}>
                  Mapear
                </CButton>
              </div>
            </CForm>
            {receptores.data && (
              <CTable small hover responsive align="middle">
                <CTableHead>
                  <CTableRow>
                    <CTableHeaderCell>Cliente</CTableHeaderCell>
                    <CTableHeaderCell>Actividad</CTableHeaderCell>
                    <CTableHeaderCell />
                  </CTableRow>
                </CTableHead>
                <CTableBody>
                  {receptores.data.map((r) => (
                    <CTableRow key={r.id}>
                      <CTableDataCell>{r.cliente_nombre ?? `#${r.cliente_id}`}</CTableDataCell>
                      <CTableDataCell>{r.actividad_codigo} — {r.actividad_descripcion}</CTableDataCell>
                      <CTableDataCell className="text-end">
                        <CButton color="danger" variant="ghost" size="sm" onClick={() => borrarRec.mutate(r.id)}>
                          ✕
                        </CButton>
                      </CTableDataCell>
                    </CTableRow>
                  ))}
                  {receptores.data.length === 0 && (
                    <CTableRow>
                      <CTableDataCell colSpan={3} className="text-center text-body-secondary py-3">
                        Sin mapeos por receptor.
                      </CTableDataCell>
                    </CTableRow>
                  )}
                </CTableBody>
              </CTable>
            )}
          </CCol>
        </CRow>

        <hr />
        <h6>Por porcentajes fijos (reparto a nivel período)</h6>
        <div className="text-body-secondary small mb-2">
          Caso de un solo punto de venta que vende de todo: el neto del período se reparte entre las
          actividades por estos porcentajes. <strong>Si cargás coeficientes, esta estrategia tiene
          prioridad</strong> sobre las anteriores. Deben sumar 100%.
        </div>
        <CRow>
          <CCol md={7}>
            <CForm
              className="row g-2 align-items-end mb-3"
              onSubmit={(e) => {
                e.preventDefault()
                if (coefAct && coefPct) mapearCoef.mutate()
              }}
            >
              <div className="col">
                <CFormLabel className="small mb-1">Actividad</CFormLabel>
                <CFormSelect value={coefAct} onChange={(e) => setCoefAct(e.target.value)}>
                  <option value="">—</option>
                  {actividades.data?.map((a) => (
                    <option key={a.id} value={a.id}>
                      {a.codigo} — {a.descripcion}
                    </option>
                  ))}
                </CFormSelect>
              </div>
              <div className="col-auto">
                <CFormLabel className="small mb-1">Participación %</CFormLabel>
                <CFormInput style={{ width: 110 }} inputMode="decimal" value={coefPct} onChange={(e) => setCoefPct(e.target.value)} />
              </div>
              <div className="col-auto">
                <CButton type="submit" color="primary" disabled={mapearCoef.isPending || !coefAct || !coefPct}>
                  Guardar
                </CButton>
              </div>
            </CForm>
            {coefs.data && coefs.data.length > 0 && (
              <CTable small hover responsive align="middle">
                <CTableHead>
                  <CTableRow>
                    <CTableHeaderCell>Actividad</CTableHeaderCell>
                    <CTableHeaderCell className="text-end">Participación</CTableHeaderCell>
                    <CTableHeaderCell />
                  </CTableRow>
                </CTableHead>
                <CTableBody>
                  {coefs.data.map((c) => (
                    <CTableRow key={c.id}>
                      <CTableDataCell>{c.actividad_codigo} — {c.actividad_descripcion}</CTableDataCell>
                      <CTableDataCell className="text-end">{(Number(c.coeficiente) * 100).toFixed(2)}%</CTableDataCell>
                      <CTableDataCell className="text-end">
                        <CButton color="danger" variant="ghost" size="sm" onClick={() => borrarCoef.mutate(c.id)}>
                          ✕
                        </CButton>
                      </CTableDataCell>
                    </CTableRow>
                  ))}
                  <CTableRow className="fw-semibold">
                    <CTableDataCell className="text-end">Total</CTableDataCell>
                    <CTableDataCell className={`text-end ${Math.abs(sumaCoef - 1) < 0.0001 ? 'text-success' : 'text-danger'}`}>
                      {(sumaCoef * 100).toFixed(2)}%
                    </CTableDataCell>
                    <CTableDataCell />
                  </CTableRow>
                </CTableBody>
              </CTable>
            )}
            {coefs.data && coefs.data.length > 0 && Math.abs(sumaCoef - 1) >= 0.0001 && (
              <CAlert color="warning" className="small py-2">
                Los porcentajes no suman 100% (suman {(sumaCoef * 100).toFixed(2)}%). Ajustá antes de generar la DJ.
              </CAlert>
            )}
          </CCol>
        </CRow>

        <hr />
        <h6>Clasificación de ventas por cuenta contable (documento "Satélite Visual IVA")</h6>
        <div className="text-body-secondary small mb-3">
          A diferencia de las actividades de arriba, esto resuelve la <strong>cuenta contable</strong> que
          se precarga en las líneas de una venta (mayorización), no la actividad de IIBB. Precedencia:{' '}
          <strong>tipo de comprobante específico</strong> → <strong>regla general del punto de venta</strong>{' '}
          → sin regla (queda para cargar a mano).
        </div>
        <CRow>
          <CCol md={6}>
            <h6>Regla general por punto de venta</h6>
            <CForm
              className="row g-2 align-items-end mb-3"
              onSubmit={(e) => {
                e.preventDefault()
                if (ventaPv2.trim() && ventaPvCuenta) mapearVentaPv.mutate()
              }}
            >
              <div className="col-auto">
                <CFormLabel className="small mb-1">Punto de venta</CFormLabel>
                <CFormInput
                  style={{ width: 110 }}
                  value={ventaPv2}
                  onChange={(e) => setVentaPv2(e.target.value.replace(/\D/g, ''))}
                />
              </div>
              <div className="col">
                <CFormLabel className="small mb-1">Cuenta</CFormLabel>
                <CFormSelect value={ventaPvCuenta} onChange={(e) => setVentaPvCuenta(e.target.value)}>
                  <option value="">—</option>
                  {cuentas.data?.map((c) => (
                    <option key={c.id} value={c.id}>
                      {c.codigo ? `${c.codigo} — ${c.nombre}` : c.nombre}
                    </option>
                  ))}
                </CFormSelect>
              </div>
              <div className="col-auto">
                <CButton
                  type="submit"
                  color="primary"
                  disabled={mapearVentaPv.isPending || !ventaPv2.trim() || !ventaPvCuenta}
                >
                  Mapear
                </CButton>
              </div>
            </CForm>
            {ventaPv.isLoading && <CSpinner />}
            {ventaPv.data && (
              <CTable small hover responsive align="middle">
                <CTableHead>
                  <CTableRow>
                    <CTableHeaderCell>PV</CTableHeaderCell>
                    <CTableHeaderCell>Cuenta</CTableHeaderCell>
                    <CTableHeaderCell />
                  </CTableRow>
                </CTableHead>
                <CTableBody>
                  {ventaPv.data.map((p) => (
                    <CTableRow key={p.id}>
                      <CTableDataCell>{String(p.punto_venta).padStart(4, '0')}</CTableDataCell>
                      <CTableDataCell>
                        {p.cuenta_codigo ? `${p.cuenta_codigo} — ${p.cuenta_nombre}` : p.cuenta_nombre}
                      </CTableDataCell>
                      <CTableDataCell className="text-end">
                        <CButton color="danger" variant="ghost" size="sm" onClick={() => borrarVentaPv.mutate(p.id)}>
                          ✕
                        </CButton>
                      </CTableDataCell>
                    </CTableRow>
                  ))}
                  {ventaPv.data.length === 0 && (
                    <CTableRow>
                      <CTableDataCell colSpan={3} className="text-center text-body-secondary py-3">
                        Sin reglas cargadas.
                      </CTableDataCell>
                    </CTableRow>
                  )}
                </CTableBody>
              </CTable>
            )}
          </CCol>

          <CCol md={6}>
            <h6>Excepción por tipo de comprobante</h6>
            <div className="text-body-secondary small mb-2">
              Ej.: una Nota de Crédito del mismo PV se imputa distinto que una Factura.
            </div>
            <CForm
              className="row g-2 align-items-end mb-3"
              onSubmit={(e) => {
                e.preventDefault()
                if (tipoPv.trim() && tipoComprobanteId && tipoCuenta) mapearVentaTipo.mutate()
              }}
            >
              <div className="col-auto">
                <CFormLabel className="small mb-1">Punto de venta</CFormLabel>
                <CFormInput
                  style={{ width: 100 }}
                  value={tipoPv}
                  onChange={(e) => setTipoPv(e.target.value.replace(/\D/g, ''))}
                />
              </div>
              <div className="col">
                <CFormLabel className="small mb-1">Tipo de comprobante</CFormLabel>
                <CFormSelect value={tipoComprobanteId} onChange={(e) => setTipoComprobanteId(e.target.value)}>
                  <option value="">—</option>
                  {tiposComprobante.data?.map((t) => (
                    <option key={t.id} value={t.id}>
                      {t.nombre}
                    </option>
                  ))}
                </CFormSelect>
              </div>
              <div className="col">
                <CFormLabel className="small mb-1">Cuenta</CFormLabel>
                <CFormSelect value={tipoCuenta} onChange={(e) => setTipoCuenta(e.target.value)}>
                  <option value="">—</option>
                  {cuentas.data?.map((c) => (
                    <option key={c.id} value={c.id}>
                      {c.codigo ? `${c.codigo} — ${c.nombre}` : c.nombre}
                    </option>
                  ))}
                </CFormSelect>
              </div>
              <div className="col-auto">
                <CButton
                  type="submit"
                  color="primary"
                  disabled={mapearVentaTipo.isPending || !tipoPv.trim() || !tipoComprobanteId || !tipoCuenta}
                >
                  Mapear
                </CButton>
              </div>
            </CForm>
            {ventaTipo.data && (
              <CTable small hover responsive align="middle">
                <CTableHead>
                  <CTableRow>
                    <CTableHeaderCell>PV</CTableHeaderCell>
                    <CTableHeaderCell>Tipo</CTableHeaderCell>
                    <CTableHeaderCell>Cuenta</CTableHeaderCell>
                    <CTableHeaderCell />
                  </CTableRow>
                </CTableHead>
                <CTableBody>
                  {ventaTipo.data.map((t) => (
                    <CTableRow key={t.id}>
                      <CTableDataCell>{String(t.punto_venta).padStart(4, '0')}</CTableDataCell>
                      <CTableDataCell>{t.tipo_comprobante_nombre}</CTableDataCell>
                      <CTableDataCell>
                        {t.cuenta_codigo ? `${t.cuenta_codigo} — ${t.cuenta_nombre}` : t.cuenta_nombre}
                      </CTableDataCell>
                      <CTableDataCell className="text-end">
                        <CButton
                          color="danger"
                          variant="ghost"
                          size="sm"
                          onClick={() => borrarVentaTipo.mutate(t.id)}
                        >
                          ✕
                        </CButton>
                      </CTableDataCell>
                    </CTableRow>
                  ))}
                  {ventaTipo.data.length === 0 && (
                    <CTableRow>
                      <CTableDataCell colSpan={4} className="text-center text-body-secondary py-3">
                        Sin excepciones cargadas.
                      </CTableDataCell>
                    </CTableRow>
                  )}
                </CTableBody>
              </CTable>
            )}
          </CCol>
        </CRow>

        <hr />
        <h6>Mapeo de conceptos → cuentas (documento "Satélite Visual IVA" §5.2/§5.4)</h6>
        <div className="text-body-secondary small mb-3">
          Traduce el catálogo de conceptos del estudio (Utilidades → Conceptos) al plan de cuentas de{' '}
          <strong>esta empresa</strong>. Lo usan las reglas de imputación de los proveedores (botón
          "Imputación" en Proveedores): si un concepto no está mapeado acá, el comprobante queda sin cuenta
          hasta que se cargue.
        </div>
        <CForm
          className="row g-2 align-items-end mb-3"
          onSubmit={(e) => {
            e.preventDefault()
            if (mapeoConceptoId && mapeoCuentaId) mapearConcepto.mutate()
          }}
        >
          <div className="col-auto" style={{ minWidth: 220 }}>
            <CFormLabel className="small mb-1">Concepto</CFormLabel>
            <CFormSelect value={mapeoConceptoId} onChange={(e) => setMapeoConceptoId(e.target.value)}>
              <option value="">—</option>
              {conceptos.data?.map((c) => (
                <option key={c.id} value={c.id}>
                  {c.nombre}
                </option>
              ))}
            </CFormSelect>
          </div>
          <div className="col-auto" style={{ minWidth: 220 }}>
            <CFormLabel className="small mb-1">Cuenta</CFormLabel>
            <CFormSelect value={mapeoCuentaId} onChange={(e) => setMapeoCuentaId(e.target.value)}>
              <option value="">—</option>
              {cuentas.data?.map((c) => (
                <option key={c.id} value={c.id}>
                  {c.codigo ? `${c.codigo} — ${c.nombre}` : c.nombre}
                </option>
              ))}
            </CFormSelect>
          </div>
          <div className="col-auto">
            <CButton
              type="submit"
              color="primary"
              disabled={mapearConcepto.isPending || !mapeoConceptoId || !mapeoCuentaId}
            >
              Mapear
            </CButton>
          </div>
        </CForm>
        {mapeoConceptos.isLoading && <CSpinner />}
        {mapeoConceptos.data && (
          <CTable small hover responsive align="middle">
            <CTableHead>
              <CTableRow>
                <CTableHeaderCell>Concepto</CTableHeaderCell>
                <CTableHeaderCell>Cuenta</CTableHeaderCell>
                <CTableHeaderCell />
              </CTableRow>
            </CTableHead>
            <CTableBody>
              {mapeoConceptos.data.map((m) => (
                <CTableRow key={m.id}>
                  <CTableDataCell>{m.concepto_nombre}</CTableDataCell>
                  <CTableDataCell>
                    {m.cuenta_codigo ? `${m.cuenta_codigo} — ${m.cuenta_nombre}` : m.cuenta_nombre}
                  </CTableDataCell>
                  <CTableDataCell className="text-end">
                    <CButton
                      color="danger"
                      variant="ghost"
                      size="sm"
                      onClick={() => borrarConcepto.mutate(m.concepto_id)}
                    >
                      ✕
                    </CButton>
                  </CTableDataCell>
                </CTableRow>
              ))}
              {mapeoConceptos.data.length === 0 && (
                <CTableRow>
                  <CTableDataCell colSpan={3} className="text-center text-body-secondary py-3">
                    Sin conceptos mapeados.
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
