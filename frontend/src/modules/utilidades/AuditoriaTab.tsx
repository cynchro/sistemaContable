import { useState } from 'react'
import { useQuery, keepPreviousData } from '@tanstack/react-query'
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
} from '@coreui/react'
import { listAuditoria } from '../../api/utilidades'

const PER_PAGE = 50

/** Color del método HTTP para lectura rápida del log. */
function metodoColor(m: string): string {
  if (m === 'POST') return 'success'
  if (m === 'PUT' || m === 'PATCH') return 'warning'
  if (m === 'DELETE') return 'danger'
  return 'secondary'
}

/** Auditoría de operaciones del módulo IVA (escrituras registradas por el backend).
 * Réplica del "Archivo de Logs" de Utilidades del Visual IVA. */
export default function AuditoriaTab() {
  const [page, setPage] = useState(1)
  const { data, isLoading, isError, isFetching } = useQuery({
    queryKey: ['auditoria', page],
    queryFn: () => listAuditoria(page, PER_PAGE),
    placeholderData: keepPreviousData,
  })

  const total = data?.cantidad_total ?? data?.total ?? 0
  const totalPaginas = Math.max(1, Math.ceil(total / PER_PAGE))

  return (
    <div>
      {isLoading && <CSpinner />}
      {isError && (
        <CAlert color="danger">
          No se pudo cargar la auditoría (requiere permiso <code>iva.auditoria</code>).
        </CAlert>
      )}
      {data && (
        <>
          <CTable hover responsive small align="middle" className="mb-0">
            <CTableHead>
              <CTableRow>
                <CTableHeaderCell>Fecha</CTableHeaderCell>
                <CTableHeaderCell>Usuario</CTableHeaderCell>
                <CTableHeaderCell>Método</CTableHeaderCell>
                <CTableHeaderCell>Recurso</CTableHeaderCell>
                <CTableHeaderCell className="text-center">Estado</CTableHeaderCell>
              </CTableRow>
            </CTableHead>
            <CTableBody>
              {data.results.map((l) => (
                <CTableRow key={l.id}>
                  <CTableDataCell className="text-nowrap">{l.created_at}</CTableDataCell>
                  <CTableDataCell>#{l.user_id ?? '—'}</CTableDataCell>
                  <CTableDataCell>
                    <CBadge color={metodoColor(l.metodo)}>{l.metodo}</CBadge>
                  </CTableDataCell>
                  <CTableDataCell><code className="small">{l.uri}</code></CTableDataCell>
                  <CTableDataCell className="text-center">{l.status}</CTableDataCell>
                </CTableRow>
              ))}
              {data.results.length === 0 && (
                <CTableRow>
                  <CTableDataCell colSpan={5} className="text-center text-body-secondary py-4">
                    Sin operaciones registradas.
                  </CTableDataCell>
                </CTableRow>
              )}
            </CTableBody>
          </CTable>

          <div className="d-flex justify-content-between align-items-center mt-3">
            <small className="text-body-secondary">
              {total} registro{total === 1 ? '' : 's'} · página {page} de {totalPaginas}
              {isFetching && <CSpinner size="sm" className="ms-2" />}
            </small>
            <div>
              <CButton
                color="secondary"
                variant="outline"
                size="sm"
                className="me-2"
                disabled={page <= 1}
                onClick={() => setPage((p) => Math.max(1, p - 1))}
              >
                Anterior
              </CButton>
              <CButton
                color="secondary"
                variant="outline"
                size="sm"
                disabled={page >= totalPaginas}
                onClick={() => setPage((p) => p + 1)}
              >
                Siguiente
              </CButton>
            </div>
          </div>
        </>
      )}
    </div>
  )
}
