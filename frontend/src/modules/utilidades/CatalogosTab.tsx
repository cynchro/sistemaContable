import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
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
} from '@coreui/react'
import { listCatalogo } from '../../api/catalogos'

/** Catálogos de AFIP visualizables (read-only). Réplica de los "Archivos base" de
 * Utilidades del Visual IVA. Los códigos son fijos (de ellos dependen los resolvers
 * de factura electrónica), por eso no se editan desde acá. */
const CATALOGOS: { slug: string; label: string }[] = [
  { slug: 'condiciones-iva', label: 'Condiciones de IVA' },
  { slug: 'tipos-comprobante', label: 'Tipos de comprobante' },
  { slug: 'tipos-documento', label: 'Tipos de documento' },
  { slug: 'tipos-retencion', label: 'Retenciones / Percepciones' },
  { slug: 'tipos-moneda', label: 'Monedas' },
  { slug: 'provincias', label: 'Provincias' },
  { slug: 'tipos-operacion-venta', label: 'Tipos de operación (venta)' },
  { slug: 'tipos-operacion-compra', label: 'Tipos de operación (compra)' },
]

export default function CatalogosTab() {
  const [slug, setSlug] = useState(CATALOGOS[0].slug)
  const { data, isLoading, isError } = useQuery({
    queryKey: ['catalogo', slug],
    queryFn: () => listCatalogo(slug),
  })

  return (
    <div>
      <div className="d-flex flex-wrap gap-2 mb-3">
        {CATALOGOS.map((c) => (
          <CButton
            key={c.slug}
            size="sm"
            color={c.slug === slug ? 'primary' : 'secondary'}
            variant={c.slug === slug ? undefined : 'outline'}
            onClick={() => setSlug(c.slug)}
          >
            {c.label}
          </CButton>
        ))}
      </div>

      {isLoading && <CSpinner />}
      {isError && <CAlert color="danger">No se pudo cargar el catálogo.</CAlert>}
      {data && (
        <CTable hover responsive small align="middle" className="mb-0">
          <CTableHead>
            <CTableRow>
              <CTableHeaderCell style={{ width: 120 }}>Código</CTableHeaderCell>
              <CTableHeaderCell>Nombre</CTableHeaderCell>
            </CTableRow>
          </CTableHead>
          <CTableBody>
            {data.map((item) => (
              <CTableRow key={item.id}>
                <CTableDataCell>{item.codigo ?? '—'}</CTableDataCell>
                <CTableDataCell>{item.nombre}</CTableDataCell>
              </CTableRow>
            ))}
            {data.length === 0 && (
              <CTableRow>
                <CTableDataCell colSpan={2} className="text-center text-body-secondary py-4">
                  Sin registros.
                </CTableDataCell>
              </CTableRow>
            )}
          </CTableBody>
        </CTable>
      )}
    </div>
  )
}
