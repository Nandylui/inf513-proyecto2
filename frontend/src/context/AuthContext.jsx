import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react'
import api, { guardarToken, leerToken, manejadores } from '../api/client'
import { useToast } from './ToastContext'

const Ctx = createContext(null)
export const useAuth = () => useContext(Ctx)

export function AuthProvider({ children }) {
  const toast = useToast()
  const [usuario, setUsuario] = useState(null)
  const [menu, setMenu] = useState([])
  const [cargando, setCargando] = useState(Boolean(leerToken()))

  const cerrarLocal = useCallback(() => {
    guardarToken(null)
    setUsuario(null)
    setMenu([])
  }, [])

  const cargarMenu = useCallback(() => api.get('menu', null, { silencioso: true }).then(setMenu).catch(() => setMenu([])), [])

  const refrescar = useCallback(async () => {
    const p = await api.get('auth/me', null, { silencioso: true })
    setUsuario(p)
    await cargarMenu()
    return p
  }, [cargarMenu])

  useEffect(() => {
    manejadores.sesionExpirada = () => {
      if (leerToken()) toast.error('Su sesión expiró o fue cerrada. Ingrese nuevamente.')
      cerrarLocal()
    }
    manejadores.error = (m) => toast.error(m)
  }, [toast, cerrarLocal])

  useEffect(() => {
    if (!leerToken()) { setCargando(false); return }
    refrescar().catch(() => cerrarLocal()).finally(() => setCargando(false))
  }, [refrescar, cerrarLocal])

  const login = useCallback(async (correo, password) => {
    const r = await api.post('auth/login', { correo, password })
    guardarToken(r.token)
    setUsuario(r.usuario)
    await cargarMenu()
    return r.usuario
  }, [cargarMenu])

  const logout = useCallback(async () => {
    try { await api.post('auth/logout', {}, { silencioso: true }) } catch { /* la sesión local se cierra igual */ }
    cerrarLocal()
  }, [cerrarLocal])

  const valor = useMemo(() => ({
    usuario, menu, cargando, login, logout, refrescar,
    puede: (accion) => Boolean(usuario?.permisos?.includes(accion)),
  }), [usuario, menu, cargando, login, logout, refrescar])

  return <Ctx.Provider value={valor}>{children}</Ctx.Provider>
}
