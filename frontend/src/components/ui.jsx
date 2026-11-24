import { useEffect, useId, useRef } from 'react'
import { X } from 'lucide-react'

/** Campo de formulario accesible (etiqueta, ayuda y mensaje de error asociados con aria). */
export function Campo({ etiqueta, nombre, valor, error, onChange, onBlur, tipo = 'text', opciones, ayuda, requerido, area, filas = 3, className = '', ...resto }) {
  const id = useId()
  const props = {
    id, name: nombre, value: valor ?? '', onBlur, className: 'input',
    'aria-invalid': error ? 'true' : undefined,
    'aria-describedby': error ? `${id}-e` : ayuda ? `${id}-a` : undefined,
    'aria-required': requerido ? 'true' : undefined,
    ...resto,
  }
  let control
  if (opciones) {
    control = (
      <select {...props} onChange={(e) => onChange?.(e.target.value)}>
        {opciones.map((o) => (typeof o === 'object' ? <option key={o.valor} value={o.valor}>{o.texto}</option> : <option key={o} value={o}>{o}</option>))}
      </select>
    )
  } else if (area) {
    control = <textarea rows={filas} {...props} onChange={(e) => onChange?.(e.target.value)} />
  } else if (tipo === 'file') {
    control = <input type="file" {...props} value={undefined} onChange={(e) => onChange?.(e.target.files?.[0] ?? null)} />
  } else {
    control = <input type={tipo} {...props} onChange={(e) => onChange?.(e.target.value)} />
  }
  return (
    <div className={className}>
      <label htmlFor={id} className="label">{etiqueta}{requerido && <span aria-hidden="true" className="text-err"> *</span>}</label>
      {control}
      {ayuda && !error && <p id={`${id}-a`} className="suave peq mt-1">{ayuda}</p>}
      {error && <p id={`${id}-e`} className="error-campo" role="alert">{error}</p>}
    </div>
  )
}

export function Titulo({ icono: Icono, children, acciones, subtitulo }) {
  return (
    <div className="mb-4 flex flex-wrap items-start justify-between gap-3">
      <div>
        <h1 className="flex items-center gap-2 text-2xl font-bold">{Icono && <Icono size={26} className="text-primary" aria-hidden="true" />}{children}</h1>
        {subtitulo && <p className="suave mt-1 text-sm">{subtitulo}</p>}
      </div>
      {acciones && <div className="flex flex-wrap gap-2 no-imprimir">{acciones}</div>}
    </div>
  )
}

export function Cargando({ texto = 'Cargando…' }) {
  return <p className="suave p-4" role="status">{texto}</p>
}

export function Vacio({ children = 'No hay registros para mostrar.' }) {
  return <p className="suave p-6 text-center">{children}</p>
}

/** Diálogo modal con foco atrapado, cierre con Escape y etiquetas ARIA. */
export function Modal({ titulo, onCerrar, children, ancho = 'max-w-xl' }) {
  const ref = useRef(null)
  const idT = useId()
  useEffect(() => {
    const previo = document.activeElement
    const el = ref.current
    el?.querySelector('input,select,textarea,button')?.focus()
    const tecla = (e) => {
      if (e.key === 'Escape') onCerrar()
      if (e.key === 'Tab' && el) {
        const f = [...el.querySelectorAll('a[href],button:not(:disabled),input:not(:disabled),select,textarea')]
        if (!f.length) return
        const a = f[0]; const z = f[f.length - 1]
        if (e.shiftKey && document.activeElement === a) { e.preventDefault(); z.focus() }
        else if (!e.shiftKey && document.activeElement === z) { e.preventDefault(); a.focus() }
      }
    }
    document.addEventListener('keydown', tecla)
    return () => { document.removeEventListener('keydown', tecla); previo?.focus?.() }
  }, [onCerrar])
  return (
    <div className="fixed inset-0 z-50 flex items-start justify-center overflow-auto bg-black/50 p-4" onMouseDown={(e) => e.target === e.currentTarget && onCerrar()}>
      <div ref={ref} role="dialog" aria-modal="true" aria-labelledby={idT} className={`card mt-10 w-full ${ancho} shadow-2xl`}>
        <div className="mb-3 flex items-center justify-between gap-2">
          <h2 id={idT} className="text-lg font-bold">{titulo}</h2>
          <button className="btn btn-sec btn-mini" onClick={onCerrar} aria-label="Cerrar ventana"><X size={16} /></button>
        </div>
        {children}
      </div>
    </div>
  )
}

export function Confirmar({ titulo = 'Confirmar acción', mensaje, onSi, onNo, textoSi = 'Confirmar', peligro }) {
  return (
    <Modal titulo={titulo} onCerrar={onNo} ancho="max-w-md">
      <p className="mb-4">{mensaje}</p>
      <div className="flex justify-end gap-2">
        <button className="btn btn-sec" onClick={onNo}>Cancelar</button>
        <button className={`btn ${peligro ? 'btn-peligro' : ''}`} onClick={onSi}>{textoSi}</button>
      </div>
    </Modal>
  )
}

export function Insignia({ estado }) {
  const e = String(estado ?? '')
  const ok = /APROB|PROMULG|PUBLIC|VIGENTE|ACTIV|APROBADO|PAGAD|CERRAD|FINALIZ|PRESENTE|A_FAVOR|OK/i.test(e)
  const mal = /RECHAZ|VET|ANUL|CANCEL|FALL|EN_CONTRA|AUSENTE|DENEG|VENCID/i.test(e)
  const warn = /PEND|PROCES|CONVOC|OBSERV|ABSTEN|REVIS|EXCUS|ESPERA/i.test(e)
  return <span className={`badge ${mal ? 'b-err' : ok ? 'b-ok' : warn ? 'b-warn' : ''}`}>{e.replaceAll('_', ' ') || '-'}</span>
}

export function Tabs({ valor, onCambio, opciones, etiqueta = 'Secciones' }) {
  return (
    <div role="tablist" aria-label={etiqueta} className="mb-3 flex flex-wrap gap-1 border-b border-line">
      {opciones.map((o) => (
        <button key={o.id} role="tab" aria-selected={valor === o.id} className="tab" onClick={() => onCambio(o.id)}>{o.texto}</button>
      ))}
    </div>
  )
}

export function Tabla({ columnas, filas, vacio, clave }) {
  if (!filas?.length) return <Vacio>{vacio}</Vacio>
  return (
    <div className="tabla-wrap">
      <table className="tabla">
        <thead><tr>{columnas.map((c) => <th key={c.titulo} scope="col" className={c.clase}>{c.titulo}</th>)}</tr></thead>
        <tbody>
          {filas.map((f, i) => (
            <tr key={clave ? f[clave] : i}>{columnas.map((c) => <td key={c.titulo} className={c.clase}>{c.celda ? c.celda(f) : f[c.campo]}</td>)}</tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}
