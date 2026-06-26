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
} from '../../../api/actividades'

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

  const [codigo, setCodigo] = useState('')
  const [descripcion, setDescripcion] = useState('')
  const [pv, setPv] = useState('')
  const [actividadId, setActividadId] = useState('')
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
      </CCardBody>
    </CCard>
  )
}
