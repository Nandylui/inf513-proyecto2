import { useEffect, useState } from 'react'
import { Eye, Globe, Send } from 'lucide-react'
import { Link } from 'react-router-dom'
import api from '../api/client'
import { useAuth } from '../context/AuthContext'
import { useToast } from '../context/ToastContext'
import { useForm } from '../hooks/useForm'
import { reglas } from '../lib/validators'
import { Campo, Tabla, Tabs, Titulo, Vacio } from '../components/ui'
import { detalleJson, fechaHora } from '../lib/format'

const esquema = {
  destino: [reglas.requerido('Indique el CI del destinatario o * para todo el Consejo.'), reglas.patron(/^(\*|[0-9A-Za-z-]{4,20})$/, 'Use * o un CI válido (4 a 20 caracteres).')],
  asunto: [reglas.requerido(), reglas.maximo(120)],
  mensaje: [reglas.requerido(), reglas.minimo(5), reglas.maximo(1000)],
}

export default function Fiscalizacion() {
  const { puede } = useAuth()
  const toast = useToast()
  const [tab, setTab] = useState('not')
  const [notis, setNotis] = useState([])
  const [soloNuevas, setSoloNuevas] = useState(false)
  const [auditoria, setAuditoria] = useState([])
  const [fa, setFa] = useState({ ci: '', entidad: '', accion: '', canal: '' })
  const f = useForm({ destino: '*', asunto: '', mensaje: '' }, esquema)

  const cargarNot = (solo = soloNuevas) => api.get('notificaciones', { no_leidas: solo ? 1 : 0 }).then(setNotis)
  const auditar = (filtros = fa) => api.get('auditoria', filtros).then(setAuditoria)
  useEffect(() => { cargarNot(false) }, []) // eslint-disable-line react-hooks/exhaustive-deps

  const leer = async (n) => { await api.post(`notificaciones/${n.id_notificacion}/leer`); cargarNot() }
  const enviar = f.enviar(async (v) => {
    const r = await api.post('notificaciones', v)
    toast.ok(`Aviso enviado a ${r.enviadas} destinatario(s).`); f.reiniciar()
  })

  return (
    <div>
      <Titulo icono={Eye} subtitulo="Auditoría de negocio (WEB y CORREO), notificaciones del Consejo y consulta pública de sesiones y Gaceta (Art. 56 y 57)."
        acciones={<Link to="/publico" target="_blank" className="btn btn-sec"><Globe size={16} aria-hidden="true" />Abrir portal público</Link>}>Fiscalización, auditoría y publicidad</Titulo>
      <Tabs valor={tab} onCambio={(t) => { setTab(t); if (t === 'aud') auditar() }} opciones={[{ id: 'not', texto: 'Mis notificaciones' }, ...(puede('auditoria.ver') ? [{ id: 'aud', texto: 'Auditoría' }] : [])]} />

      {tab === 'not' && (
        <div className="grid items-start gap-4 lg:grid-cols-[3fr_2fr]">
          <div className="card">
            <div className="mb-3 flex items-center justify-between"><h2 className="font-bold">Bandeja</h2>
              <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={soloNuevas} onChange={(e) => { setSoloNuevas(e.target.checked); cargarNot(e.target.checked) }} /> solo no leídas</label></div>
            {notis.length === 0 && <Vacio>Sin notificaciones.</Vacio>}
            {notis.map((n) => (
              <div key={n.id_notificacion} className={`linea ${n.leida ? 'opacity-70' : ''}`}>
                <div className="flex items-center gap-2"><b className="flex-1">{n.asunto}</b><span className="badge">{n.tipo}</span>{!n.leida && <button className="btn btn-sec btn-mini" onClick={() => leer(n)}>Marcar leída</button>}</div>
                <div className="peq suave">{fechaHora(n.fecha)}</div><div className="whitespace-pre-line">{n.mensaje}</div>
              </div>
            ))}
          </div>
          {puede('notificacion.enviar') && (
            <form className="card" onSubmit={enviar} noValidate>
              <h2 className="mb-2 font-bold">Enviar aviso</h2>
              <div className="grid gap-3">
                <Campo etiqueta="Destino (CI o * para todo el Consejo)" requerido {...f.campo('destino')} />
                <Campo etiqueta="Asunto" requerido {...f.campo('asunto')} />
                <Campo etiqueta="Mensaje" area requerido {...f.campo('mensaje')} />
              </div>
              <div className="mt-3 flex justify-end"><button className="btn" disabled={f.enviando}><Send size={16} aria-hidden="true" />Enviar</button></div>
            </form>
          )}
        </div>
      )}

      {tab === 'aud' && (
        <div className="card">
          <form className="mb-3 flex flex-wrap items-end gap-3" onSubmit={(e) => { e.preventDefault(); auditar() }}>
            <div className="w-40"><Campo etiqueta="CI del usuario" valor={fa.ci} onChange={(v) => setFa({ ...fa, ci: v })} /></div>
            <div className="w-44"><Campo etiqueta="Entidad" placeholder="sesion, documento…" valor={fa.entidad} onChange={(v) => setFa({ ...fa, entidad: v })} /></div>
            <div className="w-44"><Campo etiqueta="Acción contiene" placeholder="VOTO, LOGIN…" valor={fa.accion} onChange={(v) => setFa({ ...fa, accion: v })} /></div>
            <div className="w-36"><Campo etiqueta="Canal" valor={fa.canal} onChange={(v) => setFa({ ...fa, canal: v })} opciones={[{ valor: '', texto: 'Todos' }, 'WEB', 'CORREO']} /></div>
            <button className="btn">Filtrar</button>
          </form>
          <Tabla clave="id_auditoria" filas={auditoria} vacio="Sin eventos."
            columnas={[
              { titulo: 'Fecha', celda: (a) => <span className="peq">{String(a.fecha).replace('T', ' ').slice(0, 19)}</span> },
              { titulo: 'Usuario', celda: (a) => `${a.usuario?.nombres ?? 'sistema'} ${a.usuario?.apellidos ?? ''}` },
              { titulo: 'Acción', celda: (a) => <b>{a.accion}</b> },
              { titulo: 'Entidad', celda: (a) => (a.entidad ? `${a.entidad}#${a.id_entidad}` : '-') },
              { titulo: 'Detalle', celda: (a) => <span className="mono peq">{detalleJson(a.detalle)}</span> },
              { titulo: 'Canal', celda: (a) => <span className={`badge ${a.canal === 'CORREO' ? 'b-warn' : ''}`}>{a.canal}</span> },
            ]} />
        </div>
      )}
    </div>
  )
}
