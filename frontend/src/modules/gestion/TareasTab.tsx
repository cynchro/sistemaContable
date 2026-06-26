import { useState } from 'react'
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
  CFormTextarea,
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
  listTareas,
  createTarea,
  deleteTarea,
  cambiarEstadoTarea,
  listTareaTipos,
  TAREA_ESTADOS,
  TAREA_PRIORIDADES,
  type Tarea,
} from '../../api/gestion'
import { listEmpresas } from '../../api/empresas'
import { apiError, humaniza, COLOR_PRIORIDAD } from './shared'
import TareaDetalleModal from './TareaDetalleModal'

export default function TareasTab() {
  const qc = useQueryClient()
  const [open, setOpen] = useState(false)
  const [detalle, setDetalle] = useState<Tarea | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [form, setForm] = useState({ titulo: '', tipo_id: '', empresa_id: '', prioridad: 'media', fecha_limite: '', descripcion: '' })

  const { data, isLoading, isError } = useQuery({ queryKey: ['tareas'], queryFn: listTareas })
  const { data: tipos } = useQuery({ queryKey: ['tarea-tipos'], queryFn: listTareaTipos })
  const { data: empresas } = useQuery({ queryKey: ['empresas'], queryFn: listEmpresas })
  const empresaNombre = (id: number | null) => empresas?.find((e) => e.id === id)?.nombre ?? '—'
  const invalidate = () => qc.invalidateQueries({ queryKey: ['tareas'] })

  const createM = useMutation({
    mutationFn: () =>
      createTarea({
        titulo: form.titulo,
        tipo_id: form.tipo_id ? Number(form.tipo_id) : null,
        empresa_id: form.empresa_id ? Number(form.empresa_id) : null,
        prioridad: form.prioridad || null,
        fecha_limite: form.fecha_limite || null,
        descripcion: form.descripcion || null,
      }),
    onSuccess: () => {
      setOpen(false)
      setForm({ titulo: '', tipo_id: '', empresa_id: '', prioridad: 'media', fecha_limite: '', descripcion: '' })
      invalidate()
    },
    onError: (e) => setError(apiError(e, 'No se pudo crear la tarea.')),
  })
  const estadoM = useMutation({
    mutationFn: ({ id, estado }: { id: number; estado: string }) => cambiarEstadoTarea(id, estado),
    onSuccess: invalidate,
  })
  const deleteM = useMutation({ mutationFn: (id: number) => deleteTarea(id), onSuccess: invalidate })

  return (
    <>
      <div className="d-flex justify-content-end mb-3">
        <CButton color="primary" size="sm" onClick={() => { setError(null); setOpen(true) }}>
          Nueva tarea
        </CButton>
      </div>

      {isLoading && <CSpinner />}
      {isError && <CAlert color="danger">No se pudieron cargar las tareas.</CAlert>}
      {data && (
        <CTable hover responsive align="middle" className="mb-0">
          <CTableHead>
            <CTableRow>
              <CTableHeaderCell>Título</CTableHeaderCell>
              <CTableHeaderCell>Contribuyente</CTableHeaderCell>
              <CTableHeaderCell>Prioridad</CTableHeaderCell>
              <CTableHeaderCell>Vence</CTableHeaderCell>
              <CTableHeaderCell style={{ width: 180 }}>Estado</CTableHeaderCell>
              <CTableHeaderCell className="text-end">Acciones</CTableHeaderCell>
            </CTableRow>
          </CTableHead>
          <CTableBody>
            {data.map((t) => (
              <CTableRow key={t.id}>
                <CTableDataCell>
                  <button className="btn btn-link p-0 text-start" onClick={() => setDetalle(t)}>
                    {t.titulo}
                  </button>
                </CTableDataCell>
                <CTableDataCell>{empresaNombre(t.empresa_id)}</CTableDataCell>
                <CTableDataCell>
                  {t.prioridad ? (
                    <CBadge color={COLOR_PRIORIDAD[t.prioridad] ?? 'secondary'}>{humaniza(t.prioridad)}</CBadge>
                  ) : (
                    '—'
                  )}
                </CTableDataCell>
                <CTableDataCell>{t.fecha_limite ?? '—'}</CTableDataCell>
                <CTableDataCell>
                  <CFormSelect
                    size="sm"
                    value={t.estado}
                    onChange={(e) => estadoM.mutate({ id: t.id, estado: e.target.value })}
                  >
                    {TAREA_ESTADOS.map((s) => (
                      <option key={s} value={s}>
                        {humaniza(s)}
                      </option>
                    ))}
                  </CFormSelect>
                </CTableDataCell>
                <CTableDataCell className="text-end">
                  <CButton color="danger" variant="outline" size="sm" onClick={() => deleteM.mutate(t.id)}>
                    Eliminar
                  </CButton>
                </CTableDataCell>
              </CTableRow>
            ))}
            {data.length === 0 && (
              <CTableRow>
                <CTableDataCell colSpan={6} className="text-center text-body-secondary py-4">
                  Sin tareas.
                </CTableDataCell>
              </CTableRow>
            )}
          </CTableBody>
        </CTable>
      )}

      <CModal visible={open} onClose={() => setOpen(false)} alignment="center" size="lg">
        <CModalHeader>
          <CModalTitle>Nueva tarea</CModalTitle>
        </CModalHeader>
        <CForm
          onSubmit={(e) => {
            e.preventDefault()
            if (form.titulo) createM.mutate()
          }}
        >
          <CModalBody>
            {error && <CAlert color="danger">{error}</CAlert>}
            <div className="mb-3">
              <CFormLabel>Título *</CFormLabel>
              <CFormInput value={form.titulo} onChange={(e) => setForm({ ...form, titulo: e.target.value })} />
            </div>
            <div className="row">
              <div className="col-md-6 mb-3">
                <CFormLabel>Tipo</CFormLabel>
                <CFormSelect value={form.tipo_id} onChange={(e) => setForm({ ...form, tipo_id: e.target.value })}>
                  <option value="">—</option>
                  {tipos?.map((t) => (
                    <option key={t.id} value={t.id}>
                      {t.nombre}
                    </option>
                  ))}
                </CFormSelect>
              </div>
              <div className="col-md-6 mb-3">
                <CFormLabel>Contribuyente</CFormLabel>
                <CFormSelect value={form.empresa_id} onChange={(e) => setForm({ ...form, empresa_id: e.target.value })}>
                  <option value="">— (interna del estudio) —</option>
                  {empresas?.map((em) => (
                    <option key={em.id} value={em.id}>
                      {em.nombre}
                    </option>
                  ))}
                </CFormSelect>
              </div>
            </div>
            <div className="row">
              <div className="col-md-6 mb-3">
                <CFormLabel>Prioridad</CFormLabel>
                <CFormSelect value={form.prioridad} onChange={(e) => setForm({ ...form, prioridad: e.target.value })}>
                  {TAREA_PRIORIDADES.map((p) => (
                    <option key={p} value={p}>
                      {humaniza(p)}
                    </option>
                  ))}
                </CFormSelect>
              </div>
              <div className="col-md-6 mb-3">
                <CFormLabel>Fecha límite</CFormLabel>
                <CFormInput type="date" value={form.fecha_limite} onChange={(e) => setForm({ ...form, fecha_limite: e.target.value })} />
              </div>
            </div>
            <div className="mb-3">
              <CFormLabel>Descripción</CFormLabel>
              <CFormTextarea rows={2} value={form.descripcion} onChange={(e) => setForm({ ...form, descripcion: e.target.value })} />
            </div>
          </CModalBody>
          <CModalFooter>
            <CButton color="secondary" variant="outline" onClick={() => setOpen(false)}>
              Cancelar
            </CButton>
            <CButton type="submit" color="primary" disabled={createM.isPending || !form.titulo}>
              {createM.isPending ? 'Guardando…' : 'Guardar'}
            </CButton>
          </CModalFooter>
        </CForm>
      </CModal>

      {detalle && <TareaDetalleModal tarea={detalle} onClose={() => setDetalle(null)} />}
    </>
  )
}
