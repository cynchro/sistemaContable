import { useEffect, useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import {
  CButton,
  CForm,
  CFormInput,
  CFormLabel,
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
import {
  listVencimientos,
  createVencimiento,
  deleteVencimiento,
  cambiarEstadoVencimiento,
  VENCIMIENTO_ESTADOS,
} from '../../api/gestion'
import { listEmpresas } from '../../api/empresas'
import { apiError, humaniza } from './shared'

export default function VencimientosTab() {
  const qc = useQueryClient()
  const { data: empresas } = useQuery({ queryKey: ['empresas'], queryFn: listEmpresas })
  const [empresaId, setEmpresaId] = useState<number | null>(null)
  const [form, setForm] = useState({ titulo: '', agencia: '', fecha_vencimiento: '' })
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    if (empresaId == null && empresas && empresas.length > 0) setEmpresaId(empresas[0].id)
  }, [empresas, empresaId])

  const { data, isLoading } = useQuery({
    queryKey: ['vencimientos', empresaId],
    queryFn: () => listVencimientos(empresaId as number),
    enabled: empresaId != null,
  })
  const invalidate = () => qc.invalidateQueries({ queryKey: ['vencimientos', empresaId] })

  const createM = useMutation({
    mutationFn: () =>
      createVencimiento(empresaId as number, {
        titulo: form.titulo,
        agencia: form.agencia || null,
        fecha_vencimiento: form.fecha_vencimiento || null,
      }),
    onSuccess: () => {
      setForm({ titulo: '', agencia: '', fecha_vencimiento: '' })
      setError(null)
      invalidate()
    },
    onError: (e) => setError(apiError(e, 'No se pudo crear el vencimiento.')),
  })
  const estadoM = useMutation({
    mutationFn: ({ id, estado }: { id: number; estado: string }) =>
      cambiarEstadoVencimiento(empresaId as number, id, estado),
    onSuccess: invalidate,
  })
  const deleteM = useMutation({
    mutationFn: (id: number) => deleteVencimiento(empresaId as number, id),
    onSuccess: invalidate,
  })

  return (
    <>
      <div className="mb-3" style={{ maxWidth: 300 }}>
        <CFormSelect
          size="sm"
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
      </div>

      {empresaId == null ? (
        <div className="text-body-secondary py-3 text-center">Elegí una empresa.</div>
      ) : (
        <>
          {error && <CAlert color="danger">{error}</CAlert>}
          <CForm
            className="row g-2 align-items-end mb-3"
            onSubmit={(e) => {
              e.preventDefault()
              if (form.titulo) createM.mutate()
            }}
          >
            <div className="col-auto">
              <CFormLabel className="small mb-1">Obligación</CFormLabel>
              <CFormInput style={{ width: 260 }} value={form.titulo} onChange={(e) => setForm({ ...form, titulo: e.target.value })} />
            </div>
            <div className="col-auto">
              <CFormLabel className="small mb-1">Agencia</CFormLabel>
              <CFormInput style={{ width: 140 }} value={form.agencia} onChange={(e) => setForm({ ...form, agencia: e.target.value })} />
            </div>
            <div className="col-auto">
              <CFormLabel className="small mb-1">Vencimiento</CFormLabel>
              <CFormInput type="date" value={form.fecha_vencimiento} onChange={(e) => setForm({ ...form, fecha_vencimiento: e.target.value })} />
            </div>
            <div className="col-auto">
              <CButton type="submit" color="primary" disabled={createM.isPending || !form.titulo}>
                Agregar
              </CButton>
            </div>
          </CForm>

          {isLoading && <CSpinner />}
          {data && (
            <CTable hover responsive align="middle" className="mb-0">
              <CTableHead>
                <CTableRow>
                  <CTableHeaderCell>Obligación</CTableHeaderCell>
                  <CTableHeaderCell>Agencia</CTableHeaderCell>
                  <CTableHeaderCell>Vence</CTableHeaderCell>
                  <CTableHeaderCell style={{ width: 230 }}>Estado</CTableHeaderCell>
                  <CTableHeaderCell className="text-end">Acciones</CTableHeaderCell>
                </CTableRow>
              </CTableHead>
              <CTableBody>
                {data.map((v) => (
                  <CTableRow key={v.id}>
                    <CTableDataCell>{v.titulo}</CTableDataCell>
                    <CTableDataCell>{v.agencia ?? '—'}</CTableDataCell>
                    <CTableDataCell>{v.fecha_vencimiento ?? '—'}</CTableDataCell>
                    <CTableDataCell>
                      <CFormSelect
                        size="sm"
                        value={v.estado}
                        onChange={(e) => estadoM.mutate({ id: v.id, estado: e.target.value })}
                      >
                        {VENCIMIENTO_ESTADOS.map((s) => (
                          <option key={s} value={s}>
                            {humaniza(s)}
                          </option>
                        ))}
                      </CFormSelect>
                    </CTableDataCell>
                    <CTableDataCell className="text-end">
                      <CButton color="danger" variant="outline" size="sm" onClick={() => deleteM.mutate(v.id)}>
                        Eliminar
                      </CButton>
                    </CTableDataCell>
                  </CTableRow>
                ))}
                {data.length === 0 && (
                  <CTableRow>
                    <CTableDataCell colSpan={5} className="text-center text-body-secondary py-4">
                      Sin vencimientos.
                    </CTableDataCell>
                  </CTableRow>
                )}
              </CTableBody>
            </CTable>
          )}
        </>
      )}
    </>
  )
}
