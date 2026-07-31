import { useState } from 'react'
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
  CSpinner,
  CAlert,
} from '@coreui/react'
import { listSujetos } from '../../../api/sujetos'
import { listCuentas } from '../../../api/cuentas'
import { listImputacion, setImputacion, deleteImputacion } from '../../../api/imputacionContable'

/**
 * Reglas de imputación contable por punto de venta del proveedor (documento "Satélite Visual
 * IVA" §5.4, Pantalla B). Página aparte (decisión B2) en vez de una vista de detalle de
 * proveedor nueva — se llega acá desde el botón "Imputación" en el listado de proveedores.
 * Precedencia: punto de venta específico → cuenta por defecto del proveedor (se administra en
 * el modal de alta/edición) → sin regla (queda para cargar a mano, ver bandeja de pendientes).
 */
export default function ProveedorImputacionPage() {
  const { empresaId, proveedorId } = useParams()
  const eId = Number(empresaId)
  const pId = Number(proveedorId)
  const qc = useQueryClient()

  const [pv, setPv] = useState('')
  const [cuentaId, setCuentaId] = useState('')

  const proveedores = useQuery({
    queryKey: ['proveedores', eId],
    queryFn: () => listSujetos('proveedores', eId),
  })
  const proveedor = proveedores.data?.find((s) => s.id === pId)

  const cuentas = useQuery({ queryKey: ['cuentas', eId], queryFn: () => listCuentas(eId) })

  const queryKey = ['imputacion-pv', eId, pId]
  const reglas = useQuery({ queryKey, queryFn: () => listImputacion(eId, pId) })
  const invalidate = () => qc.invalidateQueries({ queryKey })

  const mapear = useMutation({
    mutationFn: () => setImputacion(eId, pId, pv.trim(), Number(cuentaId)),
    onSuccess: () => {
      invalidate()
      setPv('')
      setCuentaId('')
    },
  })
  const borrar = useMutation({ mutationFn: (id: number) => deleteImputacion(eId, pId, id), onSuccess: invalidate })

  return (
    <CCard>
      <CCardHeader>
        <Link to={`/empresas/${eId}/proveedores`} className="text-decoration-none small">
          ← Proveedores
        </Link>
        <div className="d-flex align-items-center mt-1">
          <strong>Imputación contable por punto de venta</strong>
          {proveedor && (
            <span className="text-body-secondary ms-2">
              — {proveedor.nombre} (CUIT {proveedor.cuit})
            </span>
          )}
        </div>
      </CCardHeader>
      <CCardBody>
        <div className="text-body-secondary small mb-3">
          Excepción a la cuenta por defecto del proveedor (se edita desde el propio proveedor): cuando factura
          por un punto de venta específico con otra cuenta contable — ej. un proveedor que factura conceptos
          distintos según la sucursal/PV que usa.
        </div>

        {proveedores.isLoading && <CSpinner />}
        {!proveedores.isLoading && !proveedor && (
          <CAlert color="warning">No se encontró el proveedor en esta empresa.</CAlert>
        )}

        <CForm
          className="row g-2 align-items-end mb-3"
          onSubmit={(e) => {
            e.preventDefault()
            if (pv.trim() && cuentaId) mapear.mutate()
          }}
        >
          <div className="col-auto">
            <CFormLabel className="small mb-1">Punto de venta</CFormLabel>
            <CFormInput style={{ width: 110 }} value={pv} onChange={(e) => setPv(e.target.value.replace(/\D/g, ''))} />
          </div>
          <div className="col-auto" style={{ minWidth: 280 }}>
            <CFormLabel className="small mb-1">Cuenta</CFormLabel>
            <CFormSelect value={cuentaId} onChange={(e) => setCuentaId(e.target.value)}>
              <option value="">—</option>
              {cuentas.data?.map((c) => (
                <option key={c.id} value={c.id}>
                  {c.codigo ? `${c.codigo} — ${c.nombre}` : c.nombre}
                </option>
              ))}
            </CFormSelect>
          </div>
          <div className="col-auto">
            <CButton type="submit" color="primary" disabled={mapear.isPending || !pv.trim() || !cuentaId}>
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
                <CTableHeaderCell>Cuenta</CTableHeaderCell>
                <CTableHeaderCell />
              </CTableRow>
            </CTableHead>
            <CTableBody>
              {reglas.data.map((r) => (
                <CTableRow key={r.id}>
                  <CTableDataCell>{String(r.punto_venta).padStart(4, '0')}</CTableDataCell>
                  <CTableDataCell>
                    {r.cuenta_codigo ? `${r.cuenta_codigo} — ${r.cuenta_nombre}` : r.cuenta_nombre}
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
                  <CTableDataCell colSpan={3} className="text-center text-body-secondary py-3">
                    Sin reglas cargadas.
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
