import { Routes, Route, Navigate } from 'react-router-dom'
import ProtectedRoute from './auth/ProtectedRoute'
import DefaultLayout from './layout/DefaultLayout'
import LoginPage from './modules/auth/LoginPage'
import Dashboard from './modules/dashboard/Dashboard'
import EmpresasList from './modules/empresas/EmpresasList'
import PeriodosList from './modules/periodos/PeriodosList'
import EnConstruccion from './components/EnConstruccion'

function App() {
  return (
    <Routes>
      <Route path="/login" element={<LoginPage />} />
      <Route element={<ProtectedRoute />}>
        <Route path="/" element={<DefaultLayout />}>
          <Route index element={<Dashboard />} />
          <Route path="empresas" element={<EmpresasList />} />
          <Route path="empresas/:empresaId/periodos" element={<PeriodosList />} />
          <Route path="iva" element={<EnConstruccion titulo="Comprobantes de IVA" />} />
          <Route path="iva/libro" element={<EnConstruccion titulo="Libro IVA y DDJJ" />} />
          <Route path="afip" element={<EnConstruccion titulo="Factura electrónica" />} />
          <Route path="sueldos" element={<EnConstruccion titulo="Sueldos" />} />
          <Route path="gestion" element={<EnConstruccion titulo="Gestión del estudio" />} />
          <Route path="admin" element={<EnConstruccion titulo="Administración" />} />
        </Route>
      </Route>
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  )
}

export default App
