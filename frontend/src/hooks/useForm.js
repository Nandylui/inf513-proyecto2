import { useCallback, useState } from 'react'
import { erroresPorCampo } from '../api/client'
import { validar } from '../lib/validators'

/**
 * Formularios con validación en el cliente (esquema de reglas en español) y errores del servidor (Laravel 422).
 *   const f = useForm({ correo: '' }, { correo: [reglas.requerido(), reglas.correo()] })
 *   <Campo etiqueta="Correo" {...f.campo('correo')} />
 *   f.enviar(async (valores) => api.post(...))
 */
export function useForm(inicial, esquema = {}) {
  const [valores, setValores] = useState(inicial)
  const [errores, setErrores] = useState({})
  const [tocados, setTocados] = useState({})
  const [enviando, setEnviando] = useState(false)

  const cambiar = useCallback((campo, valor) => {
    setValores((v) => {
      const nuevo = { ...v, [campo]: valor }
      if (tocados[campo] && esquema[campo]) {
        const e = validar(nuevo, { [campo]: esquema[campo] })
        setErrores((x) => ({ ...x, [campo]: e[campo] }))
      } else {
        setErrores((x) => (x[campo] ? { ...x, [campo]: undefined } : x))
      }
      return nuevo
    })
  }, [esquema, tocados])

  const campo = (nombre) => ({
    nombre,
    valor: valores[nombre] ?? '',
    error: errores[nombre],
    onChange: (v) => cambiar(nombre, v),
    onBlur: () => {
      setTocados((t) => ({ ...t, [nombre]: true }))
      if (esquema[nombre]) setErrores((x) => ({ ...x, [nombre]: validar(valores, { [nombre]: esquema[nombre] })[nombre] }))
    },
  })

  const enviar = (accion) => async (ev) => {
    ev?.preventDefault?.()
    const e = validar(valores, esquema)
    setErrores(e)
    setTocados(Object.fromEntries(Object.keys(esquema).map((k) => [k, true])))
    if (Object.keys(e).length) {
      const primero = document.querySelector('[aria-invalid="true"]')
      primero?.focus?.()
      return false
    }
    setEnviando(true)
    try {
      await accion(valores)
      return true
    } catch (err) {
      setErrores((x) => ({ ...x, ...erroresPorCampo(err) }))
      return false
    } finally {
      setEnviando(false)
    }
  }

  const reiniciar = (nuevo = inicial) => { setValores(nuevo); setErrores({}); setTocados({}) }

  return { valores, errores, enviando, campo, cambiar, enviar, reiniciar, setValores, setErrores }
}
