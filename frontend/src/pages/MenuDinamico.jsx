import { useEffect, useState } from 'react'
import { List, Plus } from 'lucide-react'
import api from '../api/client'
import { useAuth } from '../context/AuthContext'
import { useToast } from '../context/ToastContext'
import { useForm } from '../hooks/useForm'
import { reglas } from '../lib/validators'
import { Campo, Confirmar, Insignia, Modal, Tabla, Titulo } from '../components/ui'
import { ICONOS, iconoDe } from '../components/Iconos'

const vacio = { titulo: '', ruta: '', icono: 'carpeta', orden: 10, id_padre: '', permiso: '', activo: true }
const esquema = {
  titulo: [reglas.requerido(), reglas.maximo(80)],
  ruta: [reglas.patron(/^\/[A-Za-z0-9/_-]*$/, 'La ruta debe comenzar con / y usar letras, números, guion o guion bajo.')],
  orden: [reglas.requerido(), reglas.entero(), reglas.rango(0, 999)],
}

/** Administración del menú dinámico: el árbol de navegación vive en la base de datos y se filtra por permisos del rol. */
export default function MenuDinamico() {
  const toast = useToast()
  const { refrescar } = useAuth()
  const [items, setItems] = useState([])
  const [permisos, setPermisos] = useState([])
  const [modal, setModal] = useState(null)
  const [borrar, setBorrar] = useState(null)
  const f = useForm(vacio, esquema)

  const cargar = () => api.get('rbac/menus').then(setItems)
  useEffect(() => { cargar(); api.get('rbac/matriz').then((m) => setPermisos(m.permisos)) }, [])

  const padres = items.filter((i) => !i.id_padre)
  const nombrePadre = (id) => items.find((i) => i.id_menu === id)?.titulo ?? '—'
  const abrir = (m) => { f.reiniciar(m ? { titulo: m.titulo, ruta: m.ruta ?? '', icono: m.icono ?? 'carpeta', orden: m.orden, id_padre: m.id_padre ? String(m.id_padre) : '', permiso: m.permiso ?? '', activo: m.activo } : vacio); setModal({ id: m?.id_menu }) }

  const guardar = f.enviar(async (v) => {
    const cuerpo = { titulo: v.titulo, ruta: v.ruta || null, icono: v.icono || null, orden: Number(v.orden), id_padre: v.id_padre ? Number(v.id_padre) : null, permiso: v.permiso || null, activo: v.activo }
    if (modal.id) await api.put(`rbac/menus/${modal.id}`, cuerpo); else await api.post('rbac/menus', cuerpo)
    toast.ok('Opción de menú guardada.'); setModal(null); cargar(); refrescar()
  })
  const eliminar = async (m) => { await api.delete(`rbac/menus/${m.id_menu}`); toast.ok('Opción eliminada.'); setBorrar(null); cargar(); refrescar() }

  return (
    <div>
      <Titulo icono={List} subtitulo="Cree, ordene u oculte opciones de la barra de navegación. Cada opción exige un permiso: el usuario solo ve lo que su rol puede usar."
        acciones={<button className="btn" onClick={() => abrir(null)}><Plus size={16} aria-hidden="true" />Nueva opción</button>}>Menú dinámico</Titulo>
      <div className="card">
        <Tabla clave="id_menu" filas={items} vacio="Sin opciones de menú."
          columnas={[
            { titulo: 'Opción', celda: (m) => { const I = iconoDe(m.icono); return <span className={`flex items-center gap-2 ${m.id_padre ? 'ml-6' : 'font-bold'}`}><I size={16} aria-hidden="true" />{m.titulo}</span> } },
            { titulo: 'Ruta', celda: (m) => <span className="mono peq">{m.ruta ?? '(grupo)'}</span> }, { titulo: 'Grupo', celda: (m) => (m.id_padre ? nombrePadre(m.id_padre) : '—') },
            { titulo: 'Permiso requerido', celda: (m) => <span className="mono peq">{m.permiso ?? 'cualquier usuario'}</span> }, { titulo: 'Orden', campo: 'orden' },
            { titulo: 'Estado', celda: (m) => <Insignia estado={m.activo ? 'ACTIVO' : 'INACTIVO'} /> },
            { titulo: 'Acciones', celda: (m) => <div className="flex gap-1"><button className="btn btn-sec btn-mini" onClick={() => abrir(m)}>Editar</button><button className="btn btn-peligro btn-mini" onClick={() => setBorrar(m)}>Eliminar</button></div> },
          ]} />
      </div>
      {modal && (
        <Modal titulo={modal.id ? 'Editar opción de menú' : 'Nueva opción de menú'} onCerrar={() => setModal(null)}>
          <form onSubmit={guardar} noValidate>
            <div className="grid gap-3 sm:grid-cols-2">
              <Campo etiqueta="Título" requerido {...f.campo('titulo')} />
              <Campo etiqueta="Ruta (vacía si es un grupo)" placeholder="/reportes" {...f.campo('ruta')} />
              <Campo etiqueta="Icono" opciones={Object.keys(ICONOS)} {...f.campo('icono')} />
              <Campo etiqueta="Orden" tipo="number" min="0" requerido {...f.campo('orden')} />
              <Campo etiqueta="Grupo padre" opciones={[{ valor: '', texto: '— Nivel superior —' }, ...padres.filter((p) => p.id_menu !== modal.id).map((p) => ({ valor: String(p.id_menu), texto: p.titulo }))]} {...f.campo('id_padre')} />
              <Campo etiqueta="Permiso requerido" opciones={[{ valor: '', texto: '— Cualquier usuario autenticado —' }, ...permisos.map((p) => ({ valor: p.codigo, texto: p.codigo }))]} {...f.campo('permiso')} />
            </div>
            <label className="mt-3 flex items-center gap-2 text-sm"><input type="checkbox" checked={f.valores.activo} onChange={(e) => f.cambiar('activo', e.target.checked)} /> Visible en el menú</label>
            <div className="mt-4 flex justify-end gap-2"><button type="button" className="btn btn-sec" onClick={() => setModal(null)}>Cancelar</button><button className="btn" disabled={f.enviando}>Guardar</button></div>
          </form>
        </Modal>
      )}
      {borrar && <Confirmar peligro textoSi="Eliminar" mensaje={`¿Eliminar «${borrar.titulo}»? Si es un grupo, también se eliminarán sus opciones.`} onNo={() => setBorrar(null)} onSi={() => eliminar(borrar)} />}
    </div>
  )
}
