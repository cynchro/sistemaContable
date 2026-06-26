import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import {
  CButton,
  CForm,
  CFormInput,
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
import { listPermisos, createPermiso } from '../../api/admin'
import { apiError } from './shared'

export default function PermisosTab() {
  const qc = useQueryClient()
  const [key, setKey] = useState('')
  const [descripcion, setDescripcion] = useState('')
  const [error, setError] = useState<string | null>(null)

  const { data, isLoading, isError, error: qErr } = useQuery({ queryKey: ['permisos'], queryFn: listPermisos })

  const createM = useMutation({
    mutationFn: () => createPermiso(key.trim(), descripcion.trim()),
    onSuccess: () => {
      setKey('')
      setDescripcion('')
      setError(null)
      qc.invalidateQueries({ queryKey: ['permisos'] })
    },
    onError: (e) => setError(apiError(e, 'No se pudo crear el permiso.')),
  })

  if (isError) return <CAlert color="danger">{apiError(qErr, 'No se pudieron cargar los permisos.')}</CAlert>

  return (
    <>
      {error && <CAlert color="danger">{error}</CAlert>}
      <CForm
        className="row g-2 align-items-end mb-3"
        onSubmit={(e) => {
          e.preventDefault()
          if (key.trim()) createM.mutate()
        }}
      >
        <div className="col-auto">
          <CFormInput placeholder="key (ej: iva.ventas)" value={key} onChange={(e) => setKey(e.target.value)} style={{ width: 220 }} />
        </div>
        <div className="col-auto">
          <CFormInput placeholder="Descripción" value={descripcion} onChange={(e) => setDescripcion(e.target.value)} style={{ width: 280 }} />
        </div>
        <div className="col-auto">
          <CButton type="submit" color="primary" disabled={createM.isPending || !key.trim()}>
            Nuevo permiso
          </CButton>
        </div>
      </CForm>

      {isLoading && <CSpinner />}
      {data && (
        <CTable hover responsive align="middle" className="mb-0">
          <CTableHead>
            <CTableRow>
              <CTableHeaderCell>Key</CTableHeaderCell>
              <CTableHeaderCell>Descripción</CTableHeaderCell>
              <CTableHeaderCell>Estado</CTableHeaderCell>
            </CTableRow>
          </CTableHead>
          <CTableBody>
            {data.map((p) => (
              <CTableRow key={p.id}>
                <CTableDataCell>
                  <code>{p.key}</code>
                </CTableDataCell>
                <CTableDataCell>{p.descripcion ?? '—'}</CTableDataCell>
                <CTableDataCell>
                  <CBadge color={p.estado === 1 ? 'success' : 'secondary'}>
                    {p.estado === 1 ? 'Activo' : 'Inactivo'}
                  </CBadge>
                </CTableDataCell>
              </CTableRow>
            ))}
            {data.length === 0 && (
              <CTableRow>
                <CTableDataCell colSpan={3} className="text-center text-body-secondary py-4">
                  Sin permisos.
                </CTableDataCell>
              </CTableRow>
            )}
          </CTableBody>
        </CTable>
      )}
    </>
  )
}
