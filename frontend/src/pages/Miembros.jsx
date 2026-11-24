import { useCallback, useEffect, useState } from 'react'
import { Plus, Users } from 'lucide-react'
import api from '../api/client'
import { useAuth } from '../context/AuthContext'
import { useToast } from '../context/ToastContext'
import { useForm } from '../hooks/useForm'
import { reglas } from '../lib/validators'
import { Campo, Confirmar, Insignia, Tabla, Tabs, Titulo } from '../components/ui'
import { fechaCorta, fechaHora } from '../lib/format'

const inicial = { ci_titular: '', ci_suplente: '', carrera: '', estamento: 'DOCENTE', fecha_inicio: '', fecha_fin: '' }
const esquema = {
  ci_titular: [reglas.requerido('Ingrese el CI del titular.'), reglas.ci()],
  ci_suplente: [reglas.ci()],
  carrera: [reglas.requerido(), reglas.maximo(60)],
  fecha_inicio: [reglas.requerido('Indique la fecha de inicio.')],
  fecha_fin: [reglas.requerido('Indique la fecha de fin.'), reglas.fechaPosterior('fecha_inicio', 'La fecha de fin debe ser posterior al inicio.')],
}

export default function Miembros() {
  const { puede } = useAuth()
  const toast = useToast()
  const [tab, setTab] = useState('panel')
  const [panel, setPanel] = useState([])
  const [sesiones, setSesiones] = useState([])
  const [sesion, setSesion] = useState('')
  const [quorum, setQuorum] = useState(null)
  const [mandatos, setMandatos] = useState([])
  const [filtro, setFiltro] = useState('VIGENTE')
  const [nuevo, setNuevo] = useState(false)
  const [concluir, setConcluir] = useState(null)
  const f = useForm(inicial, esquema)

  const cargarPanel = useCallback((s = sesion) => api.get('panel', { sesion: s }).then(setPanel), [sesion])
  const cargarMandatos = useCallback((fl = filtro) => api.get('mandatos', { q: fl }).then(setMandatos), [filtro])
  useEffect(() => { cargarPanel(''); if (puede('sesion.ver')) api.get('sesiones').then(setSesiones) }, []) // eslint-disable-line react-hooks/exhaustive-deps

  const elegirSesion = (id) => {
    setSesion(id); setQuorum(null); cargarPanel(id)
    if (id) api.get(`sesiones/${id}/quorum`).then(setQuorum)
  }
  const registrar = f.enviar(async (v) => {
    await api.post('mandatos', { ...v, ci_suplente: v.ci_suplente || undefined })
    toast.ok('Mandato registrado.'); setNuevo(false); f.reiniciar(); cargarMandatos(); cargarPanel()
  })
  const concluirMandato = async (m) => { await api.post(`mandatos/${m.id_mandato}/concluir`); toast.ok('Mandato concluido.'); setConcluir(null); cargarMandatos(); cargarPanel() }

  return (
    <div>
      <Titulo icono={Users} subtitulo="Mandatos de 2 años sin reelección, un titular y un suplente por carrera y estamento (Art. 50 y 52); quórum de mayoría absoluta (Art. 53).">Miembros del Consejo y quórum</Titulo>
      <Tabs valor={tab} onCambio={(t) => { setTab(t); if (t === 'mandatos') cargarMandatos() }} opciones={[{ id: 'panel', texto: 'Panel de miembros' }, { id: 'mandatos', texto: 'Mandatos e historial' }]} />

      {tab === 'panel' && (
        <div className="card">
          {puede('sesion.ver') && (
            <div className="mb-3 max-w-lg">
              <Campo etiqueta="Ver habilitados para la sesión" valor={sesion} onChange={elegirSesion}
                opciones={[{ valor: '', texto: '— Sin sesión (solo titulares) —' }, ...sesiones.map((s) => ({ valor: String(s.id_sesion), texto: `#${s.id_sesion} · ${fechaHora(s.fecha_hora)} · ${s.estado}` }))]} />
            </div>
          )}
          {quorum && (
            <div className="mb-3 flex items-center gap-3">
              <div className="flex-1">
                <div className={`barra ${quorum.alcanzado ? 'ok' : ''}`} role="progressbar" aria-valuemin={0} aria-valuemax={quorum.requerido} aria-valuenow={quorum.presentes}><i style={{ width: `${Math.min(100, (quorum.presentes * 100) / (quorum.requerido || 1))}%` }} /></div>
                <span className="peq suave">Presentes {quorum.presentes} de {quorum.total_miembros} · se requieren {quorum.requerido} (mayoría absoluta)</span>
              </div>
              <Insignia estado={quorum.alcanzado ? 'QUÓRUM ALCANZADO' : 'SIN QUÓRUM'} />
            </div>
          )}
          <Tabla filas={panel} vacio="Sin miembros vigentes."
            columnas={[
              { titulo: 'Carrera', campo: 'carrera' }, { titulo: 'Estamento', campo: 'estamento' },
              { titulo: 'Titular', celda: (m) => m.titular?.nombre }, { titulo: 'Suplente', celda: (m) => m.suplente?.nombre ?? '-' },
              { titulo: 'Habilitado', celda: (m) => <b>{m.habilitado?.nombre}</b> }, { titulo: 'Condición', celda: (m) => <Insignia estado={m.condicion} /> },
              ...(sesion ? [{ titulo: 'Presente', celda: (m) => <Insignia estado={m.presente ? 'PRESENTE' : 'AUSENTE'} /> }] : []),
              { titulo: 'Fin de mandato', celda: (m) => m.fecha_fin ?? '-' },
            ]} />
        </div>
      )}

      {tab === 'mandatos' && (
        <div className="card">
          <div className="mb-3 flex flex-wrap items-end justify-between gap-2">
            <div className="w-56"><Campo etiqueta="Estado del mandato" valor={filtro} onChange={(v) => { setFiltro(v); cargarMandatos(v) }} opciones={[{ valor: '', texto: 'Todos' }, 'VIGENTE', 'CONCLUIDO']} /></div>
            {puede('miembros.gestionar') && <button className="btn" onClick={() => setNuevo((n) => !n)}><Plus size={16} aria-hidden="true" />{nuevo ? 'Cerrar formulario' : 'Registrar mandato'}</button>}
          </div>
          {nuevo && (
            <form className="card-plana mb-3" onSubmit={registrar} noValidate>
              <h3 className="mb-2 font-bold">Nuevo mandato</h3>
              <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <Campo etiqueta="CI del titular" requerido {...f.campo('ci_titular')} />
                <Campo etiqueta="CI del suplente (opcional)" {...f.campo('ci_suplente')} />
                <Campo etiqueta="Carrera" requerido placeholder="INFORMATICA" {...f.campo('carrera')} />
                <Campo etiqueta="Estamento" opciones={['DOCENTE', 'ESTUDIANTE']} {...f.campo('estamento')} />
                <Campo etiqueta="Inicio" tipo="date" requerido {...f.campo('fecha_inicio')} />
                <Campo etiqueta="Fin (máx. 2 años)" tipo="date" requerido {...f.campo('fecha_fin')} />
              </div>
              <div className="mt-3 flex justify-end"><button className="btn" disabled={f.enviando}>Registrar</button></div>
            </form>
          )}
          <Tabla clave="id_mandato" filas={mandatos} vacio="Sin mandatos."
            columnas={[
              { titulo: 'ID', campo: 'id_mandato' }, { titulo: 'Carrera', campo: 'carrera' }, { titulo: 'Estamento', campo: 'estamento' },
              { titulo: 'Titular', celda: (m) => `${m.titular?.nombres ?? ''} ${m.titular?.apellidos ?? ''}` },
              { titulo: 'Suplente', celda: (m) => (m.suplente ? `${m.suplente.nombres} ${m.suplente.apellidos}` : '-') },
              { titulo: 'Vigencia', celda: (m) => `${fechaCorta(m.fecha_inicio)} → ${fechaCorta(m.fecha_fin)}` },
              { titulo: 'Estado', celda: (m) => <Insignia estado={m.estado} /> },
              { titulo: 'Acciones', celda: (m) => puede('miembros.gestionar') && m.estado === 'VIGENTE' && <button className="btn btn-sec btn-mini" onClick={() => setConcluir(m)}>Concluir</button> },
            ]} />
        </div>
      )}
      {concluir && <Confirmar titulo="Concluir mandato" mensaje="¿Concluir este mandato? Pasará al historial." onNo={() => setConcluir(null)} onSi={() => concluirMandato(concluir)} />}
    </div>
  )
}
