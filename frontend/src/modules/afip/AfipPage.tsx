import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import {
  CCard,
  CCardHeader,
  CCardBody,
  CForm,
  CFormInput,
  CFormLabel,
  CFormSelect,
  CButton,
  CRow,
  CCol,
  CTable,
  CTableHead,
  CTableRow,
  CTableHeaderCell,
  CTableBody,
  CTableDataCell,
  CAlert,
  CSpinner,
  CBadge,
} from '@coreui/react'
import {
  consultarPadron,
  listPuntosVenta,
  createPuntoVenta,
  deletePuntoVenta,
  type PersonaPadron,
  type PuntoVenta,
} from '../../api/afip'
import { listEmpresas } from '../../api/empresas'
import AfipAmbienteBanner from '../../components/AfipAmbienteBanner'

function apiError(e: unknown, fallback: string): string {
  const err = e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
  const data = err.response?.data
  if (data?.errors) {
    const first = Object.values(data.errors)[0]
    if (first?.[0]) return first[0]
  }
  return data?.message ?? fallback
}

function Padron() {
  const [cuit, setCuit] = useState('')
  const [persona, setPersona] = useState<PersonaPadron | null>(null)
  const [error, setError] = useState<string | null>(null)

  const consultar = useMutation({
    mutationFn: () => consultarPadron(cuit.trim()),
    onSuccess: (p) => {
      setPersona(p)
      setError(null)
    },
    onError: (e) => {
      setPersona(null)
      setError(apiError(e, 'No se pudo consultar el padrón. Verificá el CUIT y la conexión con ARCA.'))
    },
  })

  return (
    <CCard className="mb-4">
      <CCardHeader>
        <strong>Consulta de padrón (ARCA)</strong>
      </CCardHeader>
      <CCardBody>
        <CForm
          className="row g-2 align-items-end mb-3"
          onSubmit={(e) => {
            e.preventDefault()
            if (cuit.trim()) consultar.mutate()
          }}
        >
          <div className="col-auto">
            <CFormLabel className="small mb-1">CUIT</CFormLabel>
            <CFormInput
              value={cuit}
              onChange={(e) => setCuit(e.target.value.replace(/\D/g, ''))}
              maxLength={11}
              style={{ width: 200 }}
              placeholder="Sin guiones"
            />
          </div>
          <div className="col-auto">
            <CButton type="submit" color="primary" disabled={consultar.isPending || cuit.trim().length !== 11}>
              {consultar.isPending ? 'Consultando…' : 'Consultar'}
            </CButton>
          </div>
        </CForm>

        {error && <CAlert color="danger">{error}</CAlert>}

        {persona && (
          <CRow className="g-3">
            <CCol md={6}>
              <div className="text-body-secondary small">Denominación</div>
              <div className="fs-5">{persona.denominacion ?? '—'}</div>
            </CCol>
            <CCol md={3}>
              <div className="text-body-secondary small">CUIT</div>
              <div>{persona.cuit ?? '—'}</div>
            </CCol>
            <CCol md={3}>
              <div className="text-body-secondary small">Tipo / Estado</div>
              <div>
                {persona.tipo_persona ?? '—'}{' '}
                {persona.estado_clave && <CBadge color="info">{persona.estado_clave}</CBadge>}
              </div>
            </CCol>
            <CCol md={12}>
              <div className="text-body-secondary small">Domicilio</div>
              <div>
                {[persona.domicilio?.direccion, persona.domicilio?.localidad, persona.domicilio?.descripcionProvincia]
                  .filter(Boolean)
                  .join(', ') || '—'}
              </div>
            </CCol>
            {persona.impuestos && persona.impuestos.length > 0 && (
              <CCol md={12}>
                <div className="text-body-secondary small">Impuestos</div>
                <div className="d-flex flex-wrap gap-1">
                  {persona.impuestos.map((imp, i) => (
                    <CBadge color="secondary" key={i}>
                      {String(imp.descripcionImpuesto ?? imp.idImpuesto ?? '—')}
                    </CBadge>
                  ))}
                </div>
              </CCol>
            )}
          </CRow>
        )}
      </CCardBody>
    </CCard>
  )
}

