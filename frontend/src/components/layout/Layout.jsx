import { useEffect, useState } from 'react'
import { Link, Outlet, useLocation } from 'react-router-dom'
import { Bell, LogOut, Menu as MenuIcono, Globe } from 'lucide-react'
import { useAuth } from '../../context/AuthContext'
import api from '../../api/client'
import { rolLegible } from '../../lib/format'
import Sidebar from './Sidebar'
import BuscadorGlobal from './BuscadorGlobal'
import PanelAccesibilidad from './PanelAccesibilidad'
import PiePagina from './PiePagina'

/** Estructura común de las vistas autenticadas: barra lateral dinámica, encabezado con búsqueda global y pie con contador de visitas. */
export default function Layout() {
  const { usuario, menu, logout, puede } = useAuth()
  const { pathname } = useLocation()
  const [movil, setMovil] = useState(false)
  const [sinLeer, setSinLeer] = useState(0)

  useEffect(() => { setMovil(false); document.getElementById('contenido')?.focus?.({ preventScroll: true }) }, [pathname])

  useEffect(() => {
    let vivo = true
    const cargar = () => api.get('notificaciones', { no_leidas: 1 }, { silencioso: true }).then((r) => vivo && setSinLeer(Array.isArray(r) ? r.length : 0)).catch(() => {})
    cargar()
    const t = setInterval(cargar, 60000)
    return () => { vivo = false; clearInterval(t) }
  }, [pathname])

  return (
    <div className="flex min-h-screen">
      <a href="#contenido" className="saltar">Saltar al contenido</a>
      <Sidebar menu={menu} abierta={movil} onCerrar={() => setMovil(false)} />
      <div className="flex min-w-0 flex-1 flex-col">
        <header className="sticky top-0 z-30 flex flex-wrap items-center gap-2 border-b border-line bg-surface px-3 py-2 no-imprimir">
          <button className="btn btn-sec btn-mini lg:hidden" onClick={() => setMovil(true)} aria-label="Abrir menú de navegación" aria-controls="navegacion" aria-expanded={movil}><MenuIcono size={18} /></button>
          <div className="order-3 w-full min-w-0 sm:order-none sm:w-auto sm:flex-1 sm:max-w-2xl">{puede('buscar') && <BuscadorGlobal />}</div>
          <div className="ml-auto flex items-center gap-2">
            <PanelAccesibilidad />
            <Link to="/publico" className="btn btn-sec btn-mini" title="Portal público"><Globe size={16} aria-hidden="true" /><span className="hidden md:inline">Portal público</span></Link>
            <Link to="/fiscalizacion" className="btn btn-sec btn-mini relative" aria-label={`Notificaciones${sinLeer ? `: ${sinLeer} sin leer` : ''}`}>
              <Bell size={16} aria-hidden="true" />
              {sinLeer > 0 && <span className="absolute -right-1.5 -top-1.5 grid h-4 min-w-4 place-items-center rounded-full bg-err px-1 text-[10px] font-bold text-primary-ink">{sinLeer}</span>}
            </Link>
            <div className="hidden text-right leading-tight md:block">
              <b className="block text-sm">{usuario?.nombre}</b>
              <span className="suave peq">{rolLegible(usuario?.rol)}</span>
            </div>
            <button className="btn btn-sec btn-mini" onClick={logout} title="Cerrar sesión"><LogOut size={16} aria-hidden="true" /><span className="hidden sm:inline">Salir</span></button>
          </div>
        </header>
        <main id="contenido" tabIndex={-1} className="mx-auto w-full max-w-7xl flex-1 px-4 py-5 outline-none">
          <Outlet />
        </main>
        <PiePagina />
      </div>
    </div>
  )
}
