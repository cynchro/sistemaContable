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
  listConceptos,
  createConcepto,
  updateConcepto,
  deleteConcepto,
  type Concepto,
  type ConceptoInput,
} from '../../api/conceptos'

/**
 * ABM del catálogo de conceptos del Padrón Único (documento "Satélite Visual IVA" §5.2/§5.4),
 * por tenant — ej. "Combustibles y Lubricantes", "Insumos". Se usan en las reglas de imputación
 * del proveedor (ProveedorImputacionPage) y se traducen a la cuenta real de cada empresa en
 * Actividades ("Mapeo de conceptos → cuentas").
 */
export default function ConceptosTab() {
  const qc = useQueryClient()
  const { data, isLoading, isError } = useQuery({ queryKey: ['conceptos'], queryFn: listConceptos })
  const [editing, setEditing] = useState<Concepto | null>(null)
  const [open, setOpen] = useState(false)
  const [nombre, setNombre] = useState('')

  useEffect(() => {
    if (open) setNombre(editing?.nombre ?? '')
  }, [open, editing])

  const invalidate = () => qc.invalidateQueries({ queryKey: ['conceptos'] })
  const close = () => {
    setOpen(false)
    setEditing(null)
  }
  const saveM = useMutation({
    mutationFn: (v: ConceptoInput) => (editing ? updateConcepto(editing.id, v) : createConcepto(v)),
    onSuccess: () => {
      invalidate()
      close()
    },
  })
  const deleteM = useMutation({ mutationFn: (id: number) => deleteConcepto(id), onSuccess: invalidate })

  const onDelete = (c: Concepto) => {
    if (window.confirm(`¿Eliminar el concepto "${c.nombre}"?`)) deleteM.mutate(c.id)
  }
  const submit = (e: React.FormEvent) => {
    e.preventDefault()
    if (nombre.trim()) saveM.mutate({ nombre })
  }

  return (
    <div>
      <div className="text-body-secondary small mb-3">
        Catálogo del estudio, sin depender de ninguna empresa. Cada empresa lo traduce a su propia cuenta
        contable desde Actividades → "Mapeo de conceptos → cuentas".
      </div>
      <div className="d-flex justify-content-end mb-3">
        <CButton
          color="primary"
          size="sm"
          onClick={() => {
            setEditing(null)
            setOpen(true)
          }}
        >
          Nuevo concepto
        </CButton>
      </div>
      {isLoading && <CSpinner />}
      {isError && <CAlert color="danger">No se pudieron cargar los conceptos.</CAlert>}
      {data && (
        <CTable hover responsive small align="middle" className="ledger mb-0">
          <CTableHead>
            <CTableRow>
              <CTableHeaderCell>Nombre</CTableHeaderCell>
              <CTableHeaderCell className="text-end">Acciones</CTableHeaderCell>
            </CTableRow>
          </CTableHead>
          <CTableBody>
            {data.map((c) => (
              <CTableRow key={c.id}>
                <CTableDataCell>{c.nombre}</CTableDataCell>
                <CTableDataCell className="text-end">
                  <CButton
                    color="secondary"
                    variant="outline"
                    size="sm"
                    className="me-2"
                    onClick={() => {
                      setEditing(c)
                      setOpen(true)
                    }}
                  >
                    Editar
                  </CButton>
                  <CButton color="danger" variant="outline" size="sm" onClick={() => onDelete(c)}>
                    Eliminar
                  </CButton>
                </CTableDataCell>
              </CTableRow>
            ))}
            {data.length === 0 && (
              <CTableRow>
                <CTableDataCell colSpan={2} className="text-center text-body-secondary py-4">
                  Sin conceptos cargados.
                </CTableDataCell>
              </CTableRow>
            )}
          </CTableBody>
        </CTable>
      )}

      <CModal visible={open} onClose={close} alignment="center">
        <CModalHeader>
          <CModalTitle>{editing ? 'Editar concepto' : 'Nuevo concepto'}</CModalTitle>
        </CModalHeader>
        <CForm onSubmit={submit}>
          <CModalBody>
            <div className="mb-3">
              <CFormLabel htmlFor="c-nombre">Nombre *</CFormLabel>
              <CFormInput id="c-nombre" value={nombre} onChange={(e) => setNombre(e.target.value)} />
            </div>
          </CModalBody>
          <CModalFooter>
            <CButton color="secondary" variant="outline" onClick={close}>
              Cancelar
            </CButton>
            <CButton type="submit" color="primary" disabled={saveM.isPending || !nombre.trim()}>
              {saveM.isPending ? 'Guardando…' : 'Guardar'}
            </CButton>
          </CModalFooter>
        </CForm>
      </CModal>
    </div>
  )
}
