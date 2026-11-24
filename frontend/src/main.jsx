import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { HashRouter } from 'react-router-dom'
import './index.css'
import App from './App'
import { ToastProvider } from './context/ToastContext'
import { AuthProvider } from './context/AuthContext'
import { TemaProvider } from './context/TemaContext'

// HashRouter: la SPA funciona en cualquier subdirectorio del servidor (/inf513/grupo07sa/proyecto2/) sin reglas de reescritura.
createRoot(document.getElementById('root')).render(
  <StrictMode>
    <HashRouter>
      <ToastProvider>
        <AuthProvider>
          <TemaProvider>
            <App />
          </TemaProvider>
        </AuthProvider>
      </ToastProvider>
    </HashRouter>
  </StrictMode>,
)
