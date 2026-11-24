import axios from 'axios'

/**
 * Cliente HTTP de la capa de presentación. Todas las pantallas consumen la API RESTful de Laravel a través de este módulo.
 * La URL base es relativa a la página ("./api"), de modo que funciona en cualquier subdirectorio del servidor
 * (p. ej. /inf513/grupo07sa/proyecto2/) y, en desarrollo, a través del proxy de Vite hacia http://127.0.0.1:8000.
 */
export const API_URL = (import.meta.env.VITE_API_URL || new URL('api', document.baseURI).pathname).replace(/\/$/, '')

const TOKEN_KEY = 'cf_token'
export const guardarToken = (t) => (t ? localStorage.setItem(TOKEN_KEY, t) : localStorage.removeItem(TOKEN_KEY))
export const leerToken = () => { try { return localStorage.getItem(TOKEN_KEY) } catch { return null } }

export const api = axios.create({ baseURL: API_URL, headers: { Accept: 'application/json' }, timeout: 30000 })

/** Gancho para que el contexto de autenticación y los avisos reaccionen a los errores globales. */
export const manejadores = { sesionExpirada: () => {}, error: () => {} }

api.interceptors.request.use((cfg) => {
  const t = leerToken()
  if (t) cfg.headers.Authorization = `Bearer ${t}`
  return cfg
})

/** Mensaje legible en español para cualquier error de red o de la API. */
export function mensajeDeError(e) {
  if (!e) return 'Ocurrió un error inesperado.'
  if (e.code === 'ECONNABORTED') return 'El servidor tardó demasiado en responder. Intente nuevamente.'
  if (!e.response) return 'No hay conexión con el servidor. Verifique su red o que el backend esté en línea.'
  const d = e.response.data
  if (d?.errors) return Object.values(d.errors).flat().join(' ')
  if (d?.message) return d.message
  return `Error ${e.response.status}: ${e.response.statusText || 'solicitud fallida'}`
}

/** Errores por campo (422) como { campo: 'mensaje' }, para mostrarlos junto a cada control del formulario. */
export function erroresPorCampo(e) {
  const err = e?.response?.data?.errors
  if (!err) return {}
  return Object.fromEntries(Object.entries(err).map(([k, v]) => [k, Array.isArray(v) ? v[0] : String(v)]))
}

api.interceptors.response.use(
  (r) => r,
  (e) => {
    const st = e.response?.status
    const url = e.config?.url || ''
    const silencioso = e.config?.silencioso
    if (st === 401 && !url.endsWith('auth/login')) {
      manejadores.sesionExpirada()
    } else if (!silencioso && !(st === 422 && e.response?.data?.errors)) {
      manejadores.error(mensajeDeError(e))
    }
    return Promise.reject(e)
  },
)

const get = (url, params, opts) => api.get(url, { params, ...opts }).then((r) => r.data)
const post = (url, body, opts) => api.post(url, body, opts).then((r) => r.data)
const put = (url, body, opts) => api.put(url, body, opts).then((r) => r.data)
const patch = (url, body, opts) => api.patch(url, body, opts).then((r) => r.data)
const del = (url, opts) => api.delete(url, opts).then((r) => r.data)

/** Descarga un archivo autenticado (CSV, documentos) y lo guarda en el navegador. */
async function descargar(url, nombre, params) {
  const r = await api.get(url, { params, responseType: 'blob' })
  const a = document.createElement('a')
  a.href = URL.createObjectURL(r.data)
  a.download = nombre
  document.body.appendChild(a)
  a.click()
  a.remove()
  URL.revokeObjectURL(a.href)
}

export default { get, post, put, patch, delete: del, descargar }
