import { Routes, Route, Navigate } from 'react-router-dom'
import DefaultLayout from './layout/DefaultLayout'
import Dashboard from './modules/dashboard/Dashboard'

function App() {
  return (
    <Routes>
      <Route path="/" element={<DefaultLayout />}>
        <Route index element={<Dashboard />} />
      </Route>
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  )
}

export default App
