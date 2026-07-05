import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { BrowserRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'

import '@coreui/coreui/dist/css/coreui.min.css'
import './index.css'
import App from './App.tsx'
import { AuthProvider } from './auth/AuthContext'
import { UiProvider } from './layout/UiContext'
import { ActiveProvider } from './layout/ActiveContext'

const queryClient = new QueryClient({
  defaultOptions: { queries: { refetchOnWindowFocus: false, retry: 1 } },
})

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <QueryClientProvider client={queryClient}>
      <AuthProvider>
        <ActiveProvider>
          <UiProvider>
            <BrowserRouter>
              <App />
            </BrowserRouter>
          </UiProvider>
        </ActiveProvider>
      </AuthProvider>
    </QueryClientProvider>
  </StrictMode>,
)
