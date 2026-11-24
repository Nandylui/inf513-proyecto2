import { useEffect, useId, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { Search, Loader2 } from 'lucide-react'
import api from '../../api/client'

/**
 * Búsqueda global predictiva en el encabezado: consulta /api/buscar mientras se escribe (con retardo corto) y navega con
 * teclado (flechas, Enter, Escape). Solo aparecen resultados de los módulos permitidos al rol.
 */
export default function BuscadorGlobal() {
  const nav = useNavigate()
  const id = useId()
  const [q, setQ] = useState('')
  const [grupos, setGrupos] = useState([])
  const [abierto, setAbierto] = useState(false)
  const [cargando, setCargando] = useState(false)
  const [activo, setActivo] = useState(-1)
  const [consultado, setConsultado] = useState('')
  const caja = useRef(null)
  const planos = grupos.flatMap((g) => g.items)

  useEffect(() => {
    const texto = q.trim()
    if (texto.length < 2) { setGrupos([]); setConsultado(''); setCargando(false); return undefined }
    setCargando(true)
    let vigente = true
    const t = setTimeout(() => {
      api.get('buscar', { q: texto }, { silencioso: true })
        .then((r) => { if (vigente) { setGrupos(r.grupos); setConsultado(texto); setActivo(-1) } })
        .catch(() => { if (vigente) { setGrupos([]); setConsultado(texto) } })
        .finally(() => vigente && setCargando(false))
    }, 250)
    return () => { vigente = false; clearTimeout(t) }
  }, [q])

  useEffect(() => {
    const fuera = (e) => { if (caja.current && !caja.current.contains(e.target)) setAbierto(false) }
    document.addEventListener('mousedown', fuera)
    return () => document.removeEventListener('mousedown', fuera)
  }, [])

  const ir = (item) => { setAbierto(false); setQ(''); nav(item.ruta) }
  const tecla = (e) => {
    if (e.key === 'ArrowDown') { e.preventDefault(); setAbierto(true); setActivo((a) => Math.min(planos.length - 1, a + 1)) }
    else if (e.key === 'ArrowUp') { e.preventDefault(); setActivo((a) => Math.max(-1, a - 1)) }
    else if (e.key === 'Enter' && activo >= 0 && planos[activo]) { e.preventDefault(); ir(planos[activo]) }
    else if (e.key === 'Escape') { setAbierto(false) }
  }

  let indice = -1
  const hayTexto = q.trim().length >= 2
  return (
    <div ref={caja} className="relative w-full" role="search">
      <label htmlFor={id} className="sr-only">Buscar en el sistema</label>
      <Search size={18} className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-muted" aria-hidden="true" />
      <input id={id} type="search" className="input !rounded-full !py-2 !pl-10 !pr-9" placeholder="Buscar usuarios, sesiones, documentos, resoluciones…"
        value={q} autoComplete="off" role="combobox" aria-expanded={abierto && hayTexto} aria-controls={`${id}-lista`} aria-autocomplete="list"
        aria-activedescendant={activo >= 0 ? `${id}-op-${activo}` : undefined}
        onChange={(e) => { setQ(e.target.value); setAbierto(true) }} onFocus={() => setAbierto(true)} onKeyDown={tecla} />
      {cargando && <Loader2 size={16} className="absolute right-3 top-1/2 -translate-y-1/2 animate-spin text-muted" aria-hidden="true" />}
      {abierto && hayTexto && (
        <div id={`${id}-lista`} role="listbox" aria-label="Resultados de búsqueda" className="card absolute left-0 right-0 z-40 mt-2 max-h-[70vh] overflow-auto !p-2 shadow-2xl">
          {!cargando && consultado === q.trim() && planos.length === 0 && <p className="suave p-3 text-sm">Sin resultados para «{q.trim()}».</p>}
          {grupos.map((g) => (
            <div key={g.grupo} role="group" aria-label={g.grupo}>
              <p className="suave px-2 pt-2 text-xs font-bold uppercase tracking-wide">{g.grupo}</p>
              {g.items.map((it) => {
                indice += 1
                const n = indice
                return (
                  <button key={`${g.grupo}-${n}`} id={`${id}-op-${n}`} role="option" aria-selected={activo === n} type="button"
                    className={`block w-full rounded-ctl px-2 py-1.5 text-left ${activo === n ? 'bg-primary-soft' : 'hover:bg-surface-2'}`}
                    onMouseEnter={() => setActivo(n)} onClick={() => ir(it)}>
                    <span className="block text-sm font-semibold">{it.titulo}</span>
                    <span className="suave block text-xs">{it.detalle}</span>
                  </button>
                )
              })}
            </div>
          ))}
        </div>
      )}
    </div>
  )
}
