import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState } from 'react'
import api from '../api/client'
import { aplicarAlDocumento, normalizar, PREFERENCIAS_BASE } from '../lib/tema'
import { useAuth } from './AuthContext'

const Ctx = createContext(null)
export const useTema = () => useContext(Ctx)
const CLAVE = 'cf_prefs'

function leerLocal() {
  try { return normalizar(JSON.parse(localStorage.getItem(CLAVE) || 'null') || PREFERENCIAS_BASE) } catch { return PREFERENCIAS_BASE }
}

/**
 * Preferencias de apariencia y accesibilidad: tema (niños / jóvenes / adultos), modo día/noche (automático o manual),
 * tamaño de fuente y alto contraste. Se guardan en el navegador y, si hay sesión, también en el perfil del usuario.
 */
export function TemaProvider({ children }) {
  const { usuario } = useAuth()
  const [prefs, setPrefs] = useState(leerLocal)
  const usuarioId = useRef(null)
  const temporizador = useRef(null)

  // Al iniciar sesión se adoptan las preferencias guardadas en el servidor.
  useEffect(() => {
    if (usuario && usuarioId.current !== usuario.id_usuario) {
      usuarioId.current = usuario.id_usuario
      if (usuario.preferencias) setPrefs(normalizar(usuario.preferencias))
      else api.put('auth/preferencias', prefs, { silencioso: true }).catch(() => {}) // primera vez: se conserva lo elegido y se guarda en el perfil
    }
    if (!usuario) usuarioId.current = null
  }, [usuario]) // eslint-disable-line react-hooks/exhaustive-deps

  // Aplica al documento y revisa cada minuto el cambio automático día/noche.
  useEffect(() => {
    aplicarAlDocumento(prefs)
    try { localStorage.setItem(CLAVE, JSON.stringify(prefs)) } catch { /* almacenamiento no disponible */ }
    if (prefs.modo !== 'auto') return undefined
    const id = setInterval(() => aplicarAlDocumento(prefs), 60000)
    return () => clearInterval(id)
  }, [prefs])

  const cambiar = useCallback((parcial) => {
    setPrefs((anterior) => {
      const nuevo = normalizar({ ...anterior, ...parcial })
      clearTimeout(temporizador.current)
      temporizador.current = setTimeout(() => {
        if (usuarioId.current) api.put('auth/preferencias', nuevo, { silencioso: true }).catch(() => {})
      }, 600)
      return nuevo
    })
  }, [])

  const valor = useMemo(() => ({ prefs, cambiar, restablecer: () => cambiar(PREFERENCIAS_BASE) }), [prefs, cambiar])
  return <Ctx.Provider value={valor}>{children}</Ctx.Provider>
}
