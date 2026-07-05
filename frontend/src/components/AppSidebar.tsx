import { NavLink } from 'react-router-dom'
import {
  CSidebar,
  CSidebarHeader,
  CSidebarBrand,
  CSidebarNav,
  CSidebarFooter,
  CSidebarToggler,
  CNavItem,
  CNavLink,
  CNavTitle,
  CCloseButton,
} from '@coreui/react'
import CIcon from '@coreui/icons-react'
import { useUi } from '../layout/UiContext'
import { useActive } from '../layout/ActiveContext'
import { buildNavigation } from '../layout/nav'

export default function AppSidebar() {
  const { sidebarShow, setSidebarShow, sidebarUnfoldable, setSidebarUnfoldable } = useUi()
  const { activeEmpresaId, activePeriodoId } = useActive()
  const navigation = buildNavigation(activeEmpresaId, activePeriodoId)

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
        <CSidebarBrand>Estudio Haddad</CSidebarBrand>
        <CCloseButton className="d-lg-none" dark onClick={() => setSidebarShow(false)} />
      </CSidebarHeader>

      <CSidebarNav>
        {navigation.map((entry, i) =>
          entry.type === 'title' ? (
            <CNavTitle key={i}>{entry.name}</CNavTitle>
          ) : entry.disabled ? (
            <CNavItem key={i}>
              <CNavLink disabled title={entry.hint}>
                <CIcon customClassName="nav-icon" icon={entry.icon} />
                {entry.name}
              </CNavLink>
            </CNavItem>
          ) : (
            <CNavItem key={i}>
              <CNavLink as={NavLink} to={entry.to} end>
                <CIcon customClassName="nav-icon" icon={entry.icon} />
                {entry.name}
              </CNavLink>
            </CNavItem>
          ),
        )}
      </CSidebarNav>

      <CSidebarFooter className="border-top d-none d-lg-flex">
        <CSidebarToggler onClick={() => setSidebarUnfoldable(!sidebarUnfoldable)} />
      </CSidebarFooter>
    </CSidebar>
  )
}
