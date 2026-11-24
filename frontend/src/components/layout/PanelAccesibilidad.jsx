import { useEffect, useRef, useState } from 'react'
import { Accessibility, Contrast, Minus, Moon, Plus, RotateCcw, Sun, SunMoon, X } from 'lucide-react'
import { useTema } from '../../context/TemaContext'
import { FUENTE_MAX, FUENTE_MIN, MODOS, TEMAS, modoEfectivo } from '../../lib/tema'

/**
 * Herramientas de accesibilidad y apariencia: tema (niños / jóvenes / adultos), modo día/noche (automático o manual),
 * tamaño de fuente y alto contraste. Disponible en todas las vistas, incluso antes de iniciar sesión.
 */
export default function PanelAccesibilidad({ variante = 'boton' }) {
  const { prefs, cambiar, restablecer } = useTema()
  const [abierto, setAbierto] = useState(false)
  const ref = useRef(null)

  useEffect(() => {
    if (!abierto) return undefined
    const fuera = (e) => { if (ref.current && !ref.current.contains(e.target)) setAbierto(false) }
    const esc = (e) => { if (e.key === 'Escape') setAbierto(false) }
    document.addEventListener('mousedown', fuera)
    document.addEventListener('keydown', esc)
    return () => { document.removeEventListener('mousedown', fuera); document.removeEventListener('keydown', esc) }
  }, [abierto])

  const efectivo = modoEfectivo(prefs.modo)
  const IconoModo = efectivo === 'noche' ? Moon : Sun
  const paso = (d) => cambiar({ fuente: Math.min(FUENTE_MAX, Math.max(FUENTE_MIN, prefs.fuente + d)) })

  return (
    <div className="relative no-imprimir" ref={ref}>
      <button className="btn btn-sec btn-mini" onClick={() => setAbierto((a) => !a)} aria-expanded={abierto} aria-haspopup="dialog" aria-controls="panel-accesibilidad">
        <Accessibility size={16} aria-hidden="true" /> <span className="hidden sm:inline">Accesibilidad</span><span className="sm:hidden sr-only">Accesibilidad</span>
        <IconoModo size={14} aria-hidden="true" />
      </button>
      {abierto && (
        <div id="panel-accesibilidad" role="dialog" aria-label="Apariencia y accesibilidad" className="card absolute right-0 z-40 mt-2 w-[min(22rem,calc(100vw-1.5rem))] shadow-2xl">
          <div className="mb-2 flex items-center justify-between">
            <h2 className="font-bold">Apariencia y accesibilidad</h2>
            <button className="btn btn-sec btn-mini" onClick={() => setAbierto(false)} aria-label="Cerrar panel"><X size={14} /></button>
          </div>

          <fieldset className="mb-3">
            <legend className="label">Tema visual</legend>
            <div className="grid grid-cols-3 gap-2">
              {TEMAS.map((t) => (
                <button key={t.id} type="button" onClick={() => cambiar({ tema: t.id })} aria-pressed={prefs.tema === t.id}
                  className={`rounded-ctl border-2 p-2 text-center text-sm font-semibold ${prefs.tema === t.id ? 'border-primary bg-primary-soft text-primary' : 'border-line'}`} title={t.descripcion}>
                  {t.nombre}
                </button>
              ))}
            </div>
          </fieldset>

          <fieldset className="mb-3">
            <legend className="label">Modo de color {prefs.modo === 'auto' && <span className="font-normal">(ahora: {efectivo === 'noche' ? 'noche' : 'día'})</span>}</legend>
            <div className="grid gap-1">
              {MODOS.map((m) => {
                const I = m.id === 'dia' ? Sun : m.id === 'noche' ? Moon : SunMoon
                return (
                  <label key={m.id} className={`flex cursor-pointer items-center gap-2 rounded-ctl border-2 px-2 py-1.5 text-sm ${prefs.modo === m.id ? 'border-primary bg-primary-soft' : 'border-line'}`}>
                    <input type="radio" name="modo" className="sr-only" checked={prefs.modo === m.id} onChange={() => cambiar({ modo: m.id })} />
                    <I size={15} aria-hidden="true" /> {m.nombre}
                  </label>
                )
              })}
            </div>
            <p className="suave peq mt-1">Automático: noche de 19:00 a 06:00.</p>
          </fieldset>

          <div className="mb-3">
            <span className="label" id="lbl-fuente">Tamaño del texto: {prefs.fuente}%</span>
            <div className="flex items-center gap-2" role="group" aria-labelledby="lbl-fuente">
              <button className="btn btn-sec btn-mini" onClick={() => paso(-10)} disabled={prefs.fuente <= FUENTE_MIN} aria-label="Reducir tamaño del texto"><Minus size={14} /></button>
              <input type="range" className="flex-1" min={FUENTE_MIN} max={FUENTE_MAX} step={10} value={prefs.fuente} onChange={(e) => cambiar({ fuente: Number(e.target.value) })} aria-labelledby="lbl-fuente" />
              <button className="btn btn-sec btn-mini" onClick={() => paso(10)} disabled={prefs.fuente >= FUENTE_MAX} aria-label="Aumentar tamaño del texto"><Plus size={14} /></button>
            </div>
          </div>

          <label className="mb-3 flex cursor-pointer items-center gap-2 text-sm font-semibold">
            <input type="checkbox" checked={prefs.contraste} onChange={(e) => cambiar({ contraste: e.target.checked })} />
            <Contrast size={16} aria-hidden="true" /> Alto contraste
          </label>

          <button className="btn btn-sec btn-mini" onClick={restablecer}><RotateCcw size={14} aria-hidden="true" /> Restablecer</button>
        </div>
      )}
    </div>
  )
}
