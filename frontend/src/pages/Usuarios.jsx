import { useCallback, useEffect, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { Plus, Search, UserCog } from 'lucide-react'
import api from '../api/client'
import { useAuth } from '../context/AuthContext'
import { useToast } from '../context/ToastContext'
import { useForm } from '../hooks/useForm'
import { reglas } from '../lib/validators'
import { Campo, Confirmar, Insignia, Modal, Tabla, Titulo } from '../components/ui'

const esquema = {
  ci: [reglas.requerido(), reglas.ci()],
  nombres: [reglas.requerido(), reglas.soloLetras(), reglas.maximo(80)],
  apellidos: [reglas.requerido(), reglas.soloLetras(), reglas.maximo(80)],
  correo: [reglas.requerido(), reglas.correo()],
  telefono: [reglas.telefono()],
  password: [reglas.claveSegura('La contraseña inicial debe tener al menos 8 caracteres (o déjela vacía para usar el CI).')],
}

export default function Usuarios() {
  const { puede } = useAuth()
  const toast = useToast()
  const [params] = useSearchParams()
  const [q, setQ] = useState(params.get('q') || '')
  const [datos, setDatos] = useState([])
  const [roles, setRoles] = useState([])
  const [modal, setModal] = useState(null) // null | { edicion, original }
  const [confirmar, setConfirmar] = useState(null)
  const f = useForm({ ci: '', nombres: '', apellidos: '', correo: '', telefono: '', rol: 'SECRETARIO', password: '' }, esquema)
  const gestionar = puede('usuarios.gestionar')

  const cargar = useCallback((texto = q) => api.get('usuarios', { q: texto }).then(setDatos), [q])
  useEffect(() => { cargar(params.get('q') || ''); api.get('roles').then(setRoles) }, []) // eslint-disable-line react-hooks/exhaustive-deps

  const abrirNuevo = () => { f.reiniciar({ ci: '', nombres: '', apellidos: '', correo: '', telefono: '', rol: 'SECRETARIO', password: '' }); setModal({ edicion: false }) }
  const editar = (u) => {
    f.reiniciar({ ci: u.ci, nombres: u.nombres, apellidos: u.apellidos, correo: u.correo, telefono: u.telefono ?? '', rol: u.rol?.nombre, password: '' })
    setModal({ edicion: true, original: u })
  }

  const guardar = f.enviar(async (v) => {
    if (!modal.edicion) {
      await api.post('usuarios', { ...v, password: v.password || undefined, telefono: v.telefono || undefined })
      toast.ok('Usuario registrado.')
    } else {
      const o = modal.original
      const cambios = ['nombres', 'apellidos', 'correo', 'telefono', 'rol'].filter((c) => (v[c] ?? '') !== (c === 'rol' ? o.rol?.nombre : (o[c] ?? '')) && (v[c] ?? '') !== '')
      for (const c of cambios) await api.patch(`usuarios/${v.ci}`, { campo: c, valor: v[c] })
      if (cambios.length) toast.ok('Usuario actualizado.')
    }
    setModal(null)
    cargar()
  })

  const desactivar = async (u) => { await api.delete(`usuarios/${u.ci}`); toast.ok('Usuario desactivado.'); setConfirmar(null); cargar() }
  const activar = async (u) => { await api.patch(`usuarios/${u.ci}`, { campo: 'estado', valor: 'ACTIVO' }); toast.ok('Usuario activado.'); cargar() }

  return (
    <div>
      <Titulo icono={UserCog} subtitulo="Alta, modificación y desactivación de usuarios; cada rol limita las funciones disponibles (RF-01)."
        acciones={gestionar && <button className="btn" onClick={abrirNuevo}><Plus size={16} aria-hidden="true" />Nuevo usuario</button>}>Usuarios y roles</Titulo>
      <div className="card">
        <form className="mb-3 flex max-w-xl gap-2" onSubmit={(e) => { e.preventDefault(); cargar() }} role="search" aria-label="Buscar usuarios">
          <label htmlFor="q-usuarios" className="sr-only">Buscar usuarios</label>
          <input id="q-usuarios" className="input" placeholder="Buscar por CI, nombre, correo o rol…" value={q} onChange={(e) => setQ(e.target.value)} />
          <button className="btn btn-sec"><Search size={16} aria-hidden="true" />Buscar</button>
        </form>
        <Tabla clave="id_usuario" filas={datos} vacio="Sin resultados."
          columnas={[
            { titulo: 'CI', campo: 'ci' },
            { titulo: 'Nombre', celda: (u) => `${u.nombres} ${u.apellidos}` },
            { titulo: 'Correo', campo: 'correo' },
            { titulo: 'Rol', celda: (u) => <span className="badge">{u.rol?.nombre}</span> },
            { titulo: 'Teléfono', celda: (u) => u.telefono ?? '-' },
            { titulo: 'Estado', celda: (u) => <Insignia estado={u.estado} /> },
            { titulo: 'Acciones', celda: (u) => gestionar && (
              <div className="flex gap-1">
                <button className="btn btn-sec btn-mini" onClick={() => editar(u)}>Editar</button>
                {u.estado === 'ACTIVO' ? <button className="btn btn-peligro btn-mini" onClick={() => setConfirmar(u)}>Desactivar</button> : <button className="btn btn-mini" onClick={() => activar(u)}>Activar</button>}
              </div>) },
          ]} />
      </div>

      {modal && (
        <Modal titulo={modal.edicion ? `Editar usuario ${f.valores.ci}` : 'Nuevo usuario'} onCerrar={() => setModal(null)}>
          <form onSubmit={guardar} noValidate>
            <div className="grid gap-3 sm:grid-cols-2">
              <Campo etiqueta="CI" requerido disabled={modal.edicion} {...f.campo('ci')} />
              <Campo etiqueta="Rol" opciones={roles.map((r) => r.nombre)} {...f.campo('rol')} />
              <Campo etiqueta="Nombres" requerido {...f.campo('nombres')} />
              <Campo etiqueta="Apellidos" requerido {...f.campo('apellidos')} />
              <Campo etiqueta="Correo" tipo="email" requerido {...f.campo('correo')} />
              <Campo etiqueta="Teléfono" {...f.campo('telefono')} />
              {!modal.edicion && <Campo etiqueta="Contraseña inicial" tipo="password" ayuda="Si la deja vacía, la contraseña inicial es el CI." autoComplete="new-password" {...f.campo('password')} />}
            </div>
            <div className="mt-4 flex justify-end gap-2">
              <button type="button" className="btn btn-sec" onClick={() => setModal(null)}>Cancelar</button>
              <button className="btn" disabled={f.enviando}>Guardar</button>
            </div>
          </form>
        </Modal>
      )}
      {confirmar && <Confirmar peligro textoSi="Desactivar" mensaje={`¿Desactivar a ${confirmar.nombres} ${confirmar.apellidos}? No podrá ingresar al sistema.`} onNo={() => setConfirmar(null)} onSi={() => desactivar(confirmar)} />}
    </div>
  )
}
