import { NavLink } from 'react-router-dom'
import {
  CSidebar,
  CSidebarHeader,
  CSidebarBrand,
  CSidebarNav,
  CSidebarFooter,
  CSidebarToggler,
  CNavItem,
  CNavTitle,
  CCloseButton,
} from '@coreui/react'
import CIcon from '@coreui/icons-react'
import { useUi } from '../layout/UiContext'
import { navigation } from '../layout/nav'

export default function AppSidebar() {
  const { sidebarShow, setSidebarShow, sidebarUnfoldable, setSidebarUnfoldable } = useUi()

  return (
    <CSidebar
      className="border-end"
      colorScheme="dark"
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
          ) : (
            <CNavItem key={i} as={NavLink} to={entry.to} end={entry.to === '/'}>
              <CIcon customClassName="nav-icon" icon={entry.icon} />
              {entry.name}
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
