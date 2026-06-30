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
      </CCardBody>
    </CCard>
  )
}
