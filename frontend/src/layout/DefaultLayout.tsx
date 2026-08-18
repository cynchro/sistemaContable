import { Outlet } from 'react-router-dom'
import { CContainer } from '@coreui/react'
import AppSidebar from '../components/AppSidebar'
import AppHeader from '../components/AppHeader'
import AppFooter from '../components/AppFooter'
import AppBreadcrumb from '../components/AppBreadcrumb'
import ActiveContextBanner from '../components/ActiveContextBanner'

/** Layout del back-office estilo CoreUI Admin Template: sidebar colapsable + header
 * (toggle, dark mode, perfil) + breadcrumb + contenido + footer. */
export default function DefaultLayout() {
  return (
    <>
      <AppSidebar />
      <div className="wrapper d-flex flex-column min-vh-100" style={{ minWidth: 0 }}>
        <AppHeader />
        <div className="body flex-grow-1">
          <CContainer fluid className="px-4">
            <AppBreadcrumb />
            <ActiveContextBanner />
            <Outlet />
          </CContainer>
        </div>
        <AppFooter />
      </div>
    </>
  )
}
