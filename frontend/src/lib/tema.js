/** Lógica de temas, modo día/noche y accesibilidad (sin dependencias de React para poder probarla). */
export const TEMAS = [
  { id: 'adultos', nombre: 'Adultos', descripcion: 'Sobrio, azul institucional' },
  { id: 'jovenes', nombre: 'Jóvenes', descripcion: 'Vibrante, violeta y fucsia' },
  { id: 'ninos', nombre: 'Niños', descripcion: 'Alegre, letra grande y formas redondeadas' },
]
export const MODOS = [
  { id: 'auto', nombre: 'Automático (según la hora)' },
  { id: 'dia', nombre: 'Día' },
  { id: 'noche', nombre: 'Noche' },
]
export const PREFERENCIAS_BASE = { tema: 'adultos', modo: 'auto', fuente: 100, contraste: false }
export const FUENTE_MIN = 80
export const FUENTE_MAX = 160

/** Modo automático: noche entre las 19:00 y las 06:00. */
export function modoPorHorario(fecha = new Date()) {
  const h = fecha.getHours()
  return h >= 19 || h < 6 ? 'noche' : 'dia'
}
export const modoEfectivo = (modo, fecha) => (modo === 'dia' || modo === 'noche' ? modo : modoPorHorario(fecha))

export function normalizar(p) {
  const base = { ...PREFERENCIAS_BASE, ...(p || {}) }
  if (!TEMAS.some((t) => t.id === base.tema)) base.tema = PREFERENCIAS_BASE.tema
  if (!MODOS.some((m) => m.id === base.modo)) base.modo = PREFERENCIAS_BASE.modo
  base.fuente = Math.min(FUENTE_MAX, Math.max(FUENTE_MIN, Number(base.fuente) || 100))
  base.contraste = Boolean(base.contraste)
  return base
}

export function aplicarAlDocumento(prefs, el = document.documentElement, fecha) {
  const p = normalizar(prefs)
  el.dataset.tema = p.tema
  el.dataset.modo = modoEfectivo(p.modo, fecha)
  if (p.contraste) el.dataset.contraste = 'alto'
  else delete el.dataset.contraste
  el.style.setProperty('--escala', String(p.fuente / 100))
  return p
}
