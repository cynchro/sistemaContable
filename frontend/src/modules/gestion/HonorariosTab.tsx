import { useEffect, useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import {
  CButton,
  CModal,
  CModalHeader,
  CModalTitle,
  CModalBody,
  CModalFooter,
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
  listHonorarios,
  createHonorario,
  deleteHonorario,
  listServicios,
  listFactores,
} from '../../api/gestion'
import { listEmpresas } from '../../api/empresas'
import { apiError, money } from './shared'

interface LineaForm {
  servicio_id: string
  complejidad: string
  cantidad: string
}

export default function HonorariosTab() {
  const qc = useQueryClient()
  const { data: empresas } = useQuery({ queryKey: ['empresas'], queryFn: listEmpresas })
  const { data: servicios } = useQuery({ queryKey: ['servicios'], queryFn: listServicios })
  const { data: factores } = useQuery({ queryKey: ['factores'], queryFn: listFactores })
  const [empresaId, setEmpresaId] = useState<number | null>(null)
  const [open, setOpen] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [valorUc, setValorUc] = useState('')
  const [fecha, setFecha] = useState('')
  const [descripcion, setDescripcion] = useState('')
  const [lineas, setLineas] = useState<LineaForm[]>([{ servicio_id: '', complejidad: '', cantidad: '1' }])

  useEffect(() => {
    if (empresaId == null && empresas && empresas.length > 0) setEmpresaId(empresas[0].id)
  }, [empresas, empresaId])

  const { data, isLoading } = useQuery({
    queryKey: ['honorarios', empresaId],
    queryFn: () => listHonorarios(empresaId as number),
    enabled: empresaId != null,
  })
  const invalidate = () => qc.invalidateQueries({ queryKey: ['honorarios', empresaId] })

  const reset = () => {
    setValorUc('')
    setFecha('')
    setDescripcion('')
    setLineas([{ servicio_id: '', complejidad: '', cantidad: '1' }])
  }

  const createM = useMutation({
    mutationFn: () =>
      createHonorario(empresaId as number, {
        valor_uc: valorUc || '0',
        fecha: fecha || null,
        descripcion: descripcion || null,
        lineas: lineas
          .filter((l) => l.servicio_id)
          .map((l) => ({ servicio_id: Number(l.servicio_id), complejidad: l.complejidad || null, cantidad: l.cantidad || '1' })),
      }),
    onSuccess: () => {
      setOpen(false)
      reset()
      invalidate()
    },
    onError: (e) => setError(apiError(e, 'No se pudo crear el honorario.')),
  })
  const deleteM = useMutation({
    mutationFn: (id: number) => deleteHonorario(empresaId as number, id),
    onSuccess: invalidate,
  })

  const setLinea = (i: number, patch: Partial<LineaForm>) =>
    setLineas((ls) => ls.map((l, j) => (j === i ? { ...l, ...patch } : l)))

  return (
    <>
      <div className="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <CFormSelect
          size="sm"
          style={{ maxWidth: 300 }}
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
        {empresaId != null && (
          <CButton color="primary" size="sm" onClick={() => { setError(null); setOpen(true) }}>
            Nuevo honorario
          </CButton>
        )}
      </div>

      {empresaId == null ? (
        <div className="text-body-secondary py-3 text-center">Elegí una empresa.</div>
      ) : (
        <>
          {isLoading && <CSpinner />}
          {data && (
            <CTable hover responsive align="middle" className="mb-0">
              <CTableHead>
                <CTableRow>
                  <CTableHeaderCell>Fecha</CTableHeaderCell>
                  <CTableHeaderCell>Descripción</CTableHeaderCell>
                  <CTableHeaderCell className="text-end">Valor UC</CTableHeaderCell>
                  <CTableHeaderCell className="text-end">Total</CTableHeaderCell>
                  <CTableHeaderCell className="text-end">Acciones</CTableHeaderCell>
                </CTableRow>
              </CTableHead>
              <CTableBody>
                {data.map((h) => (
                  <CTableRow key={h.id}>
                    <CTableDataCell>{h.fecha ?? '—'}</CTableDataCell>
                    <CTableDataCell>{h.descripcion ?? '—'}</CTableDataCell>
                    <CTableDataCell className="text-end">{money(h.valor_uc)}</CTableDataCell>
                    <CTableDataCell className="text-end fw-semibold">{money(h.total)}</CTableDataCell>
                    <CTableDataCell className="text-end">
                      <CButton color="danger" variant="outline" size="sm" onClick={() => deleteM.mutate(h.id)}>
                        Eliminar
                      </CButton>
                    </CTableDataCell>
                  </CTableRow>
                ))}
                {data.length === 0 && (
                  <CTableRow>
                    <CTableDataCell colSpan={5} className="text-center text-body-secondary py-4">
                      Sin honorarios.
                    </CTableDataCell>
                  </CTableRow>
                )}
              </CTableBody>
            </CTable>
          )}
        </>
      )}

      <CModal visible={open} onClose={() => setOpen(false)} alignment="center" size="lg">
        <CModalHeader>
          <CModalTitle>Nuevo honorario</CModalTitle>
        </CModalHeader>
        <CForm
          onSubmit={(e) => {
            e.preventDefault()
            createM.mutate()
          }}
        >
          <CModalBody>
            {error && <CAlert color="danger">{error}</CAlert>}
            <div className="row">
              <div className="col-md-4 mb-3">
                <CFormLabel>Valor UC</CFormLabel>
                <CFormInput inputMode="decimal" value={valorUc} onChange={(e) => setValorUc(e.target.value)} />
              </div>
              <div className="col-md-4 mb-3">
                <CFormLabel>Fecha</CFormLabel>
                <CFormInput type="date" value={fecha} onChange={(e) => setFecha(e.target.value)} />
              </div>
              <div className="col-md-4 mb-3">
                <CFormLabel>Descripción</CFormLabel>
                <CFormInput value={descripcion} onChange={(e) => setDescripcion(e.target.value)} />
              </div>
            </div>

            <div className="d-flex justify-content-between align-items-center mb-2">
              <strong>Servicios</strong>
              <CButton
                type="button"
                color="primary"
                variant="outline"
                size="sm"
                onClick={() => setLineas((ls) => [...ls, { servicio_id: '', complejidad: '', cantidad: '1' }])}
              >
                + Agregar
              </CButton>
            </div>
            <CTable small bordered responsive align="middle">
              <CTableHead>
                <CTableRow>
                  <CTableHeaderCell>Servicio</CTableHeaderCell>
                  <CTableHeaderCell style={{ width: 180 }}>Complejidad</CTableHeaderCell>
                  <CTableHeaderCell style={{ width: 110 }}>Cantidad</CTableHeaderCell>
                  <CTableHeaderCell style={{ width: 40 }} />
                </CTableRow>
              </CTableHead>
              <CTableBody>
                {lineas.map((l, i) => (
                  <CTableRow key={i}>
                    <CTableDataCell>
                      <CFormSelect size="sm" value={l.servicio_id} onChange={(e) => setLinea(i, { servicio_id: e.target.value })}>
                        <option value="">—</option>
                        {servicios?.map((s) => (
                          <option key={s.id} value={s.id}>
                            {s.codigo ? `${s.codigo} — ` : ''}{s.descripcion}
                          </option>
                        ))}
                      </CFormSelect>
                    </CTableDataCell>
                    <CTableDataCell>
                      <CFormSelect size="sm" value={l.complejidad} onChange={(e) => setLinea(i, { complejidad: e.target.value })}>
                        <option value="">Normal</option>
                        {factores?.map((f) => (
                          <option key={f.id} value={f.nivel}>
                            {f.label ?? f.nivel} (×{f.factor})
                          </option>
                        ))}
                      </CFormSelect>
                    </CTableDataCell>
                    <CTableDataCell>
                      <CFormInput size="sm" inputMode="decimal" value={l.cantidad} onChange={(e) => setLinea(i, { cantidad: e.target.value })} />
                    </CTableDataCell>
                    <CTableDataCell className="text-end">
                      <CButton
                        type="button"
                        color="danger"
                        variant="ghost"
                        size="sm"
                        disabled={lineas.length <= 1}
                        onClick={() => setLineas((ls) => ls.filter((_, j) => j !== i))}
                      >
                        ✕
                      </CButton>
                    </CTableDataCell>
                  </CTableRow>
                ))}
              </CTableBody>
            </CTable>
          </CModalBody>
          <CModalFooter>
            <CButton color="secondary" variant="outline" onClick={() => setOpen(false)}>
              Cancelar
            </CButton>
            <CButton type="submit" color="primary" disabled={createM.isPending}>
              {createM.isPending ? 'Guardando…' : 'Guardar'}
            </CButton>
          </CModalFooter>
        </CForm>
      </CModal>
    </>
  )
}
