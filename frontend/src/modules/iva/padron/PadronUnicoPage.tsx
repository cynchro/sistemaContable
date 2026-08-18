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
  CButton,
} from '@coreui/react'
import { listPadronUnico, type RolPadron } from '../../../api/padronUnico'
import { usePageTour } from '../../../tours/usePageTour'
import { tourPadronUnico } from '../../../tours/tours'

/**
 * Vista global del Padrón Único de Sujetos (documento "Satélite Visual IVA" §10, Etapa 4):
 * consulta de todos los proveedores (o todos los clientes, según `rol`) del estudio sin tener
 * que entrar empresa por empresa. La edición sigue viviendo en la pantalla de cada empresa (ahí
 * es donde tiene sentido: activar/desactivar, reglas de imputación) — acá cada fila linkea
 * directo con el CUIT precargado en la búsqueda de esa empresa.
 *
 * Dos padrones separados, nunca mezclados (informe del cliente 10/08/2026, pedido 5a) — esta
 * misma página se monta dos veces, una por rol (`/padron-proveedores`, `/padron-clientes`).
 */
const PER_PAGE = 50

export default function PadronUnicoPage({ rol }: { rol: RolPadron }) {
  const [q, setQ] = useState('')
  const [busqueda, setBusqueda] = useState('')
  const [page, setPage] = useState(1)

  const { data, isLoading, isError } = useQuery({
    queryKey: ['padron-unico', rol, busqueda, page],
    queryFn: () => listPadronUnico(rol, busqueda || undefined, page, PER_PAGE),
  })
  const total = data?.total ?? 0
  const totalPaginas = Math.max(1, Math.ceil(total / PER_PAGE))
  const { start: verRecorrido } = usePageTour(`padron-${rol}`, tourPadronUnico)

  const titulo = rol === 'proveedor' ? 'Padrón único de proveedores' : 'Padrón único de clientes'
  const rutaEmpresa = rol === 'proveedor' ? 'proveedores' : 'clientes'

  return (
    <CCard>
      <CCardHeader className="d-flex justify-content-between align-items-start">
        <div>
          <strong>{titulo}</strong>
          <div className="text-body-secondary small mt-1">
            Todos los {rutaEmpresa} del estudio, en una sola vista — sin filtrar por empresa. Un mismo CUIT
            aparece una sola vez, con las empresas donde está activado.
          </div>
        </div>
        <CButton color="secondary" variant="outline" size="sm" onClick={verRecorrido}>
          Ver recorrido
        </CButton>
      </CCardHeader>
      <CCardBody>
        <form
          id="tour-padron-buscar"
          className="mb-3"
          onSubmit={(e) => {
            e.preventDefault()
            setBusqueda(q.trim())
            setPage(1)
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
          <CTable id="tour-padron-tabla" hover responsive align="middle" className="mb-0">
            <CTableHead>
              <CTableRow>
                <CTableHeaderCell>Nombre</CTableHeaderCell>
                <CTableHeaderCell>CUIT</CTableHeaderCell>
                <CTableHeaderCell>Localidad</CTableHeaderCell>
                <CTableHeaderCell>Activo en</CTableHeaderCell>
              </CTableRow>
            </CTableHead>
            <CTableBody>
              {data.results.map((s) => (
                <CTableRow key={s.id}>
                  <CTableDataCell>{s.nombre}</CTableDataCell>
                  <CTableDataCell>{s.cuit}</CTableDataCell>
                  <CTableDataCell>{s.localidad ?? '—'}</CTableDataCell>
                  <CTableDataCell>
                    {s.empresas.map((e) => (
                      <Link
                        key={e.empresa_id}
                        to={`/empresas/${e.empresa_id}/${rutaEmpresa}?q=${encodeURIComponent(s.cuit)}`}
                        className="text-decoration-none me-1"
                      >
                        <CBadge color={rol === 'proveedor' ? 'info' : 'success'} className="me-1">
                          {e.empresa_nombre}
                        </CBadge>
                      </Link>
                    ))}
                    {s.empresas.length === 0 && <span className="text-body-secondary">—</span>}
                  </CTableDataCell>
                </CTableRow>
              ))}
              {data.results.length === 0 && (
                <CTableRow>
                  <CTableDataCell colSpan={4} className="text-center text-body-secondary py-4">
                    Sin sujetos cargados.
                  </CTableDataCell>
                </CTableRow>
              )}
            </CTableBody>
          </CTable>
        )}
        {data && data.results.length > 0 && (
          <div className="d-flex justify-content-between align-items-center mt-3">
            <small className="text-body-secondary">
              {total} sujeto{total === 1 ? '' : 's'} · página {page} de {totalPaginas}
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
        )}
      </CCardBody>
    </CCard>
  )
}
