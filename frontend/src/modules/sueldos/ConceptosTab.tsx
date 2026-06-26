import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import {
  CButton,
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
  listConceptos,
  createConcepto,
  updateConcepto,
  deleteConcepto,
  type Concepto,
  type ConceptoInput,
} from '../../api/sueldos'
import ConceptoFormModal from './ConceptoFormModal'
import { apiError } from './shared'

const TIPO: Record<number, { label: string; color: string }> = {
  1: { label: 'Remunerativo', color: 'success' },
  2: { label: 'No remunerativo', color: 'info' },
  3: { label: 'Descuento', color: 'warning' },
}

export default function ConceptosTab({ empresaId }: { empresaId: number }) {
  const qc = useQueryClient()
  const [open, setOpen] = useState(false)
  const [editing, setEditing] = useState<Concepto | null>(null)
  const [formError, setFormError] = useState<string | null>(null)

  const { data, isLoading, isError } = useQuery({
    queryKey: ['conceptos', empresaId],
    queryFn: () => listConceptos(empresaId),
  })
  const invalidate = () => qc.invalidateQueries({ queryKey: ['conceptos', empresaId] })
  const close = () => {
    setOpen(false)
    setEditing(null)
    setFormError(null)
  }

  const saveM = useMutation({
    mutationFn: (v: ConceptoInput) =>
      editing ? updateConcepto(empresaId, editing.id, v) : createConcepto(empresaId, v),
    onSuccess: () => {
      invalidate()
      close()
    },
    onError: (e) => setFormError(apiError(e, 'No se pudo guardar el concepto.')),
  })
  const deleteM = useMutation({ mutationFn: (id: number) => deleteConcepto(empresaId, id), onSuccess: invalidate })

  const onDelete = (c: Concepto) => {
    if (window.confirm(`¿Eliminar el concepto ${c.codigo} — ${c.descripcion}?`)) deleteM.mutate(c.id)
  }

  return (
    <>
      <div className="d-flex justify-content-end mb-3">
        <CButton color="primary" size="sm" onClick={() => { setEditing(null); setFormError(null); setOpen(true) }}>
          Nuevo concepto
        </CButton>
      </div>

      {isLoading && <CSpinner />}
      {isError && <CAlert color="danger">No se pudieron cargar los conceptos.</CAlert>}
      {data && (
        <CTable hover responsive align="middle" className="mb-0">
          <CTableHead>
            <CTableRow>
              <CTableHeaderCell>Código</CTableHeaderCell>
              <CTableHeaderCell>Descripción</CTableHeaderCell>
              <CTableHeaderCell>Tipo</CTableHeaderCell>
              <CTableHeaderCell>Fórmula</CTableHeaderCell>
              <CTableHeaderCell className="text-end">Acciones</CTableHeaderCell>
            </CTableRow>
          </CTableHead>
          <CTableBody>
            {data.map((c) => {
              const t = c.tipo != null ? TIPO[c.tipo] : undefined
              return (
                <CTableRow key={c.id}>
                  <CTableDataCell>{c.codigo}</CTableDataCell>
                  <CTableDataCell>{c.descripcion}</CTableDataCell>
                  <CTableDataCell>
                    {t ? <CBadge color={t.color}>{t.label}</CBadge> : '—'}
                  </CTableDataCell>
                  <CTableDataCell>
                    <code className="small">{c.formula ?? '—'}</code>
                  </CTableDataCell>
                  <CTableDataCell className="text-end">
                    <CButton
                      color="secondary"
                      variant="outline"
                      size="sm"
                      className="me-2"
                      onClick={() => { setEditing(c); setFormError(null); setOpen(true) }}
                    >
                      Editar
                    </CButton>
                    <CButton color="danger" variant="outline" size="sm" onClick={() => onDelete(c)}>
                      Eliminar
                    </CButton>
                  </CTableDataCell>
                </CTableRow>
              )
            })}
            {data.length === 0 && (
              <CTableRow>
                <CTableDataCell colSpan={5} className="text-center text-body-secondary py-4">
                  Sin conceptos cargados.
                </CTableDataCell>
              </CTableRow>
            )}
          </CTableBody>
        </CTable>
      )}

      <ConceptoFormModal
        visible={open}
        concepto={editing}
        saving={saveM.isPending}
        errorMsg={formError}
        onClose={close}
        onSubmit={(v) => saveM.mutate(v)}
      />
    </>
  )
}
