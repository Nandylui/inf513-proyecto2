import { useEffect, useState } from 'react'
import { NavLink, useLocation } from 'react-router-dom'
import { ChevronDown, Landmark } from 'lucide-react'
import { iconoDe } from '../Iconos'

/** Barra de navegación dinámica: se construye con el árbol que devuelve GET /api/menu (filtrado por los permisos del rol). */
export default function Sidebar({ menu, abierta, onCerrar }) {
  const { pathname } = useLocation()
  const [expandidos, setExpandidos] = useState({})

  useEffect(() => {
    // Expande automáticamente el grupo de la página actual.
    const g = menu.find((m) => m.hijos?.some((h) => pathname === h.ruta || pathname.startsWith(`${h.ruta}/`)))
    if (g) setExpandidos((e) => ({ ...e, [g.id_menu ?? g.titulo]: true }))
  }, [pathname, menu])

  const clase = ({ isActive }) =>
    `flex items-center gap-2.5 rounded-ctl px-3 py-2 text-sm font-semibold transition-colors ${isActive ? 'bg-primary text-primary-ink' : 'text-side-ink hover:bg-white/10'}`

  return (
    <>
      {abierta && <div className="fixed inset-0 z-30 bg-black/50 lg:hidden" onClick={onCerrar} aria-hidden="true" />}
      <aside id="navegacion" aria-label="Navegación principal"
        className={`fixed inset-y-0 left-0 z-40 flex w-64 flex-col bg-side text-side-ink transition-transform lg:static lg:translate-x-0 ${abierta ? 'translate-x-0' : '-translate-x-full'}`}>
        <div className="flex items-center gap-2 px-4 py-4">
          <Landmark size={26} aria-hidden="true" />
          <div className="leading-tight"><b className="block">Consejo Facultativo</b><span className="text-xs opacity-80">FICCT · UAGRM</span></div>
        </div>
        <nav className="flex-1 overflow-auto px-2 pb-4" aria-label="Menú del sistema">
          <ul className="grid gap-0.5">
            {menu.map((m) => {
              const Icono = iconoDe(m.icono)
              const clave = m.id_menu ?? m.titulo
              if (!m.hijos?.length) {
                return m.ruta ? (
                  <li key={clave}><NavLink to={m.ruta} className={clase} onClick={onCerrar}><Icono size={18} aria-hidden="true" />{m.titulo}</NavLink></li>
                ) : null
              }
              const abiertoGrupo = expandidos[clave]
              return (
                <li key={clave}>
                  <button type="button" className="flex w-full items-center gap-2.5 rounded-ctl px-3 py-2 text-left text-sm font-semibold hover:bg-white/10"
                    aria-expanded={Boolean(abiertoGrupo)} onClick={() => setExpandidos((e) => ({ ...e, [clave]: !e[clave] }))}>
                    <Icono size={18} aria-hidden="true" /><span className="flex-1">{m.titulo}</span>
                    <ChevronDown size={16} className={`transition-transform ${abiertoGrupo ? 'rotate-180' : ''}`} aria-hidden="true" />
                  </button>
                  {abiertoGrupo && (
                    <ul className="ml-4 mt-0.5 grid gap-0.5 border-l border-white/20 pl-2">
                      {m.hijos.map((h) => {
                        const I = iconoDe(h.icono)
                        return <li key={h.id_menu ?? h.titulo}><NavLink to={h.ruta} end className={clase} onClick={onCerrar}><I size={16} aria-hidden="true" />{h.titulo}</NavLink></li>
                      })}
                    </ul>
                  )}
                </li>
              )
            })}
          </ul>
        </nav>
      </aside>
    </>
  )
}