function PuntosVenta() {
  const qc = useQueryClient()
  const { data: empresas } = useQuery({ queryKey: ['empresas'], queryFn: listEmpresas })
  const [empresaId, setEmpresaId] = useState<number | null>(null)
  const [numero, setNumero] = useState('')
  const [descripcion, setDescripcion] = useState('')
  const [error, setError] = useState<string | null>(null)

  const { data: puntos, isLoading } = useQuery({
    queryKey: ['puntos-venta', empresaId],
    queryFn: () => listPuntosVenta(empresaId as number),
    enabled: empresaId != null,
  })

  const invalidate = () => qc.invalidateQueries({ queryKey: ['puntos-venta', empresaId] })

  const createM = useMutation({
    mutationFn: () =>
      createPuntoVenta(empresaId as number, { numero: Number(numero), descripcion: descripcion || null }),
    onSuccess: () => {
      setNumero('')
      setDescripcion('')
      setError(null)
      invalidate()
    },
    onError: (e) => setError(apiError(e, 'No se pudo crear el punto de venta.')),
  })

  const deleteM = useMutation({
    mutationFn: (id: number) => deletePuntoVenta(empresaId as number, id),
    onSuccess: invalidate,
  })

  const onDelete = (pv: PuntoVenta) => {
    if (window.confirm(`¿Eliminar el punto de venta ${pv.numero}?`)) deleteM.mutate(pv.id)
  }

  return (
    <CCard>
      <CCardHeader className="d-flex flex-wrap justify-content-between align-items-center gap-2">
        <strong>Puntos de venta</strong>
        <CFormSelect
          size="sm"
          style={{ minWidth: 220 }}
          value={empresaId ?? ''}
          onChange={(e) => setEmpresaId(e.target.value ? Number(e.target.value) : null)}
        >
          <option value="">— Empresa —</option>
          {empresas?.map((em) => (
            <option key={em.id} value={em.id}>
              {em.nombre}
            </option>
          ))}
        </CFormSelect>
      </CCardHeader>
      <CCardBody>
        {empresaId == null ? (
          <div className="text-body-secondary py-3 text-center">Elegí una empresa para ver sus puntos de venta.</div>
        ) : (
          <>
            {error && <CAlert color="danger">{error}</CAlert>}
            <CForm
              className="row g-2 align-items-end mb-3"
              onSubmit={(e) => {
                e.preventDefault()
                if (numero) createM.mutate()
              }}
            >
              <div className="col-auto">
                <CFormLabel className="small mb-1">Número</CFormLabel>
                <CFormInput
                  inputMode="numeric"
                  style={{ width: 120 }}
                  value={numero}
                  onChange={(e) => setNumero(e.target.value.replace(/\D/g, ''))}
                />
              </div>
              <div className="col-auto">
                <CFormLabel className="small mb-1">Descripción</CFormLabel>
                <CFormInput
                  style={{ width: 260 }}
                  value={descripcion}
                  onChange={(e) => setDescripcion(e.target.value)}
                />
              </div>
              <div className="col-auto">
                <CButton type="submit" color="primary" disabled={createM.isPending || !numero}>
                  Agregar
                </CButton>
              </div>
            </CForm>

            {isLoading && <CSpinner />}
            {puntos && (
              <CTable hover responsive align="middle" className="mb-0">
                <CTableHead>
                  <CTableRow>
                    <CTableHeaderCell>Número</CTableHeaderCell>
                    <CTableHeaderCell>Descripción</CTableHeaderCell>
                    <CTableHeaderCell>Emisión</CTableHeaderCell>
                    <CTableHeaderCell>Estado</CTableHeaderCell>
                    <CTableHeaderCell className="text-end">Acciones</CTableHeaderCell>
                  </CTableRow>
                </CTableHead>
                <CTableBody>
                  {puntos.map((pv) => (
                    <CTableRow key={pv.id}>
                      <CTableDataCell>{String(pv.numero).padStart(4, '0')}</CTableDataCell>
                      <CTableDataCell>{pv.descripcion ?? '—'}</CTableDataCell>
                      <CTableDataCell>{pv.tipo_emision ?? 'CAE'}</CTableDataCell>
                      <CTableDataCell>
                        <CBadge color={pv.activo === 'S' ? 'success' : 'secondary'}>
                          {pv.activo === 'S' ? 'Activo' : 'Inactivo'}
                        </CBadge>
                      </CTableDataCell>
                      <CTableDataCell className="text-end">
                        <CButton color="danger" variant="outline" size="sm" onClick={() => onDelete(pv)}>
                          Eliminar
                        </CButton>
                      </CTableDataCell>
                    </CTableRow>
                  ))}
                  {puntos.length === 0 && (
                    <CTableRow>
                      <CTableDataCell colSpan={5} className="text-center text-body-secondary py-3">
                        Sin puntos de venta.
                      </CTableDataCell>
                    </CTableRow>
                  )}
                </CTableBody>
              </CTable>
            )}
          </>
        )}
      </CCardBody>
    </CCard>
  )
}

export default function AfipPage() {
  return (
    <>
      <h2 className="mb-4">AFIP / Factura electrónica</h2>
      <AfipAmbienteBanner />
      <CAlert color="info" className="small">
        La consulta de padrón y la emisión de CAE usan los web services de ARCA y requieren el
        certificado configurado en el backend (homologación o producción). Sin certificado válido,
        ARCA rechaza la conexión y vas a ver un error de autenticación.
      </CAlert>
      <Padron />
      <PuntosVenta />
    </>
  )
}
