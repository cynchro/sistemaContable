import { useEffect, useMemo, useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import {
  CButton,
  CBadge,
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
  CFormSelect,
} from '@coreui/react'
import { listCatalogo, type TipoRetencion } from '../../api/catalogos'
import {
  listTiposRetencionAbm,
  createTipoRetencion,
  updateTipoRetencion,
  deleteTipoRetencion,
  type TipoRetencionInput,
} from '../../api/tiposRetencion'

const BASES: { value: string; label: string }[] = [
  { value: 'neto_gravado', label: 'Neto gravado' },
  { value: 'neto_mas_imp_interno', label: 'Neto + imp. interno' },
  { value: 'iva_percepcion', label: 'IVA (por tramos)' },
]
const baseLabel = (v: string) => BASES.find((b) => b.value === v)?.label ?? v

interface FormState {
  nombre: string
  cod_afip: string
  alicuota: string
  base_calculo: string
  provincia_id: string
  tipo_rg3685: string
}
const EMPTY: FormState = {
  nombre: '',
  cod_afip: '',
  alicuota: '',
  base_calculo: 'neto_gravado',
  provincia_id: '',
  tipo_rg3685: '',
}

/** ABM de tipos de retención/percepción (Archivo de Retenciones del Visual IVA). Los
 * estándar de AFIP son read-only; sólo se editan/borran los propios del estudio. */
export default function TiposRetencionTab() {
  const qc = useQueryClient()
  const { data, isLoading, isError } = useQuery({ queryKey: ['tipos-retencion-abm'], queryFn: listTiposRetencionAbm })
  const { data: provincias } = useQuery({ queryKey: ['catalogo', 'provincias'], queryFn: () => listCatalogo('provincias') })
  const provMap = useMemo(() => new Map(provincias?.map((p) => [p.id, p.nombre])), [provincias])

  const [editing, setEditing] = useState<TipoRetencion | null>(null)
  const [open, setOpen] = useState(false)
  const [form, setForm] = useState<FormState>(EMPTY)
  const [errorMsg, setErrorMsg] = useState<string | null>(null)

  useEffect(() => {
    if (open) {
      setErrorMsg(null)
      setForm(
        editing
          ? {
              nombre: editing.nombre ?? '',
              cod_afip: editing.cod_afip ?? '',
              alicuota: editing.alicuota != null ? String(Number(editing.alicuota)) : '',
              base_calculo: editing.base_calculo ?? 'neto_gravado',
              provincia_id: editing.provincia_id != null ? String(editing.provincia_id) : '',
              tipo_rg3685: editing.tipo_rg3685 != null ? String(editing.tipo_rg3685) : '',
            }
          : EMPTY,
      )
    }
  }, [open, editing])

  const invalidate = () => {
    qc.invalidateQueries({ queryKey: ['tipos-retencion-abm'] })
    qc.invalidateQueries({ queryKey: ['catalogo', 'tipos-retencion'] })
  }
  const close = () => {
    setOpen(false)
    setEditing(null)
  }
  const saveM = useMutation({
    mutationFn: (v: TipoRetencionInput) => (editing ? updateTipoRetencion(editing.id, v) : createTipoRetencion(v)),
    onSuccess: () => {
      invalidate()
      close()
    },
    onError: (e) => {
      const err = e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
      const first = err.response?.data?.errors ? Object.values(err.response.data.errors)[0]?.[0] : undefined
      setErrorMsg(first ?? err.response?.data?.message ?? 'No se pudo guardar.')
    },
  })
  const deleteM = useMutation({ mutationFn: (id: number) => deleteTipoRetencion(id), onSuccess: invalidate })

  const onDelete = (t: TipoRetencion) => {
    if (window.confirm(`¿Eliminar "${t.nombre}"?`)) deleteM.mutate(t.id)
  }
  const submit = (e: React.FormEvent) => {
    e.preventDefault()
    if (!form.nombre.trim()) return
    saveM.mutate({
      nombre: form.nombre,
      cod_afip: form.cod_afip || null,
      alicuota: form.alicuota || null,
      base_calculo: form.base_calculo || null,
      provincia_id: form.provincia_id ? Number(form.provincia_id) : null,
      tipo_rg3685: form.tipo_rg3685 ? Number(form.tipo_rg3685) : null,
    })
  }

  return (
    <div>
      <div className="d-flex justify-content-between align-items-center mb-3">
        <span className="text-body-secondary small">
          Los <CBadge color="secondary">estándar</CBadge> de AFIP son de sólo lectura; editá/borrá sólo los propios.
        </span>
        <CButton
          color="primary"
          size="sm"
          onClick={() => {
            setEditing(null)
            setOpen(true)
          }}
        >
          Nuevo tipo
        </CButton>
      </div>
      {isLoading && <CSpinner />}
      {isError && <CAlert color="danger">No se pudieron cargar los tipos.</CAlert>}
      {data && (
        <CTable hover responsive small align="middle" className="ledger mb-0">
          <CTableHead>
            <CTableRow>
              <CTableHeaderCell>Cód.</CTableHeaderCell>
              <CTableHeaderCell>Nombre</CTableHeaderCell>
              <CTableHeaderCell className="text-end">Alícuota %</CTableHeaderCell>
              <CTableHeaderCell>Base cálculo</CTableHeaderCell>
              <CTableHeaderCell>Provincia</CTableHeaderCell>
              <CTableHeaderCell className="text-center">Origen</CTableHeaderCell>
              <CTableHeaderCell className="text-end">Acciones</CTableHeaderCell>
            </CTableRow>
          </CTableHead>
          <CTableBody>
            {data.map((t) => {
              const propio = t.tenant_id != null
              return (
                <CTableRow key={t.id}>
                  <CTableDataCell>{t.cod_afip ?? '—'}</CTableDataCell>
                  <CTableDataCell>{t.nombre}</CTableDataCell>
                  <CTableDataCell className="text-end">{Number(t.alicuota)}</CTableDataCell>
                  <CTableDataCell>{baseLabel(t.base_calculo)}</CTableDataCell>
                  <CTableDataCell>{t.provincia_id != null ? (provMap.get(t.provincia_id) ?? `#${t.provincia_id}`) : '—'}</CTableDataCell>
                  <CTableDataCell className="text-center">
                    <CBadge color={propio ? 'info' : 'secondary'}>{propio ? 'Propio' : 'Estándar'}</CBadge>
                  </CTableDataCell>
                  <CTableDataCell className="text-end">
                    {propio ? (
                      <>
                        <CButton
                          color="secondary"
                          variant="outline"
                          size="sm"
                          className="me-2"
                          onClick={() => {
                            setEditing(t)
                            setOpen(true)
                          }}
                        >
                          Editar
                        </CButton>
                        <CButton color="danger" variant="outline" size="sm" onClick={() => onDelete(t)}>
                          Eliminar
                        </CButton>
                      </>
                    ) : (
                      <span className="text-body-secondary small">—</span>
                    )}
                  </CTableDataCell>
                </CTableRow>
              )
            })}
          </CTableBody>
        </CTable>
      )}

      <CModal visible={open} onClose={close} alignment="center" size="lg">
        <CModalHeader>
          <CModalTitle>{editing ? 'Editar tipo' : 'Nuevo tipo de retención/percepción'}</CModalTitle>
        </CModalHeader>
        <CForm onSubmit={submit}>
          <CModalBody>
            {errorMsg && <CAlert color="danger">{errorMsg}</CAlert>}
            <div className="row">
              <div className="col-md-8 mb-3">
                <CFormLabel htmlFor="tr-nombre">Nombre *</CFormLabel>
                <CFormInput
                  id="tr-nombre"
                  value={form.nombre}
                  onChange={(e) => setForm((f) => ({ ...f, nombre: e.target.value }))}
                />
              </div>
              <div className="col-md-4 mb-3">
                <CFormLabel htmlFor="tr-cod">Código AFIP</CFormLabel>
                <CFormInput
                  id="tr-cod"
                  value={form.cod_afip}
                  onChange={(e) => setForm((f) => ({ ...f, cod_afip: e.target.value }))}
                />
              </div>
            </div>
            <div className="row">
              <div className="col-md-3 mb-3">
                <CFormLabel htmlFor="tr-alic">Alícuota %</CFormLabel>
                <CFormInput
                  id="tr-alic"
                  inputMode="decimal"
                  value={form.alicuota}
                  onChange={(e) => setForm((f) => ({ ...f, alicuota: e.target.value }))}
                />
              </div>
              <div className="col-md-5 mb-3">
                <CFormLabel htmlFor="tr-base">Base de cálculo</CFormLabel>
                <CFormSelect
                  id="tr-base"
                  value={form.base_calculo}
                  onChange={(e) => setForm((f) => ({ ...f, base_calculo: e.target.value }))}
                >
                  {BASES.map((b) => (
                    <option key={b.value} value={b.value}>
                      {b.label}
                    </option>
                  ))}
                </CFormSelect>
              </div>
              <div className="col-md-4 mb-3">
                <CFormLabel htmlFor="tr-prov">Provincia (IIBB)</CFormLabel>
                <CFormSelect
                  id="tr-prov"
                  value={form.provincia_id}
                  onChange={(e) => setForm((f) => ({ ...f, provincia_id: e.target.value }))}
                >
                  <option value="">—</option>
                  {provincias?.map((p) => (
                    <option key={p.id} value={p.id}>
                      {p.nombre}
                    </option>
                  ))}
                </CFormSelect>
              </div>
            </div>
            <div className="row">
              <div className="col-md-4 mb-3">
                <CFormLabel htmlFor="tr-rg">Clasificación RG3685</CFormLabel>
                <CFormInput
                  id="tr-rg"
                  inputMode="numeric"
                  placeholder="1=IVA, 3=IIBB…"
                  value={form.tipo_rg3685}
                  onChange={(e) => setForm((f) => ({ ...f, tipo_rg3685: e.target.value }))}
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
