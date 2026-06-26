import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import {
  CButton,
  CForm,
  CFormInput,
  CFormLabel,
  CTable,
  CTableHead,
  CTableRow,
  CTableHeaderCell,
  CTableBody,
  CTableDataCell,
  CSpinner,
  CAlert,
  CBadge,
} from '@coreui/react'
import {
  listLiquidaciones,
  createLiquidacion,
  deleteLiquidacion,
  type Liquidacion,
} from '../../api/sueldos'
import LiquidarModal from './LiquidarModal'
import { apiError } from './shared'

export default function LiquidacionesTab({ empresaId }: { empresaId: number }) {
  const qc = useQueryClient()
  const [periodo, setPeriodo] = useState('')
  const [descripcion, setDescripcion] = useState('')
  const [fechaPago, setFechaPago] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [liquidando, setLiquidando] = useState<Liquidacion | null>(null)

  const { data, isLoading, isError } = useQuery({
    queryKey: ['liquidaciones', empresaId],
    queryFn: () => listLiquidaciones(empresaId),
  })
  const invalidate = () => qc.invalidateQueries({ queryKey: ['liquidaciones', empresaId] })

  const createM = useMutation({
    mutationFn: () =>
      createLiquidacion(empresaId, {
        periodo_liquidado: periodo,
        descripcion: descripcion || null,
        fecha_pago: fechaPago || null,
      }),
    onSuccess: () => {
      setPeriodo('')
      setDescripcion('')
      setFechaPago('')
      setError(null)
      invalidate()
    },
    onError: (e) => setError(apiError(e, 'No se pudo crear la liquidación.')),
  })
  const deleteM = useMutation({ mutationFn: (id: number) => deleteLiquidacion(empresaId, id), onSuccess: invalidate })

  const onDelete = (l: Liquidacion) => {
    if (window.confirm(`¿Eliminar la liquidación ${l.periodo_liquidado}?`)) deleteM.mutate(l.id)
  }

  return (
    <>
      {error && <CAlert color="danger">{error}</CAlert>}
      <CForm
        className="row g-2 align-items-end mb-3"
        onSubmit={(e) => {
          e.preventDefault()
          if (periodo) createM.mutate()
        }}
      >
        <div className="col-auto">
          <CFormLabel className="small mb-1">Período (ej: 2026-01)</CFormLabel>
          <CFormInput style={{ width: 150 }} value={periodo} onChange={(e) => setPeriodo(e.target.value)} />
        </div>
        <div className="col-auto">
          <CFormLabel className="small mb-1">Descripción</CFormLabel>
          <CFormInput style={{ width: 240 }} value={descripcion} onChange={(e) => setDescripcion(e.target.value)} />
        </div>
        <div className="col-auto">
          <CFormLabel className="small mb-1">Fecha de pago</CFormLabel>
          <CFormInput type="date" value={fechaPago} onChange={(e) => setFechaPago(e.target.value)} />
        </div>
        <div className="col-auto">
          <CButton type="submit" color="primary" disabled={createM.isPending || !periodo}>
            Nueva liquidación
          </CButton>
        </div>
      </CForm>

      {isLoading && <CSpinner />}
      {isError && <CAlert color="danger">No se pudieron cargar las liquidaciones.</CAlert>}
      {data && (
        <CTable hover responsive align="middle" className="mb-0">
          <CTableHead>
            <CTableRow>
              <CTableHeaderCell>Período</CTableHeaderCell>
              <CTableHeaderCell>Descripción</CTableHeaderCell>
              <CTableHeaderCell>Fecha de pago</CTableHeaderCell>
              <CTableHeaderCell>Estado</CTableHeaderCell>
              <CTableHeaderCell className="text-end">Acciones</CTableHeaderCell>
            </CTableRow>
          </CTableHead>
          <CTableBody>
            {data.map((l) => (
              <CTableRow key={l.id}>
                <CTableDataCell>{l.periodo_liquidado}</CTableDataCell>
                <CTableDataCell>{l.descripcion ?? '—'}</CTableDataCell>
                <CTableDataCell>{l.fecha_pago ?? '—'}</CTableDataCell>
                <CTableDataCell>
                  <CBadge color={l.bloqueada === 'S' ? 'secondary' : 'success'}>
                    {l.bloqueada === 'S' ? 'Bloqueada' : 'Abierta'}
                  </CBadge>
                </CTableDataCell>
                <CTableDataCell className="text-end">
                  <CButton
                    color="primary"
                    variant="outline"
                    size="sm"
                    className="me-2"
                    onClick={() => setLiquidando(l)}
                  >
                    Liquidar
                  </CButton>
                  <CButton color="danger" variant="outline" size="sm" onClick={() => onDelete(l)}>
                    Eliminar
                  </CButton>
                </CTableDataCell>
              </CTableRow>
            ))}
            {data.length === 0 && (
              <CTableRow>
                <CTableDataCell colSpan={5} className="text-center text-body-secondary py-4">
                  Sin liquidaciones.
                </CTableDataCell>
              </CTableRow>
            )}
          </CTableBody>
        </CTable>
      )}

      {liquidando && (
        <LiquidarModal
          visible
          empresaId={empresaId}
          liquidacion={liquidando}
          onClose={() => setLiquidando(null)}
        />
      )}
    </>
  )
}
