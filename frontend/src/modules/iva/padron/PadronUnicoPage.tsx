import { useState } from 'react'
import { Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import {
  CCard,
  CCardHeader,
  CCardBody,
  CTable,
  CTableHead,
  CTableRow,
  CTableHeaderCell,
  CTableBody,
  CTableDataCell,
  CFormInput,
  CBadge,
  CSpinner,
  CAlert,
} from '@coreui/react'
import { listPadronUnico } from '../../../api/padronUnico'

/**
 * Vista global del Padrón Único de Sujetos (documento "Satélite Visual IVA" §10, Etapa 4):
 * consulta de todos los proveedores/clientes del estudio sin tener que entrar empresa por
 * empresa. La edición sigue viviendo en la pantalla de cada empresa (ahí es donde tiene sentido:
 * activar/desactivar, reglas de imputación) — acá cada fila linkea directo con el CUIT
 * precargado en la búsqueda de esa empresa.
 */
export default function PadronUnicoPage() {
  const [q, setQ] = useState('')
  const [busqueda, setBusqueda] = useState('')

  const { data, isLoading, isError } = useQuery({
    queryKey: ['padron-unico', busqueda],
    queryFn: () => listPadronUnico(busqueda || undefined),
  })

  return (
    <CCard>
      <CCardHeader>
        <strong>Padrón único de sujetos</strong>
        <div className="text-body-secondary small mt-1">
          Todos los proveedores y clientes del estudio, en una sola vista — sin filtrar por empresa. Un mismo
          CUIT aparece una sola vez, con las empresas donde está activado.
        </div>
      </CCardHeader>
      <CCardBody>
        <form
          className="mb-3"
          onSubmit={(e) => {
            e.preventDefault()
            setBusqueda(q.trim())
          }}
        >
          <CFormInput
            style={{ maxWidth: 320 }}
            placeholder="Buscar por nombre o CUIT…"
            value={q}
            onChange={(e) => setQ(e.target.value)}
          />
        </form>

        {isLoading && <CSpinner />}
        {isError && <CAlert color="danger">No se pudo cargar el padrón.</CAlert>}
        {data && (
          <CTable hover responsive align="middle" className="mb-0">
            <CTableHead>
              <CTableRow>
                <CTableHeaderCell>Nombre</CTableHeaderCell>
                <CTableHeaderCell>CUIT</CTableHeaderCell>
                <CTableHeaderCell>Localidad</CTableHeaderCell>
                <CTableHeaderCell>Activo en</CTableHeaderCell>
              </CTableRow>
            </CTableHead>
            <CTableBody>
              {data.map((s) => (
                <CTableRow key={s.id}>
                  <CTableDataCell>{s.nombre}</CTableDataCell>
                  <CTableDataCell>{s.cuit}</CTableDataCell>
                  <CTableDataCell>{s.localidad ?? '—'}</CTableDataCell>
                  <CTableDataCell>
                    {s.empresas.map((e) => (
                      <Link
                        key={`${e.empresa_id}-${e.rol}`}
                        to={`/empresas/${e.empresa_id}/${e.rol === 'proveedor' ? 'proveedores' : 'clientes'}?q=${encodeURIComponent(s.cuit)}`}
                        className="text-decoration-none me-1"
                      >
                        <CBadge color={e.rol === 'proveedor' ? 'info' : 'success'} className="me-1">
                          {e.empresa_nombre} ({e.rol})
                        </CBadge>
                      </Link>
                    ))}
                    {s.empresas.length === 0 && <span className="text-body-secondary">—</span>}
                  </CTableDataCell>
                </CTableRow>
              ))}
              {data.length === 0 && (
                <CTableRow>
                  <CTableDataCell colSpan={4} className="text-center text-body-secondary py-4">
                    Sin sujetos cargados.
                  </CTableDataCell>
                </CTableRow>
              )}
            </CTableBody>
          </CTable>
        )}
      </CCardBody>
    </CCard>
  )
}
