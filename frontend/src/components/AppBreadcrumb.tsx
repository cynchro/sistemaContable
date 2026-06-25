import { useLocation } from 'react-router-dom'
import { CBreadcrumb, CBreadcrumbItem } from '@coreui/react'

const NAMES: Record<string, string> = {
  empresas: 'Empresas',
  periodos: 'Períodos',
  iva: 'IVA',
  libro: 'Libro IVA y DDJJ',
  afip: 'Factura electrónica',
  sueldos: 'Sueldos',
  gestion: 'Gestión',
  admin: 'Administración',
}

export default function AppBreadcrumb() {
  const { pathname } = useLocation()
  const segments = pathname.split('/').filter(Boolean)

  return (
    <CBreadcrumb className="my-0 py-3">
      <CBreadcrumbItem active={segments.length === 0}>Inicio</CBreadcrumbItem>
      {segments.map((seg, i) => {
        if (/^\d+$/.test(seg)) {
          return null // ids numéricos: no se muestran en el breadcrumb
        }
        return (
          <CBreadcrumbItem key={i} active={i === segments.length - 1}>
            {NAMES[seg] ?? seg}
          </CBreadcrumbItem>
        )
      })}
    </CBreadcrumb>
  )
}
