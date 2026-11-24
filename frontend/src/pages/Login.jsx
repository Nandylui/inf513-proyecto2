import { useState } from 'react'
import { Link, useLocation, useNavigate } from 'react-router-dom'
import { Landmark, LogIn, Eye, EyeOff } from 'lucide-react'
import { useAuth } from '../context/AuthContext'
import { useForm } from '../hooks/useForm'
import { reglas } from '../lib/validators'
import { Campo } from '../components/ui'
import PanelAccesibilidad from '../components/layout/PanelAccesibilidad'
import PiePagina from '../components/layout/PiePagina'
import { mensajeDeError } from '../api/client'

const esquema = { correo: [reglas.requerido('Ingrese su correo institucional.'), reglas.correo()], password: [reglas.requerido('Ingrese su contraseña.')] }

export default function Login() {
  const { login } = useAuth()
  const nav = useNavigate()
  const { state } = useLocation()
  const f = useForm({ correo: '', password: '' }, esquema)
  const [ver, setVer] = useState(false)
  const [errorGlobal, setErrorGlobal] = useState('')

  const enviar = f.enviar(async (v) => {
    setErrorGlobal('')
    try {
      await login(v.correo.trim(), v.password)
      nav(state?.desde || '/panel', { replace: true })
    } catch (e) {
      setErrorGlobal(mensajeDeError(e))
      throw e
    }
  })

  return (
    <div className="flex min-h-screen flex-col" style={{ background: 'linear-gradient(135deg, var(--c-side), var(--c-primary))' }}>
      <div className="flex justify-end p-3"><PanelAccesibilidad /></div>
      <main id="contenido" className="grid flex-1 place-items-center p-4">
        <form onSubmit={enviar} noValidate className="card w-full max-w-md !p-7 shadow-2xl" aria-labelledby="titulo-login">
          <Landmark size={40} className="text-primary" aria-hidden="true" />
          <h1 id="titulo-login" className="mt-1 text-2xl font-bold">Consejo Facultativo</h1>
          <p className="suave mb-5 text-sm">Facultad de Ingeniería en Ciencias de la Computación y Telecomunicaciones · UAGRM</p>
          {errorGlobal && <p className="aviso aviso-warn mb-3" role="alert">{errorGlobal}</p>}
          <Campo etiqueta="Correo institucional" tipo="email" autoComplete="username" requerido {...f.campo('correo')} className="mb-3" />
          <div className="mb-4">
            <Campo etiqueta="Contraseña" tipo={ver ? 'text' : 'password'} autoComplete="current-password" requerido {...f.campo('password')} />
            <button type="button" className="suave peq mt-1 inline-flex items-center gap-1 underline" onClick={() => setVer((v) => !v)}>
              {ver ? <EyeOff size={14} aria-hidden="true" /> : <Eye size={14} aria-hidden="true" />}{ver ? 'Ocultar contraseña' : 'Mostrar contraseña'}
            </button>
          </div>
          <button className="btn w-full" disabled={f.enviando}><LogIn size={16} aria-hidden="true" />{f.enviando ? 'Ingresando…' : 'Ingresar'}</button>
          <p className="suave peq mt-4">Si es su primer ingreso, la contraseña inicial es su CI. <Link to="/publico" className="underline">Consultar información pública</Link></p>
        </form>
      </main>
      <div className="bg-surface"><PiePagina /></div>
    </div>
  )
}
