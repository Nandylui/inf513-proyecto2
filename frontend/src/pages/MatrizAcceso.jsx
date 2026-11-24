import { Fragment, useEffect, useMemo, useState } from 'react'
import { KeyRound, Lock } from 'lucide-react'
import api from '../api/client'
import { useToast } from '../context/ToastContext'
import { Campo, Titulo } from '../components/ui'
import { rolLegible } from '../lib/format'

/** Matriz de control de acceso (RBAC): qué rol puede ejecutar qué acción. Los cambios se guardan en la base de datos y rigen de inmediato en la API y en el menú. */
export default function MatrizAcceso() {
  const toast = useToast()
  const [datos, setDatos] = useState(null)
  const [q, setQ] = useState('')
  const [guardando, setGuardando] = useState('')

  const cargar = () => api.get('rbac/matriz').then(setDatos)
  useEffect(() => { cargar() }, [])

  const permisos = useMemo(() => {
    if (!datos) return []
    const f = q.toLowerCase()
    return datos.permisos.filter((p) => !f || `${p.codigo}${p.descripcion}${p.modulo}`.toLowerCase().includes(f))
  }, [datos, q])
  const modulos = useMemo(() => [...new Set(permisos.map((p) => p.modulo))], [permisos])

  const alternar = async (p, rol, concedido) => {
    const clave = `${p.codigo}|${rol}`
    setGuardando(clave)
    try {
      await api.put('rbac/matriz', { rol, permiso: p.codigo, concedido })
      setDatos((d) => ({ ...d, permisos: d.permisos.map((x) => (x.codigo === p.codigo ? { ...x, roles: concedido ? [...x.roles, rol] : x.roles.filter((r) => r !== rol) } : x)) }))
      toast.ok(`${concedido ? 'Concedido' : 'Revocado'}: ${p.codigo} → ${rolLegible(rol)}.`)
    } finally { setGuardando('') }
  }

  if (!datos) return <p className="suave p-4" role="status">Cargando…</p>
  return (
    <div>
      <Titulo icono={KeyRound} subtitulo="Defina qué acciones y pantallas puede usar cada perfil. Los cambios se guardan en la base de datos y se aplican en la API y en el menú dinámico.">Matriz de acceso por perfil</Titulo>
      <div className="card">
        <div className="mb-3 max-w-md"><Campo etiqueta="Filtrar permisos" placeholder="Código, módulo o descripción…" valor={q} onChange={setQ} /></div>
        <div className="tabla-wrap" style={{ maxHeight: '70vh' }}>
          <table className="tabla">
            <caption className="sr-only">Matriz de permisos por rol</caption>
            <thead><tr><th scope="col">Permiso</th>{datos.roles.map((r) => <th key={r.id_rol} scope="col" className="text-center" title={r.descripcion}>{rolLegible(r.nombre)}</th>)}</tr></thead>
            <tbody>
              {modulos.map((m) => (
                <Fragment key={m}>
                  <tr><th colSpan={datos.roles.length + 1} scope="colgroup" className="!bg-primary-soft !text-primary">{m}</th></tr>
                  {permisos.filter((p) => p.modulo === m).map((p) => (
                    <tr key={p.codigo}>
                      <td><span className="mono">{p.codigo}</span><br /><span className="suave peq">{p.descripcion}</span></td>
                      {datos.roles.map((r) => {
                        const activo = p.roles.includes(r.nombre)
                        const bloqueado = r.nombre === 'ADMINISTRADOR' && datos.intocables_admin.includes(p.codigo)
                        return (
                          <td key={r.id_rol} className="text-center">
                            {bloqueado ? <span title="Permiso protegido: el administrador no puede perderlo"><Lock size={16} className="mx-auto text-muted" aria-label="Protegido" /></span> : (
                              <input type="checkbox" className="h-5 w-5 cursor-pointer" checked={activo} disabled={guardando === `${p.codigo}|${r.nombre}`}
                                aria-label={`${p.codigo} para ${rolLegible(r.nombre)}`} onChange={(e) => alternar(p, r.nombre, e.target.checked)} />
                            )}
                          </td>
                        )
                      })}
                    </tr>
                  ))}
                </Fragment>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  )
}
