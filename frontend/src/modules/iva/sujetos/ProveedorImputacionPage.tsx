import { useState, useEffect } from 'react'
import { useParams, Link } from 'react-router-dom'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import {
  CCard,
  CCardHeader,
  CCardBody,
  CButton,
  CForm,
  CFormLabel,
  CFormInput,
  CFormSelect,
  CTable,
  CTableHead,
  CTableRow,
  CTableHeaderCell,
  CTableBody,
  CTableDataCell,
  CBadge,
  CSpinner,
  CAlert,
  CRow,
  CCol,
} from '@coreui/react'
import { listSujetos } from '../../../api/sujetos'
import { listConceptos, type Concepto } from '../../../api/conceptos'
import {
  listReglasGlobales,
  setReglaGlobal,
  deleteReglaGlobal,
  listReglasEmpresa,
  setReglaEmpresa,
  deleteReglaEmpresa,
  getConceptoExcepcion,
  setConceptoExcepcion,
  type ReglaPuntoVenta,
} from '../../../api/imputacionContable'

/**
 * Reglas de imputación contable del proveedor (documento "Satélite Visual IVA" §5.4, Pantalla
 * B). Página aparte (decisión B2). Migración 0051: 3 secciones — regla GLOBAL de punto de venta
 * (aplica a todas las empresas del estudio), excepción de esa regla para ESTA empresa, y
 * excepción del concepto por defecto para ESTA empresa. El mapeo concepto→cuenta de esta empresa
 * se administra en Actividades (no depende de ningún proveedor puntual).
 */
export default function ProveedorImputacionPage() {
  const { empresaId, proveedorId } = useParams()
  const eId = Number(empresaId)
  const pId = Number(proveedorId)

  const proveedores = useQuery({
    queryKey: ['proveedores', eId],
    queryFn: () => listSujetos('proveedores', eId),
  })
  const proveedor = proveedores.data?.find((s) => s.id === pId)
  const conceptos = useQuery({ queryKey: ['conceptos'], queryFn: () => listConceptos() })

  return (
    <CCard>
      <CCardHeader>
        <Link to={`/empresas/${eId}/proveedores`} className="text-decoration-none small">
          ← Proveedores
        </Link>
        <div className="d-flex align-items-center mt-1">
          <strong>Imputación contable</strong>
          {proveedor && (
            <span className="text-body-secondary ms-2">
              — {proveedor.nombre} (CUIT {proveedor.cuit})
            </span>
          )}
        </div>
      </CCardHeader>
      <CCardBody>
        {proveedores.isLoading && <CSpinner />}
        {!proveedores.isLoading && !proveedor && (
          <CAlert color="warning">No se encontró el proveedor en esta empresa.</CAlert>
        )}

        <ReglaConceptoDefault empresaId={eId} proveedorId={pId} conceptos={conceptos.data ?? []} />
        <hr />
        <CRow>
          <CCol md={6}>
            <ReglaPuntoVentaSeccion
              titulo="Regla global de punto de venta"
              ayuda="Aplica a todas las empresas del estudio donde este proveedor factura (documento §5.4, caso MUCHAY SRL)."
              conceptos={conceptos.data ?? []}
              queryKey={['imputacion-global', eId, pId]}
              list={() => listReglasGlobales(eId, pId)}
              set={(pv, c) => setReglaGlobal(eId, pId, pv, c)}
              del={(id) => deleteReglaGlobal(eId, pId, id)}
            />
          </CCol>
          <CCol md={6}>
            <ReglaPuntoVentaSeccion
              titulo="Excepción de punto de venta para esta empresa"
              ayuda="Pisa la regla global solo para esta empresa (ej. el mismo PV se imputa distinto acá)."
              conceptos={conceptos.data ?? []}
              queryKey={['imputacion-empresa', eId, pId]}
              list={() => listReglasEmpresa(eId, pId)}
              set={(pv, c) => setReglaEmpresa(eId, pId, pv, c)}
              del={(id) => deleteReglaEmpresa(eId, pId, id)}
            />
          </CCol>
        </CRow>
      </CCardBody>
    </CCard>
  )
}

