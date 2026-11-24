/**
 * Validación del lado del cliente (todos los mensajes en español).
 * Cada regla devuelve un mensaje de error o null. El servidor repite estas validaciones (Laravel).
 */
const vacio = (v) => v === undefined || v === null || String(v).trim() === ''

export const reglas = {
  requerido: (msg = 'Este campo es obligatorio.') => (v) => (vacio(v) ? msg : null),
  correo: (msg = 'Ingrese un correo electrónico válido (ej. nombre@dominio.com).') => (v) => (vacio(v) || /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(String(v).trim()) ? null : msg),
  minimo: (n, msg) => (v) => (vacio(v) || String(v).trim().length >= n ? null : msg || `Debe tener al menos ${n} caracteres.`),
  maximo: (n, msg) => (v) => (vacio(v) || String(v).trim().length <= n ? null : msg || `No puede superar los ${n} caracteres.`),
  patron: (re, msg) => (v) => (vacio(v) || re.test(String(v).trim()) ? null : msg),
  numero: (msg = 'Ingrese un número válido.') => (v) => (vacio(v) || (!Number.isNaN(Number(v)) && Number.isFinite(Number(v))) ? null : msg),
  entero: (msg = 'Ingrese un número entero.') => (v) => (vacio(v) || /^-?\d+$/.test(String(v).trim()) ? null : msg),
  rango: (min, max, msg) => (v) => (vacio(v) || (Number(v) >= min && Number(v) <= max) ? null : msg || `Debe estar entre ${min} y ${max}.`),
  mayorQue: (n, msg) => (v) => (vacio(v) || Number(v) > n ? null : msg || `Debe ser mayor que ${n}.`),
  fechaFutura: (msg = 'La fecha debe ser posterior a la actual.') => (v) => (vacio(v) || new Date(v) > new Date() ? null : msg),
  fechaPosterior: (otro, msg = 'La fecha final debe ser posterior a la inicial.') => (v, todos) => (vacio(v) || vacio(todos?.[otro]) || new Date(v) > new Date(todos[otro]) ? null : msg),
  // Cédula de identidad: 4 a 20 caracteres alfanuméricos (coincide con el servidor).
  ci: (msg = 'El CI debe tener entre 4 y 20 caracteres alfanuméricos.') => (v) => (vacio(v) || /^[0-9A-Za-z-]{4,20}$/.test(String(v).trim()) ? null : msg),
  soloLetras: (msg = 'Use solo letras y espacios.') => (v) => (vacio(v) || /^[\p{L}\s.'-]+$/u.test(String(v).trim()) ? null : msg),
  telefono: (msg = 'Ingrese un teléfono válido (7 a 15 dígitos).') => (v) => (vacio(v) || /^\+?[\d\s-]{7,15}$/.test(String(v).trim()) ? null : msg),
  claveSegura: (msg = 'La contraseña debe tener al menos 8 caracteres.') => (v) => (vacio(v) || String(v).length >= 8 ? null : msg),
}

/** Algoritmo de Luhn para números de tarjeta. */
export function luhn(numero) {
  const d = String(numero).replace(/\D/g, '')
  if (d.length < 13) return false
  let suma = 0
  let alterno = false
  for (let i = d.length - 1; i >= 0; i--) {
    let n = Number(d[i])
    if (alterno) { n *= 2; if (n > 9) n -= 9 }
    suma += n
    alterno = !alterno
  }
  return suma % 10 === 0
}

export const reglasTarjeta = {
  numero: (v) => {
    const d = String(v ?? '').replace(/[\s-]/g, '')
    if (!d) return 'Ingrese el número de la tarjeta.'
    if (!/^\d{13,19}$/.test(d)) return 'El número de tarjeta debe tener entre 13 y 19 dígitos.'
    return luhn(d) ? null : 'El número de tarjeta no es válido (falla la verificación de dígito).'
  },
  vencimiento: (v) => {
    const m = /^(0[1-9]|1[0-2])\/(\d{2})$/.exec(String(v ?? '').trim())
    if (!m) return 'Use el formato MM/AA (ej. 08/29).'
    const fin = new Date(2000 + Number(m[2]), Number(m[1]), 0, 23, 59, 59)
    return fin < new Date() ? 'La tarjeta está vencida.' : null
  },
  cvv: (v) => (/^\d{3,4}$/.test(String(v ?? '')) ? null : 'El CVV debe tener 3 o 4 dígitos.'),
}

/** Ejecuta las reglas de cada campo y devuelve { campo: mensaje }. Un campo puede tener varias reglas (se muestra la primera que falle). */
export function validar(valores, esquema) {
  const errores = {}
  for (const [campo, lista] of Object.entries(esquema)) {
    for (const regla of [].concat(lista)) {
      const msg = regla(valores[campo], valores)
      if (msg) { errores[campo] = msg; break }
    }
  }
  return errores
}
