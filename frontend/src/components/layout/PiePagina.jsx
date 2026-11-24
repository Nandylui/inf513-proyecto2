import { useEffect, useRef, useState } from 'react'
import { useLocation } from 'react-router-dom'
import { Eye } from 'lucide-react'
import api from '../../api/client'

/** Pie de página común a todas las vistas con el contador persistente de visitas por página (tabla visita_pagina). */
export default function PiePagina() {
  const { pathname } = useLocation()
  const [datos, setDatos] = useState(null)
  const ultima = useRef('')

  useEffect(() => {
    if (ultima.current === pathname) return // evita el doble conteo de React StrictMode en desarrollo
    ultima.current = pathname
    api.post('visitas', { pagina: pathname || '/' }, { silencioso: true }).then(setDatos).catch(() => setDatos(null))
  }, [pathname])

  return (
    <footer className="no-imprimir mt-8 border-t border-line px-4 py-3 text-sm text-muted">
      <div className="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-2">
        <span>Consejo Facultativo · FICCT – UAGRM · INF513 Tecnología Web · Grupo 07SA</span>
        <span className="flex items-center gap-1.5" aria-live="polite">
          <Eye size={15} aria-hidden="true" />
          {datos
            ? <>Visitas a esta página: <b className="text-ink" data-testid="visitas-pagina">{datos.visitas.toLocaleString('es-BO')}</b> · Total del sitio: <b className="text-ink">{datos.total.toLocaleString('es-BO')}</b></>
            : 'Contador de visitas no disponible'}
        </span>
      </div>
    </footer>
  )
}
