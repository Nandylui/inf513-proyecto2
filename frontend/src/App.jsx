import { lazy, Suspense } from 'react'
import { Navigate, Route, Routes, useLocation } from 'react-router-dom'
import { useAuth } from './context/AuthContext'
import Layout from './components/layout/Layout'
import { Cargando } from './components/ui'
import Login from './pages/Login'
import Publico from './pages/Publico'
import { NoAutorizado, NoEncontrado } from './pages/Errores'

const Panel = lazy(() => import('./pages/Panel'))
const Usuarios = lazy(() => import('./pages/Usuarios'))
const Miembros = lazy(() => import('./pages/Miembros'))
const Sesiones = lazy(() => import('./pages/Sesiones'))
const Documentos = lazy(() => import('./pages/Documentos'))
const Votacion = lazy(() => import('./pages/Votacion'))
const Resoluciones = lazy(() => import('./pages/Resoluciones'))
const Fiscalizacion = lazy(() => import('./pages/Fiscalizacion'))
const Reportes = lazy(() => import('./pages/Reportes'))
const Pagos = lazy(() => import('./pages/Pagos'))
const PagosAdmin = lazy(() => import('./pages/PagosAdmin'))
const Bitacora = lazy(() => import('./pages/Bitacora'))
const MatrizAcceso = lazy(() => import('./pages/MatrizAcceso'))
const MenuDinamico = lazy(() => import('./pages/MenuDinamico'))
const Correo = lazy(() => import('./pages/Correo'))

/** Ruta protegida: exige sesión y, opcionalmente, un permiso del rol (la matriz de acceso se aplica también en el servidor). */
function Protegida({ permiso, children }) {
  const { usuario, cargando, puede } = useAuth()
  const ubicacion = useLocation()
  if (cargando) return <Cargando texto="Verificando sesión…" />
  if (!usuario) return <Navigate to="/login" replace state={{ desde: ubicacion.pathname + ubicacion.search }} />
  if (permiso && !puede(permiso)) return <NoAutorizado permiso={permiso} />
  return children
}

const R = ({ permiso, el: Pagina }) => <Protegida permiso={permiso}><Pagina /></Protegida>

export default function App() {
  const { usuario } = useAuth()
  return (
    <Suspense fallback={<Cargando />}>
      <Routes>
        <Route path="/login" element={usuario ? <Navigate to="/panel" replace /> : <Login />} />
        <Route path="/publico" element={<Publico />} />
        <Route element={<Protegida><Layout /></Protegida>}>
          <Route index element={<Navigate to="/panel" replace />} />
          <Route path="/panel" element={<Panel />} />
          <Route path="/usuarios" element={<R permiso="usuarios.ver" el={Usuarios} />} />
          <Route path="/miembros" element={<R permiso="miembros.ver" el={Miembros} />} />
          <Route path="/sesiones" element={<R permiso="sesion.ver" el={Sesiones} />} />
          <Route path="/documentos" element={<R permiso="documento.ver" el={Documentos} />} />
          <Route path="/votacion" element={<R permiso="asistencia.ver" el={Votacion} />} />
          <Route path="/resoluciones" element={<R permiso="resolucion.ver" el={Resoluciones} />} />
          <Route path="/fiscalizacion" element={<Fiscalizacion />} />
          <Route path="/reportes" element={<R permiso="reporte.ver" el={Reportes} />} />
          <Route path="/pagos" element={<R permiso="pago.propio" el={Pagos} />} />
          <Route path="/pagos/admin" element={<R permiso="pago.gestionar" el={PagosAdmin} />} />
          <Route path="/bitacora" element={<R permiso="bitacora.ver" el={Bitacora} />} />
          <Route path="/seguridad/matriz" element={<R permiso="rbac.gestionar" el={MatrizAcceso} />} />
          <Route path="/seguridad/menu" element={<R permiso="rbac.gestionar" el={MenuDinamico} />} />
          <Route path="/correo" element={<Correo />} />
          <Route path="*" element={<NoEncontrado />} />
        </Route>
      </Routes>
    </Suspense>
  )
}