function ReglaConceptoDefault({
  empresaId,
  proveedorId,
  conceptos,
}: {
  empresaId: number
  proveedorId: number
  conceptos: Concepto[]
}) {
  const qc = useQueryClient()
  const [valor, setValor] = useState('')
  const queryKey = ['imputacion-concepto-default', empresaId, proveedorId]
  const query = useQuery({ queryKey, queryFn: () => getConceptoExcepcion(empresaId, proveedorId) })

  useEffect(() => {
    setValor(query.data != null ? String(query.data) : '')
  }, [query.data])

  const guardar = useMutation({
    mutationFn: (conceptoId: number | null) => setConceptoExcepcion(empresaId, proveedorId, conceptoId),
    onSuccess: () => qc.invalidateQueries({ queryKey }),
  })

  return (
    <div>
      <h6>Excepción del concepto por defecto para esta empresa</h6>
      <div className="text-body-secondary small mb-2">
        El proveedor tiene un concepto por defecto global (se edita desde su alta/edición). Acá se puede
        excepcionar solo para esta empresa (documento §5.2: mismo proveedor, distinto tratamiento contable según
        quién compra).
      </div>
      <div className="row g-2 align-items-end mb-3">
        <div className="col-auto" style={{ minWidth: 280 }}>
          <CFormSelect
            value={valor}
            onChange={(e) => {
              setValor(e.target.value)
              guardar.mutate(e.target.value ? Number(e.target.value) : null)
            }}
          >
            <option value="">— Usar el default global del proveedor —</option>
            {conceptos.map((c) => (
              <option key={c.id} value={c.id}>
                {c.nombre}
              </option>
            ))}
          </CFormSelect>
        </div>
        {guardar.isPending && <CSpinner size="sm" className="col-auto" />}
      </div>
    </div>
  )
}

function ReglaPuntoVentaSeccion({
  titulo,
  ayuda,
  conceptos,
  queryKey,
  list,
  set,
  del,
}: {
  titulo: string
  ayuda: string
  conceptos: Concepto[]
  queryKey: unknown[]
  list: () => Promise<ReglaPuntoVenta[]>
  set: (puntoVenta: string, conceptoId: number) => Promise<void>
  del: (id: number) => Promise<void>
}) {
  const qc = useQueryClient()
  const [pv, setPv] = useState('')
  const [conceptoId, setConceptoId] = useState('')
  const reglas = useQuery({ queryKey, queryFn: list })
  const invalidate = () => qc.invalidateQueries({ queryKey })

  const mapear = useMutation({
    mutationFn: () => set(pv.trim(), Number(conceptoId)),
    onSuccess: () => {
      invalidate()
      setPv('')
      setConceptoId('')
    },
  })
  const borrar = useMutation({ mutationFn: (id: number) => del(id), onSuccess: invalidate })

  return (
    <div>
      <h6>{titulo}</h6>
      <div className="text-body-secondary small mb-2">{ayuda}</div>
      <CForm
        className="row g-2 align-items-end mb-3"
        onSubmit={(e) => {
          e.preventDefault()
          if (pv.trim() && conceptoId) mapear.mutate()
        }}
      >
        <div className="col-auto">
          <CFormLabel className="small mb-1">Punto de venta</CFormLabel>
          <CFormInput style={{ width: 110 }} value={pv} onChange={(e) => setPv(e.target.value.replace(/\D/g, ''))} />
        </div>
        <div className="col-auto" style={{ minWidth: 220 }}>
          <CFormLabel className="small mb-1">Concepto</CFormLabel>
          <CFormSelect value={conceptoId} onChange={(e) => setConceptoId(e.target.value)}>
            <option value="">—</option>
            {conceptos.map((c) => (
              <option key={c.id} value={c.id}>
                {c.nombre}
              </option>
            ))}
          </CFormSelect>
        </div>
        <div className="col-auto">
          <CButton type="submit" color="primary" disabled={mapear.isPending || !pv.trim() || !conceptoId}>
            Mapear
          </CButton>
        </div>
      </CForm>

      {reglas.isLoading && <CSpinner />}
      {reglas.isError && <CAlert color="danger">No se pudieron cargar las reglas.</CAlert>}
      {reglas.data && (
        <CTable small hover responsive align="middle">
          <CTableHead>
            <CTableRow>
              <CTableHeaderCell>PV</CTableHeaderCell>
              <CTableHeaderCell>Concepto</CTableHeaderCell>
              <CTableHeaderCell>Cuenta (esta empresa)</CTableHeaderCell>
              <CTableHeaderCell />
            </CTableRow>
          </CTableHead>
          <CTableBody>
            {reglas.data.map((r) => (
              <CTableRow key={r.id}>
                <CTableDataCell>{String(r.punto_venta).padStart(4, '0')}</CTableDataCell>
                <CTableDataCell>{r.concepto_nombre}</CTableDataCell>
                <CTableDataCell>
                  {r.cuenta_id ? (
                    r.cuenta_codigo ? (
                      `${r.cuenta_codigo} — ${r.cuenta_nombre}`
                    ) : (
                      r.cuenta_nombre
                    )
                  ) : (
                    <CBadge color="warning">sin mapear</CBadge>
                  )}
                </CTableDataCell>
                <CTableDataCell className="text-end">
                  <CButton color="danger" variant="ghost" size="sm" onClick={() => borrar.mutate(r.id)}>
                    ✕
                  </CButton>
                </CTableDataCell>
              </CTableRow>
            ))}
            {reglas.data.length === 0 && (
              <CTableRow>
                <CTableDataCell colSpan={4} className="text-center text-body-secondary py-3">
                  Sin reglas cargadas.
                </CTableDataCell>
              </CTableRow>
            )}
          </CTableBody>
        </CTable>
      )}
    </div>
  )
}
