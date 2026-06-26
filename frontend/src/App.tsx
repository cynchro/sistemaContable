import { Routes, Route, Navigate } from 'react-router-dom'
import ProtectedRoute from './auth/ProtectedRoute'
import DefaultLayout from './layout/DefaultLayout'
import LoginPage from './modules/auth/LoginPage'
import Dashboard from './modules/dashboard/Dashboard'
import EmpresasList from './modules/empresas/EmpresasList'
import PeriodosList from './modules/periodos/PeriodosList'
import EnConstruccion from './components/EnConstruccion'
import SujetosList from './modules/iva/sujetos/SujetosList'
import VentasList from './modules/iva/ventas/VentasList'
import ComprasList from './modules/iva/compras/ComprasList'
import LibroIvaPage from './modules/iva/libro/LibroIvaPage'
import ActividadesPage from './modules/iva/actividades/ActividadesPage'
import AfipPage from './modules/afip/AfipPage'
import SueldosPage from './modules/sueldos/SueldosPage'
import GestionPage from './modules/gestion/GestionPage'
import AdminPage from './modules/admin/AdminPage'

function App() {
  return (
    <Routes>
      <Route path="/login" element={<LoginPage />} />
      <Route element={<ProtectedRoute />}>
        <Route path="/" element={<DefaultLayout />}>
          <Route index element={<Dashboard />} />
          <Route path="empresas" element={<EmpresasList />} />
          <Route path="empresas/:empresaId/periodos" element={<PeriodosList />} />
          <Route path="empresas/:empresaId/clientes" element={<SujetosList recurso="clientes" />} />
          <Route path="empresas/:empresaId/proveedores" element={<SujetosList recurso="proveedores" />} />
          <Route path="empresas/:empresaId/actividades" element={<ActividadesPage />} />
          <Route path="empresas/:empresaId/periodos/:periodoId/ventas" element={<VentasList />} />
          <Route path="empresas/:empresaId/periodos/:periodoId/compras" element={<ComprasList />} />
          <Route path="empresas/:empresaId/periodos/:periodoId/libro-iva" element={<LibroIvaPage />} />
          <Route path="iva" element={<EnConstruccion titulo="Comprobantes de IVA" />} />
          <Route path="iva/libro" element={<EnConstruccion titulo="Libro IVA y DDJJ" />} />
          <Route path="afip" element={<AfipPage />} />
          <Route path="sueldos" element={<SueldosPage />} />
          <Route path="gestion" element={<GestionPage />} />
          <Route path="admin" element={<AdminPage />} />
        </Route>
      </Route>
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  )
}

export default App
