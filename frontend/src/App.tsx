import { Routes, Route, Navigate } from 'react-router-dom'
import ProtectedRoute from './auth/ProtectedRoute'
import DefaultLayout from './layout/DefaultLayout'
import LoginPage from './modules/auth/LoginPage'
import Dashboard from './modules/dashboard/Dashboard'
import EmpresasList from './modules/empresas/EmpresasList'

function App() {
  return (
    <Routes>
      <Route path="/login" element={<LoginPage />} />
      <Route element={<ProtectedRoute />}>
        <Route path="/" element={<DefaultLayout />}>
          <Route index element={<Dashboard />} />
          <Route path="empresas" element={<EmpresasList />} />
        </Route>
      </Route>
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  )
}

export default App
