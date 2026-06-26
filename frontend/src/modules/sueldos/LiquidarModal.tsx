import { useEffect, useState } from 'react'
import { useQuery, useMutation } from '@tanstack/react-query'
import {
  CModal,
  CModalHeader,
  CModalTitle,
  CModalBody,
  CModalFooter,
  CButton,
  CFormSelect,
  CFormInput,
  CTable,
  CTableHead,
  CTableRow,
  CTableHeaderCell,
  CTableBody,
  CTableDataCell,
  CAlert,
  CRow,
  CCol,
} from '@coreui/react'
import {
  listEmpleados,
  listConceptos,
  getNovedades,
  setNovedades,
  liquidar,
  type Liquidacion,
  type Novedad,
  type Recibo,
} from '../../api/sueldos'
import { apiError, money } from './shared'

interface Props {
  visible: boolean
  empresaId: number
  liquidacion: Liquidacion
  onClose: () => void
}

interface Fila {
  concepto_id: string
  cantidad: string
  importe: string
}

export default function LiquidarModal({ visible, empresaId, liquidacion, onClose }: Props) {
  const [empId, setEmpId] = useState<number | null>(null)
  const [filas, setFilas] = useState<Fila[]>([])
  const [recibo, setRecibo] = useState<Recibo | null>(null)
  const [error, setError] = useState<string | null>(null)
  const bloqueada = liquidacion.bloqueada === 'S'

  const { data: empleados } = useQuery({
    queryKey: ['empleados', empresaId],
    queryFn: () => listEmpleados(empresaId),
    enabled: visible,
  })
  const { data: conceptos } = useQuery({
    queryKey: ['conceptos', empresaId],
    queryFn: () => listConceptos(empresaId),
    enabled: visible,
  })

  // Al elegir empleado, cargamos sus novedades y limpiamos el recibo previo.
  useEffect(() => {
    setRecibo(null)
    setError(null)
    if (empId == null) {
      setFilas([])
      return
    }
    getNovedades(empresaId, liquidacion.id, empId)
      .then((novs) =>
        setFilas(
          novs.length > 0
            ? novs.map((n) => ({ concepto_id: String(n.concepto_id), cantidad: n.cantidad, importe: n.importe }))
            : [{ concepto_id: '', cantidad: '', importe: '' }],
        ),
      )
      .catch(() => setFilas([{ concepto_id: '', cantidad: '', importe: '' }]))
  }, [empId, empresaId, liquidacion.id])

  const setFila = (i: number, patch: Partial<Fila>) =>
    setFilas((fs) => fs.map((f, j) => (j === i ? { ...f, ...patch } : f)))
  const addFila = () => setFilas((fs) => [...fs, { concepto_id: '', cantidad: '', importe: '' }])
  const delFila = (i: number) => setFilas((fs) => fs.filter((_, j) => j !== i))

  const guardarM = useMutation({
    mutationFn: () => {
      const novedades: Novedad[] = filas
        .filter((f) => f.concepto_id)
        .map((f) => ({ concepto_id: Number(f.concepto_id), cantidad: f.cantidad || '0', importe: f.importe || '0' }))
      return setNovedades(empresaId, liquidacion.id, empId as number, novedades)
    },
    onSuccess: () => setError(null),
    onError: (e) => setError(apiError(e, 'No se pudieron guardar las novedades.')),
  })

  const liquidarM = useMutation({
    mutationFn: async () => {
      await guardarM.mutateAsync()
      return liquidar(empresaId, liquidacion.id, empId as number)
    },
    onSuccess: (r) => {
      setRecibo(r)
      setError(null)
    },
    onError: (e) => setError(apiError(e, 'No se pudo liquidar.')),
  })

  const haberes = (recibo?.lineas ?? [])
    .filter((l) => l.tipo !== 3)
    .reduce((a, l) => a + (Number(l.importe) || 0), 0)
  const descuentos = (recibo?.lineas ?? [])
    .filter((l) => l.tipo === 3)
    .reduce((a, l) => a + (Number(l.importe) || 0), 0)
  const neto = haberes - descuentos

  return (
    <CModal visible={visible} onClose={onClose} alignment="center" size="xl">
      <CModalHeader>
        <CModalTitle>Liquidar — {liquidacion.periodo_liquidado}</CModalTitle>
      </CModalHeader>
      <CModalBody>
        {bloqueada && <CAlert color="warning">La liquidación está bloqueada; no se puede liquidar.</CAlert>}
        {error && <CAlert color="danger">{error}</CAlert>}

        <div className="mb-3" style={{ maxWidth: 420 }}>
          <label className="small mb-1">Empleado</label>
          <CFormSelect value={empId ?? ''} onChange={(e) => setEmpId(e.target.value ? Number(e.target.value) : null)}>
            <option value="">— Elegí un empleado —</option>
            {empleados?.map((e) => (
              <option key={e.id} value={e.id}>
                {[e.primer_apellido, e.nombres].filter(Boolean).join(', ')} {e.legajo ? `(${e.legajo})` : ''}
              </option>
            ))}
          </CFormSelect>
        </div>

        {empId != null && (
          <>
            <div className="d-flex justify-content-between align-items-center mb-2">
              <strong>Novedades</strong>
              <CButton type="button" color="primary" variant="outline" size="sm" onClick={addFila} disabled={bloqueada}>
                + Agregar
              </CButton>
            </div>
            <CTable small bordered responsive align="middle">
              <CTableHead>
                <CTableRow>
                  <CTableHeaderCell>Concepto</CTableHeaderCell>
                  <CTableHeaderCell style={{ width: 140 }}>Cantidad</CTableHeaderCell>
                  <CTableHeaderCell style={{ width: 160 }}>Importe</CTableHeaderCell>
                  <CTableHeaderCell style={{ width: 50 }} />
                </CTableRow>
              </CTableHead>
              <CTableBody>
                {filas.map((f, i) => (
                  <CTableRow key={i}>
                    <CTableDataCell>
                      <CFormSelect
                        size="sm"
                        value={f.concepto_id}
                        disabled={bloqueada}
                        onChange={(e) => setFila(i, { concepto_id: e.target.value })}
                      >
                        <option value="">—</option>
                        {conceptos?.map((c) => (
                          <option key={c.id} value={c.id}>
                            {c.codigo} — {c.descripcion}
                          </option>
                        ))}
                      </CFormSelect>
                    </CTableDataCell>
                    <CTableDataCell>
                      <CFormInput
                        size="sm"
                        inputMode="decimal"
                        value={f.cantidad}
                        disabled={bloqueada}
                        onChange={(e) => setFila(i, { cantidad: e.target.value })}
                      />
                    </CTableDataCell>
                    <CTableDataCell>
                      <CFormInput
                        size="sm"
                        inputMode="decimal"
                        value={f.importe}
                        disabled={bloqueada}
                        onChange={(e) => setFila(i, { importe: e.target.value })}
                      />
                    </CTableDataCell>
                    <CTableDataCell className="text-end">
                      <CButton
                        type="button"
                        color="danger"
                        variant="ghost"
                        size="sm"
                        disabled={bloqueada || filas.length <= 1}
                        onClick={() => delFila(i)}
                      >
                        ✕
                      </CButton>
                    </CTableDataCell>
                  </CTableRow>
                ))}
              </CTableBody>
            </CTable>
            <div className="d-flex gap-2 mb-3">
              <CButton
                color="secondary"
                variant="outline"
                disabled={bloqueada || guardarM.isPending}
                onClick={() => guardarM.mutate()}
              >
                {guardarM.isPending ? 'Guardando…' : 'Guardar novedades'}
              </CButton>
              <CButton color="primary" disabled={bloqueada || liquidarM.isPending} onClick={() => liquidarM.mutate()}>
                {liquidarM.isPending ? 'Liquidando…' : 'Liquidar'}
              </CButton>
            </div>

            {recibo && (
              <>
                <h6>Recibo</h6>
                <CTable small bordered responsive align="middle">
                  <CTableHead>
                    <CTableRow>
                      <CTableHeaderCell>Concepto</CTableHeaderCell>
                      <CTableHeaderCell className="text-end">Cantidad</CTableHeaderCell>
                      <CTableHeaderCell className="text-end">Haberes</CTableHeaderCell>
                      <CTableHeaderCell className="text-end">Descuentos</CTableHeaderCell>
                    </CTableRow>
                  </CTableHead>
                  <CTableBody>
                    {recibo.lineas.map((l, i) => (
                      <CTableRow key={i}>
                        <CTableDataCell>{l.descripcion ?? l.codigo ?? l.concepto_id}</CTableDataCell>
                        <CTableDataCell className="text-end">{l.cantidad}</CTableDataCell>
                        <CTableDataCell className="text-end">{l.tipo !== 3 ? money(l.importe) : ''}</CTableDataCell>
                        <CTableDataCell className="text-end">{l.tipo === 3 ? money(l.importe) : ''}</CTableDataCell>
                      </CTableRow>
                    ))}
                  </CTableBody>
                </CTable>
                <CRow className="text-end">
                  <CCol><span className="text-body-secondary small">Haberes</span><div>{money(String(haberes))}</div></CCol>
                  <CCol><span className="text-body-secondary small">Descuentos</span><div>{money(String(descuentos))}</div></CCol>
                  <CCol>
                    <span className="text-body-secondary small">Neto</span>
                    <div className="fs-5 fw-bold">{money(String(neto))}</div>
                  </CCol>
                </CRow>
              </>
            )}
          </>
        )}
      </CModalBody>
      <CModalFooter>
        <CButton color="secondary" variant="outline" onClick={onClose}>
          Cerrar
        </CButton>
      </CModalFooter>
    </CModal>
  )
}
