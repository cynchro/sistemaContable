import { NavLink, useLocation } from 'react-router-dom'
import {
  CSidebar,
  CSidebarHeader,
  CSidebarBrand,
  CSidebarNav,
  CSidebarFooter,
  CSidebarToggler,
  CNavItem,
  CNavGroup,
  CNavLink,
  CCloseButton,
} from '@coreui/react'
import CIcon from '@coreui/icons-react'
import { useUi } from '../layout/UiContext'
import { useActive } from '../layout/ActiveContext'
import { buildNavigation, type NavItem } from '../layout/nav'

/** Primer segmento de un path ("/empresas/5/ventas" → "empresas"). */
function firstSegment(to: string): string {
  return to.split('/')[1] ?? ''
}

/** ids de anclaje para el tour "mapa del sistema" (usePageTour → tourNavegacion). */
const TOUR_GROUP_ID: Record<string, string> = {
  IVA: 'tour-sidebar-iva',
  Estudio: 'tour-sidebar-estudio',
  Administración: 'tour-sidebar-admin',
}

export default function AppSidebar() {
  const { sidebarShow, setSidebarShow, sidebarUnfoldable, setSidebarUnfoldable } = useUi()
  const { activeEmpresaId, activePeriodoId } = useActive()
  const { pathname } = useLocation()
  const navigation = buildNavigation(activeEmpresaId, activePeriodoId)
  const currentSegment = firstSegment(pathname)

  const renderItem = (item: NavItem, key: number) =>
    item.disabled ? (
      <CNavItem key={key}>
        <CNavLink disabled title={item.hint}>
          <CIcon customClassName="nav-icon" icon={item.icon} />
          {item.name}
        </CNavLink>
      </CNavItem>
    ) : (
      <CNavItem key={key}>
        <CNavLink as={NavLink} to={item.to} end>
          <CIcon customClassName="nav-icon" icon={item.icon} />
          {item.name}
        </CNavLink>
      </CNavItem>
    )

  return (
    <CSidebar
      className="border-end"
      colorScheme="dark"
      position="fixed"
      unfoldable={sidebarUnfoldable}
      visible={sidebarShow}
      onVisibleChange={(v) => setSidebarShow(v)}
    >
      <CSidebarHeader className="border-bottom">
        <CSidebarBrand as={NavLink} to="/" title="Ir al inicio">
          <img src="/logo.png" alt="Estudio Haddad" height={40} style={{ maxWidth: '100%', objectFit: 'contain' }} />
        </CSidebarBrand>
        <CCloseButton className="d-lg-none" dark onClick={() => setSidebarShow(false)} />
      </CSidebarHeader>

      <CSidebarNav>
        {navigation.map((entry, i) => {
          if (entry.type === 'item') return renderItem(entry, i)

          // El grupo arranca abierto si la ruta actual pertenece a alguno de sus ítems.
          const open = entry.items.some((it) => firstSegment(it.to) === currentSegment && currentSegment !== '')
          return (
            <CNavGroup
              key={i}
              id={TOUR_GROUP_ID[entry.name]}
              visible={open}
              toggler={
                <>
                  <CIcon customClassName="nav-icon" icon={entry.icon} />
                  {entry.name}
                </>
              }
            >
              {entry.items.map((it, j) => renderItem(it, j))}
            </CNavGroup>
          )
        })}
      </CSidebarNav>

      <CSidebarFooter className="border-top d-none d-lg-flex">
        <CSidebarToggler onClick={() => setSidebarUnfoldable(!sidebarUnfoldable)} />
      </CSidebarFooter>
    </CSidebar>
  )
}
