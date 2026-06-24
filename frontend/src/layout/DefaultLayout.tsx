import { Outlet } from 'react-router-dom'
import {
  CSidebar,
  CSidebarHeader,
  CSidebarBrand,
  CSidebarNav,
  CNavTitle,
  CNavItem,
  CHeader,
  CHeaderBrand,
  CContainer,
} from '@coreui/react'

/**
 * Layout base del back-office (CoreUI): sidebar de navegación por módulo + header,
 * y el contenido de cada ruta en el <Outlet />. Los ítems apuntan a placeholders
 * hasta que se construya cada pantalla (tasks del feature Frontend).
 */
export default function DefaultLayout() {
  return (
    <div className="d-flex">
      <CSidebar className="border-end" colorScheme="dark">
        <CSidebarHeader className="border-bottom">
          <CSidebarBrand>Estudio Haddad</CSidebarBrand>
        </CSidebarHeader>
        <CSidebarNav>
          <CNavTitle>General</CNavTitle>
          <CNavItem href="#/">Inicio</CNavItem>
          <CNavItem href="#/empresas">Empresas / Contribuyentes</CNavItem>
          <CNavTitle>IVA</CNavTitle>
          <CNavItem href="#/iva/comprobantes">Comprobantes</CNavItem>
          <CNavItem href="#/iva/libro">Libro IVA y DDJJ</CNavItem>
          <CNavItem href="#/afip">Factura electrónica</CNavItem>
          <CNavTitle>Estudio</CNavTitle>
          <CNavItem href="#/sueldos">Sueldos</CNavItem>
          <CNavItem href="#/gestion">Vencimientos y tareas</CNavItem>
          <CNavItem href="#/admin">Administración</CNavItem>
        </CSidebarNav>
      </CSidebar>

      <div className="d-flex flex-column flex-grow-1">
        <CHeader className="border-bottom px-4">
          <CHeaderBrand>Sistema Contable</CHeaderBrand>
        </CHeader>
        <main className="flex-grow-1 bg-body-tertiary">
          <CContainer fluid className="py-4">
            <Outlet />
          </CContainer>
        </main>
      </div>
    </div>
  )
}
