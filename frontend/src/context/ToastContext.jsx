import { createContext, useCallback, useContext, useMemo, useState } from 'react'
import { AlertTriangle, CheckCircle2, Info, X } from 'lucide-react'

const Ctx = createContext(null)
export const useToast = () => useContext(Ctx)

export function ToastProvider({ children }) {
  const [items, setItems] = useState([])
  const quitar = useCallback((id) => setItems((l) => l.filter((x) => x.id !== id)), [])
  const mostrar = useCallback((tipo, texto) => {
    const id = Math.random().toString(36).slice(2)
    setItems((l) => [...l.slice(-3), { id, tipo, texto }])
    setTimeout(() => quitar(id), tipo === 'error' ? 8000 : 4500)
  }, [quitar])
  const api = useMemo(() => ({ ok: (t) => mostrar('ok', t), error: (t) => mostrar('error', t), info: (t) => mostrar('info', t) }), [mostrar])

  const icono = { ok: CheckCircle2, error: AlertTriangle, info: Info }
  const clase = { ok: 'b-ok', error: 'b-err', info: '' }
  return (
    <Ctx.Provider value={api}>
      {children}
      <div className="fixed bottom-4 right-4 z-[60] flex w-[min(24rem,calc(100vw-2rem))] flex-col gap-2 no-imprimir" role="region" aria-label="Avisos" aria-live="polite">
        {items.map((t) => {
          const I = icono[t.tipo]
          return (
            <div key={t.id} role={t.tipo === 'error' ? 'alert' : 'status'}
              className={`flex items-start gap-2 rounded-ctl border-2 border-line bg-surface p-3 text-sm shadow-lg`}>
              <span className={`badge ${clase[t.tipo]} !p-1`}><I size={16} aria-hidden="true" /></span>
              <p className="flex-1">{t.texto}</p>
              <button className="text-muted hover:text-ink" onClick={() => quitar(t.id)} aria-label="Cerrar aviso"><X size={16} /></button>
            </div>
          )
        })}
      </div>
    </Ctx.Provider>
  )
}
