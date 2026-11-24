import { useCallback, useEffect, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { Download, FileText, Plus, Search, Upload } from 'lucide-react'
import api from '../api/client'
import { useAuth } from '../context/AuthContext'
import { useToast } from '../context/ToastContext'
import { useForm } from '../hooks/useForm'
import { reglas } from '../lib/validators'
import { Campo, Insignia, Modal, Tabla, Tabs, Titulo } from '../components/ui'
import { fechaHora } from '../lib/format'

const ESTADOS = ['RECIBIDO', 'EN_REVISION', 'OBSERVADO', 'APROBADO', 'RECHAZADO', 'VOTADO']
const esquema = {
  titulo: [reglas.requerido(), reglas.minimo(5), reglas.maximo(200)],
  id_sesion: [reglas.requerido('Seleccione la sesión.')],
  prioridad: [reglas.requerido(), reglas.rango(1, 5, 'La prioridad debe estar entre 1 y 5.')],
  punto: [reglas.entero(), reglas.mayorQue(0, 'El punto debe ser un número positivo.')],
  descripcion: [reglas.maximo(1000)],
}

export default function Documentos() {
  const { puede, usuario } = useAuth()
  const toast = useToast()
  const [params] = useSearchParams()
  const [tab, setTab] = useState('docs')
  const [docs, setDocs] = useState([])
  const [tipos, setTipos] = useState([])
  const [sesiones, setSesiones] = useState([])
  const [det, setDet] = useState(null)
  const [nuevo, setNuevo] = useState(false)
  const [filtro, setFiltro] = useState({ sesion: '', estado: '', q: '' })
  const [archivo, setArchivo] = useState(null)
  const [archivoVersion, setArchivoVersion] = useState(null)
  const [val, setVal] = useState({ decision: 'APROBADO', observaciones: '' })
  const [edTipo, setEdTipo] = useState(null)
  const f = useForm({ titulo: '', id_sesion: '', tipo: 'GENERAL', prioridad: 3, punto: '', descripcion: '' }, esquema)
  const programadas = sesiones.filter((s) => s.estado === 'PROGRAMADA')

  const cargar = useCallback((fl = filtro) => api.get('documentos', fl).then(setDocs), [filtro])
  const cargarTipos = useCallback(() => api.get('tramites').then(setTipos), [])
  const abrir = useCallback((id) => {
    setVal({ decision: 'APROBADO', observaciones: '' })
    return api.get(`documentos/${id}/trazabilidad`).then(setDet)
  }, [])

  useEffect(() => {
    cargar(); cargarTipos()
    if (puede('sesion.ver')) api.get('sesiones').then(setSesiones)
    const id = params.get('id'); if (id) abrir(id)
  }, []) // eslint-disable-line react-hooks/exhaustive-deps

  const registrar = f.enviar(async (v) => {
    const fd = new FormData()
    for (const [k, x] of Object.entries(v)) if (x !== undefined && x !== null && x !== '') fd.append(k, String(x))
    if (archivo) fd.append('archivo', archivo)
    await api.post('documentos', fd)
    toast.ok('Documento registrado y enviado al flujo de validación.')
    setNuevo(false); setArchivo(null); f.reiniciar(); cargar()
  })

  const elegirArchivo = (a, max, set) => {
    if (a && a.size > max * 1024 * 1024) { toast.error(`El archivo supera los ${max} MB permitidos.`); return }
    set(a)
  }

  const puedeValidar = (d) => {
    const actual = d.pasos.find((p) => ['ACTUAL', 'OBSERVADO', 'RECHAZADO'].includes(p.estado) || (p.estado === 'PENDIENTE' && d.pasos.every((x) => x.orden >= p.orden || x.estado === 'COMPLETADO')))
    return ['RECIBIDO', 'EN_REVISION'].includes(d.documento.estado) && !!actual && actual.rol === usuario?.rol
  }
  const puedeVersion = (d) => ['RECIBIDO', 'EN_REVISION', 'OBSERVADO'].includes(d.documento.estado) && puede('documento.registrar')
  const obsObligatoria = val.decision !== 'APROBADO'

  const validar = async () => {
    if (obsObligatoria && !val.observaciones.trim()) { toast.error('Las observaciones son obligatorias si no aprueba el documento.'); return }
    await api.post(`documentos/${det.documento.id_documento}/validar`, val)
    toast.ok('Validación registrada.'); abrir(det.documento.id_documento); cargar()
  }
  const subirVersion = async () => {
    if (!archivoVersion) { toast.error('Seleccione el archivo de la nueva versión.'); return }
    const fd = new FormData(); fd.append('comentario', 'Nueva versión'); fd.append('archivo', archivoVersion)
    await api.post(`documentos/${det.documento.id_documento}/versiones`, fd)
    toast.ok('Versión registrada.'); setArchivoVersion(null); abrir(det.documento.id_documento); cargar()
  }
  const guardarTipo = async (e) => {
    e.preventDefault()
    const roles = edTipo.rolesTxt.split(',').map((r) => r.trim().toUpperCase()).filter(Boolean)
    if (!roles.length) { toast.error('Indique al menos un rol en el flujo.'); return }
    await api.put(`tramites/${edTipo.id_tipo_tramite}`, { roles, mayoria: edTipo.mayoria })
    toast.ok('Trámite configurado.'); setEdTipo(null); cargarTipos()
  }

  return (
    <div>
      <Titulo icono={FileText} subtitulo="Documentos con 48 h de anticipación, versiones, prioridad por punto del orden del día y workflow por tipo de trámite."
        acciones={puede('documento.registrar') && <button className="btn" onClick={() => setNuevo((n) => !n)}><Plus size={16} aria-hidden="true" />{nuevo ? 'Cerrar' : 'Registrar documento'}</button>}>Repositorio documental y flujo de validación</Titulo>
      <Tabs valor={tab} onCambio={setTab} opciones={[{ id: 'docs', texto: 'Documentos' }, { id: 'tipos', texto: 'Tipos de trámite y flujos' }]} />

      {tab === 'docs' && (
        <>
          {nuevo && (
            <form className="card mb-4" onSubmit={registrar} noValidate>
              <h2 className="mb-2 font-bold">Registrar documento</h2>
              <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <Campo className="sm:col-span-2" etiqueta="Título" requerido {...f.campo('titulo')} />
                <Campo etiqueta="Sesión" requerido opciones={[{ valor: '', texto: '— Seleccione —' }, ...programadas.map((s) => ({ valor: String(s.id_sesion), texto: `#${s.id_sesion} · ${fechaHora(s.fecha_hora)}` }))]} {...f.campo('id_sesion')} />
                <Campo etiqueta="Tipo de trámite" opciones={tipos.map((t) => ({ valor: t.nombre, texto: `${t.nombre} (${t.mayoria})` }))} {...f.campo('tipo')} />
                <Campo etiqueta="Prioridad (1 alta – 5 baja)" tipo="number" min="1" max="5" {...f.campo('prioridad')} />
                <Campo etiqueta="N° de punto del orden del día" tipo="number" min="1" {...f.campo('punto')} />
                <Campo className="sm:col-span-2" etiqueta="Archivo (PDF, Word o imagen · máx. 10 MB)" tipo="file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" valor="" onChange={(a) => elegirArchivo(a, 10, setArchivo)} />
              </div>
              <Campo className="mt-3" etiqueta="Descripción" area {...f.campo('descripcion')} />
              <div className="mt-3 flex justify-end"><button className="btn" disabled={f.enviando}>Registrar</button></div>
            </form>
          )}
          <div className="card">
            <form className="mb-3 flex flex-wrap items-end gap-2" onSubmit={(e) => { e.preventDefault(); cargar() }}>
              <div className="w-52"><Campo etiqueta="Sesión" valor={filtro.sesion} onChange={(v) => { const n = { ...filtro, sesion: v }; setFiltro(n); cargar(n) }} opciones={[{ valor: '', texto: 'Todas las sesiones' }, ...sesiones.map((s) => ({ valor: String(s.id_sesion), texto: `Sesión #${s.id_sesion}` }))]} /></div>
              <div className="w-48"><Campo etiqueta="Estado" valor={filtro.estado} onChange={(v) => { const n = { ...filtro, estado: v }; setFiltro(n); cargar(n) }} opciones={[{ valor: '', texto: 'Todos los estados' }, ...ESTADOS]} /></div>
              <div className="w-60"><Campo etiqueta="Título" valor={filtro.q} placeholder="Buscar por título…" onChange={(v) => setFiltro({ ...filtro, q: v })} /></div>
              <button className="btn btn-sec"><Search size={16} aria-hidden="true" />Buscar</button>
            </form>
            <Tabla clave="id_documento" filas={docs} vacio="Sin documentos."
              columnas={[
                { titulo: '#', campo: 'id_documento' },
                { titulo: 'Título', celda: (d) => <>{d.titulo}<br /><span className="suave peq">{d.remitente?.nombres} {d.remitente?.apellidos}</span></> },
                { titulo: 'Trámite', celda: (d) => d.tipo_tramite?.nombre ?? d.tipoTramite?.nombre },
                { titulo: 'Prior.', campo: 'prioridad' }, { titulo: 'Sesión', celda: (d) => `#${d.id_sesion}` },
                { titulo: 'Estado', celda: (d) => <Insignia estado={d.estado} /> }, { titulo: 'Ver.', celda: (d) => `v${d.version_actual}` },
                { titulo: 'Acciones', celda: (d) => <button className="btn btn-sec btn-mini" onClick={() => abrir(d.id_documento)}>Flujo</button> },
              ]} />
          </div>
        </>
      )}

      {tab === 'tipos' && (
        <>
          <div className="card">
            <Tabla clave="id_tipo_tramite" filas={tipos} vacio="Sin tipos de trámite."
              columnas={[
                { titulo: 'Trámite', celda: (t) => <b>{t.nombre}</b> }, { titulo: 'Base', celda: (t) => t.articulo ?? '-' },
                { titulo: 'Mayoría', celda: (t) => <span className={`badge ${t.mayoria === 'DOS_TERCIOS' ? 'b-warn' : ''}`}>{t.mayoria}</span> },
                { titulo: 'Flujo de validación (en orden)', celda: (t) => <div className="flex flex-wrap gap-1">{t.pasos?.map((p) => <span key={p.id_flujo_paso} className="badge">{p.orden}. {p.rol?.nombre}</span>)}</div> },
                { titulo: 'Acciones', celda: (t) => puede('tramite.configurar') && <button className="btn btn-sec btn-mini" onClick={() => setEdTipo({ ...t, rolesTxt: t.pasos.map((p) => p.rol?.nombre).join(', ') })}>Configurar</button> },
              ]} />
          </div>
          {edTipo && (
            <form className="card mt-3" onSubmit={guardarTipo}>
              <h2 className="mb-2 font-bold">Configurar «{edTipo.nombre}»</h2>
              <div className="grid gap-3 sm:grid-cols-3">
                <Campo className="sm:col-span-2" etiqueta="Roles del flujo, en orden (separados por coma)" valor={edTipo.rolesTxt} onChange={(v) => setEdTipo({ ...edTipo, rolesTxt: v })} />
                <Campo etiqueta="Mayoría requerida" valor={edTipo.mayoria} onChange={(v) => setEdTipo({ ...edTipo, mayoria: v })} opciones={['ABSOLUTA', 'DOS_TERCIOS']} />
              </div>
              <div className="mt-3 flex justify-end gap-2"><button type="button" className="btn btn-sec" onClick={() => setEdTipo(null)}>Cancelar</button><button className="btn">Guardar</button></div>
            </form>
          )}
        </>
      )}

      {det && (
        <Modal titulo={`#${det.documento.id_documento} · ${det.documento.titulo}`} onCerrar={() => setDet(null)} ancho="max-w-2xl">
          <p className="suave mb-2 text-sm">Trámite {det.documento.tipo} · mayoría {det.documento.mayoria} · versión {det.documento.version_actual} · <Insignia estado={det.documento.estado} /></p>
          <h3 className="font-bold">Recorrido del flujo</h3>
          {det.pasos.map((p) => (
            <div key={p.orden} className="linea"><b>Paso {p.orden}: {p.rol}</b> <Insignia estado={p.estado} />
              {p.validado_por && <><br /><span className="suave peq">{p.validado_por} · {p.fecha}</span></>}
              {p.observaciones && <><br /><span className="peq">“{p.observaciones}”</span></>}</div>
          ))}
          {puedeValidar(det) && (
            <div className="card-plana mt-3">
              <h3 className="mb-2 font-bold">Validar en el paso actual</h3>
              <div className="grid gap-3 sm:grid-cols-3">
                <Campo etiqueta="Decisión" valor={val.decision} onChange={(v) => setVal({ ...val, decision: v })} opciones={['APROBADO', 'OBSERVADO', 'RECHAZADO']} />
                <Campo className="sm:col-span-2" etiqueta={`Observaciones${obsObligatoria ? ' (obligatorias)' : ''}`} valor={val.observaciones} onChange={(v) => setVal({ ...val, observaciones: v })} />
              </div>
              <div className="mt-2 flex justify-end"><button className="btn" onClick={validar}>Registrar validación</button></div>
            </div>
          )}
          <div className="mt-4 flex flex-wrap items-end justify-end gap-2">
            {det.documento.version_actual > 0 && <button className="btn btn-sec" onClick={() => api.descargar(`documentos/${det.documento.id_documento}/descargar`, `documento_${det.documento.id_documento}`)}><Download size={16} aria-hidden="true" />Descargar última versión</button>}
            {puedeVersion(det) && (
              <>
                <div className="w-60"><Campo etiqueta="Nueva versión" tipo="file" valor="" onChange={(a) => elegirArchivo(a, 10, setArchivoVersion)} /></div>
                <button className="btn btn-sec" onClick={subirVersion}><Upload size={16} aria-hidden="true" />Subir</button>
              </>
            )}
            <button className="btn" onClick={() => setDet(null)}>Cerrar</button>
          </div>
        </Modal>
      )}
    </div>
  )
}
