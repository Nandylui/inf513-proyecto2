import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { Landmark } from 'lucide-react'
import api from '../api/client'
import { Insignia, Tabla } from '../components/ui'
import PanelAccesibilidad from '../components/layout/PanelAccesibilidad'
import PiePagina from '../components/layout/PiePagina'
import { fechaHora } from '../lib/format'

/** Portal de información pública (Art. 56 y 57): accesible sin iniciar sesión. */
export default function Publico() {
  const [sesiones, setSesiones] = useState([])
  const [resoluciones, setResoluciones] = useState([])
  const [acta, setActa] = useState(null)

  useEffect(() => {
    api.get('publico/sesiones').then(setSesiones)
    api.get('publico/resoluciones').then(setResoluciones)
  }, [])

  return (
    <div className="flex min-h-screen flex-col">
      <a href="#contenido" className="saltar">Saltar al contenido</a>
      <header className="flex flex-wrap items-center justify-between gap-3 bg-side px-6 py-4 text-side-ink">
        <div className="flex items-center gap-3"><Landmark size={28} aria-hidden="true" /><div><b className="block text-lg">Consejo Facultativo · FICCT · UAGRM</b><span className="text-sm opacity-80">Portal de información pública (Art. 56 y 57)</span></div></div>
        <div className="flex items-center gap-2"><PanelAccesibilidad /><Link to="/login" className="btn">Ingresar al sistema →</Link></div>
      </header>
      <main id="contenido" className="mx-auto w-full max-w-7xl flex-1 px-4 py-5">
        <div className="grid items-start gap-4 lg:grid-cols-2">
          <section className="card" aria-label="Sesiones públicas">
            <h1 className="mb-2 text-lg font-bold">Sesiones públicas del Consejo</h1>
            <Tabla clave="id_sesion" filas={sesiones} vacio="Sin sesiones registradas."
              columnas={[
                { titulo: 'Fecha', celda: (s) => fechaHora(s.fecha_hora) }, { titulo: 'Tipo', campo: 'tipo' }, { titulo: 'Lugar', campo: 'lugar' },
                { titulo: 'Estado', celda: (s) => <Insignia estado={s.estado} /> },
                { titulo: 'Acta', celda: (s) => s.estado === 'FINALIZADA' && <button className="btn btn-sec btn-mini" onClick={() => api.get(`publico/actas/${s.id_sesion}`).then(setActa)}>Ver acta</button> },
              ]} />
          </section>
          <section className="card" aria-label="Resoluciones publicadas">
            <h2 className="mb-2 text-lg font-bold">Resoluciones publicadas</h2>
            <Tabla clave="id_resolucion" filas={resoluciones} vacio="Sin resoluciones."
              columnas={[
                { titulo: 'Número', celda: (r) => <b>{r.numero}</b> }, { titulo: 'Asunto', celda: (r) => r.documento?.titulo },
                { titulo: 'Estado', celda: (r) => <Insignia estado={r.estado} /> }, { titulo: 'Gaceta', celda: (r) => r.gaceta_referencia ?? '-' },
              ]} />
          </section>
        </div>
        {acta && (
          <section className="card mt-4" aria-label="Acta">
            <div className="mb-2 flex items-center justify-between"><h2 className="font-bold">Acta de la sesión #{acta.id_sesion}</h2><button className="btn btn-sec btn-mini" onClick={() => setActa(null)}>Cerrar</button></div>
            <pre className="salida">{acta.contenido}</pre><p className="peq suave mono mt-2">Sello SHA-256: {acta.hash}</p>
          </section>
        )}
      </main>
      <PiePagina />
    </div>
  )
}
