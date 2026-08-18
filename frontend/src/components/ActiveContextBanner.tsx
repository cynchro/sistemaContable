import { useLocation } from 'react-router-dom'
import { CBadge } from '@coreui/react'
import CIcon from '@coreui/icons-react'
import { cilBuilding } from '@coreui/icons'
import { useActive } from '../layout/ActiveContext'

/**
 * Nombre de la sección actual, derivado de la ruta (mismos grupos que `nav.ts`:
 * IVA / Contabilidad / Estudio / Administración). No hay un "módulo activo" explícito en el
 * estado — se infiere del path para no duplicar esa información en cada página.
 */
function seccionDe(pathname: string): string {
  if (pathname === '/') return 'Inicio'
  if (pathname.startsWith('/manuales')) return 'Manuales'
  if (pathname.startsWith('/utilidades') || pathname.startsWith('/admin')) return 'Administración'
  if (pathname.startsWith('/sueldos') || pathname.startsWith('/gestion')) return 'Estudio'
  if (pathname.startsWith('/afip')) return 'AFIP'
  if (/\/(cuentas|reportes-mayor)(\/|$)/.test(pathname)) return 'Contabilidad'

  return 'IVA'
}

/**
 * Indicador de contexto persistente (informe del cliente 10/08/2026, pedido 5b): "siempre tiene
 * que quedar claro sobre qué contribuyente estamos parados... un indicador arriba en el panel que
 * diga clarito: 'Contribuyente tal · IVA · Período tal'". Antes esto solo vivía en los dropdowns
 * del header (`ActiveSelector.tsx`) — acá se repite, fijo, en el cuerpo de cada pantalla (montado
 * una sola vez en `DefaultLayout`, no por página) para que sea imposible perderlo de vista.
 *
 * Solo se muestra con una empresa activa: sin eso no hay "contribuyente" que confirmar, y el
 * propio header ya invita a elegir uno.
 */
export default function ActiveContextBanner() {
  const { empresa, periodo } = useActive()
  const { pathname } = useLocation()

  if (!empresa) return null

  return (
    <div
      id="tour-active-banner"
      className="d-flex align-items-center flex-wrap gap-2 px-3 py-2 mb-3 rounded border bg-body-tertiary"
    >
      <CIcon icon={cilBuilding} className="text-body-secondary" />
      <strong>{empresa.nombre}</strong>
      <span className="text-body-secondary">·</span>
      <span>{seccionDe(pathname)}</span>
      {periodo && (
        <>
          <span className="text-body-secondary">·</span>
          <span>{periodo.nombre}</span>
          <CBadge color={periodo.cerrado === 'S' ? 'secondary' : 'success'}>
            {periodo.cerrado === 'S' ? 'Cerrado' : 'Abierto'}
          </CBadge>
        </>
      )}
    </div>
  )
}
