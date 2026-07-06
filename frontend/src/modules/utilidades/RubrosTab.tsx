import { useEffect, useState } from 'react'
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
  CModal,
  CModalHeader,
  CModalTitle,
  CModalBody,
  CModalFooter,
  CForm,
  CFormInput,
  CFormLabel,
} from '@coreui/react'
import {
  listRubros,
  createRubro,
  updateRubro,
  deleteRubro,
  type Rubro,
  type RubroInput,
} from '../../api/rubros'

/** ABM de rubros / actividades por tenant (Archivo de Rubros del Visual IVA). */
export default function RubrosTab() {
  const qc = useQueryClient()
  const { data, isLoading, isError } = useQuery({ queryKey: ['rubros'], queryFn: listRubros })
  const [editing, setEditing] = useState<Rubro | null>(null)
  const [open, setOpen] = useState(false)
  const [form, setForm] = useState<{ codigo: string; nombre: string }>({ codigo: '', nombre: '' })

  useEffect(() => {
    if (open) setForm({ codigo: editing?.codigo ?? '', nombre: editing?.nombre ?? '' })
  }, [open, editing])

  const invalidate = () => qc.invalidateQueries({ queryKey: ['rubros'] })
  const close = () => {
    setOpen(false)
    setEditing(null)
  }
  const saveM = useMutation({
    mutationFn: (v: RubroInput) => (editing ? updateRubro(editing.id, v) : createRubro(v)),
    onSuccess: () => {
      invalidate()
      close()
    },
  })
  const deleteM = useMutation({ mutationFn: (id: number) => deleteRubro(id), onSuccess: invalidate })

  const onDelete = (r: Rubro) => {
    if (window.confirm(`¿Eliminar el rubro "${r.nombre}"?`)) deleteM.mutate(r.id)
  }
  const submit = (e: React.FormEvent) => {
    e.preventDefault()
    if (form.nombre.trim()) saveM.mutate({ nombre: form.nombre, codigo: form.codigo || null })
  }

  return (
    <div>
      <div className="d-flex justify-content-end mb-3">
        <CButton
          color="primary"
          size="sm"
          onClick={() => {
            setEditing(null)
            setOpen(true)
          }}
        >
          Nuevo rubro
        </CButton>
      </div>
      {isLoading && <CSpinner />}
      {isError && <CAlert color="danger">No se pudieron cargar los rubros.</CAlert>}
      {data && (
        <CTable hover responsive small align="middle" className="ledger mb-0">
          <CTableHead>
            <CTableRow>
              <CTableHeaderCell style={{ width: 120 }}>Código</CTableHeaderCell>
              <CTableHeaderCell>Nombre</CTableHeaderCell>
              <CTableHeaderCell className="text-end">Acciones</CTableHeaderCell>
            </CTableRow>
          </CTableHead>
          <CTableBody>
            {data.map((r) => (
              <CTableRow key={r.id}>
                <CTableDataCell>{r.codigo ?? '—'}</CTableDataCell>
                <CTableDataCell>{r.nombre}</CTableDataCell>
                <CTableDataCell className="text-end">
                  <CButton
                    color="secondary"
                    variant="outline"
                    size="sm"
                    className="me-2"
                    onClick={() => {
                      setEditing(r)
                      setOpen(true)
                    }}
                  >
                    Editar
                  </CButton>
                  <CButton color="danger" variant="outline" size="sm" onClick={() => onDelete(r)}>
                    Eliminar
                  </CButton>
                </CTableDataCell>
              </CTableRow>
            ))}
            {data.length === 0 && (
              <CTableRow>
                <CTableDataCell colSpan={3} className="text-center text-body-secondary py-4">
                  Sin rubros cargados.
                </CTableDataCell>
              </CTableRow>
            )}
          </CTableBody>
        </CTable>
      )}

      <CModal visible={open} onClose={close} alignment="center">
        <CModalHeader>
          <CModalTitle>{editing ? 'Editar rubro' : 'Nuevo rubro'}</CModalTitle>
        </CModalHeader>
        <CForm onSubmit={submit}>
          <CModalBody>
            <div className="row">
              <div className="col-4 mb-3">
                <CFormLabel htmlFor="r-codigo">Código</CFormLabel>
                <CFormInput
                  id="r-codigo"
                  value={form.codigo}
                  onChange={(e) => setForm((f) => ({ ...f, codigo: e.target.value }))}
                />
              </div>
              <div className="col-8 mb-3">
                <CFormLabel htmlFor="r-nombre">Nombre *</CFormLabel>
                <CFormInput
                  id="r-nombre"
                  value={form.nombre}
                  onChange={(e) => setForm((f) => ({ ...f, nombre: e.target.value }))}
                />
              </div>
            </div>
          </CModalBody>
          <CModalFooter>
            <CButton color="secondary" variant="outline" onClick={close}>
              Cancelar
            </CButton>
            <CButton type="submit" color="primary" disabled={saveM.isPending || !form.nombre.trim()}>
              {saveM.isPending ? 'Guardando…' : 'Guardar'}
            </CButton>
          </CModalFooter>
        </CForm>
      </CModal>
    </div>
  )
}
