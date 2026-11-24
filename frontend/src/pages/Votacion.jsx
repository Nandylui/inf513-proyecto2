import { useEffect, useState } from 'react'
import { Vote } from 'lucide-react'
import api from '../api/client'
import { useAuth } from '../context/AuthContext'
import { useToast } from '../context/ToastContext'
import { Campo, Confirmar, Insignia, Tabla, Titulo, Vacio } from '../components/ui'
import { fechaHora } from '../lib/format'

export default function Votacion() {
  const { puede } = useAuth()
  const toast = useToast()
  const [sesiones, setSesiones] = useState([])
  const [sesionId, setSesionId] = useState('')
  const [q, setQ] = useState(null)
  const [asistentes, setAsistentes] = useState([])
  const [habilitados, setHabilitados] = useState([])
  const [docs, setDocs] = useState([])
  const [conteos, setConteos] = useState({})
  const [ci, setCi] = useState('')
  const [metodo, setMetodo] = useState('HUELLA')
  const [cerrar, setCerrar] = useState(null)

  useEffect(() => { api.get('sesiones').then((r) => setSesiones(r.filter((s) => s.estado !== 'CANCELADA'))) }, [])

  const contar = (id, d) => api.get(`sesiones/${id}/documentos/${d.id_documento}/conteo`).then((c) => setConteos((m) => ({ ...m, [d.id_documento]: c })))
  const recargar = (id = sesionId) => {
    api.get(`sesiones/${id}/asistencias`).then((r) => { setAsistentes(r.asistentes); setQ(r.quorum) })
    api.get('panel', { sesion: id }).then(setHabilitados)
    api.get('documentos', { sesion: id }).then((d) => { setDocs(d); d.filter((x) => ['APROBADO', 'VOTADO'].includes(x.estado)).forEach((x) => contar(id, x)) })
  }
  const elegir = (id) => { setSesionId(id); setConteos({}); setQ(null); setAsistentes([]); setDocs([]); if (id) recargar(id) }

  const marcar = async () => {
    const r = await api.post(`sesiones/${sesionId}/asistencias`, { ci, metodo })
    toast.ok(r.quorum.alcanzado ? 'Asistencia registrada. ¡Quórum alcanzado!' : `Asistencia registrada. Faltan ${r.quorum.faltan} para el quórum.`)
    setCi(''); recargar()
  }
  const votar = async (d, voto) => {
    const r = await api.post(`sesiones/${sesionId}/documentos/${d.id_documento}/votos`, { voto })
    toast.ok('Su voto fue registrado.'); setConteos((m) => ({ ...m, [d.id_documento]: r.conteo }))
  }
  const cerrarVotacion = async (d) => {
    const r = await api.post(`sesiones/${sesionId}/documentos/${d.id_documento}/cerrar`)
    toast.ok(`Resolución ${r.numero}: ${r.resultado}`); setCerrar(null); recargar()
  }

  return (
    <div>
      <Titulo icono={Vote} subtitulo="Registro de asistencia (reloj biométrico), verificación de quórum, votación secreta y cierre con resolución numerada.">Asistencia biométrica, quórum y votaciones</Titulo>
      <div className="card mb-4 max-w-xl">
        <Campo etiqueta="Sesión" valor={sesionId} onChange={elegir}
          opciones={[{ valor: '', texto: '— Seleccione —' }, ...sesiones.map((s) => ({ valor: String(s.id_sesion), texto: `#${s.id_sesion} · ${fechaHora(s.fecha_hora)} · ${s.estado}` }))]} />
      </div>

      {sesionId && (
        <div className="grid items-start gap-4 lg:grid-cols-2">
          <section className="card" aria-label="Asistencia y quórum">
            <h2 className="mb-2 font-bold">Asistencia y quórum</h2>
            {q && (
              <>
                <div className={`barra ${q.alcanzado ? 'ok' : ''}`} style={{ height: '1rem' }} role="progressbar" aria-valuemin={0} aria-valuemax={q.requerido} aria-valuenow={q.presentes}><i style={{ width: `${Math.min(100, (q.presentes * 100) / (q.requerido || 1))}%` }} /></div>
                <p className="suave peq mb-3 mt-1">Presentes {q.presentes} de {q.total_miembros} · mayoría absoluta = {q.requerido} <Insignia estado={q.alcanzado ? 'QUÓRUM ALCANZADO' : 'SIN QUÓRUM'} /></p>
              </>
            )}
            {puede('asistencia.registrar') && (
              <div className="mb-3 flex flex-wrap items-end gap-2">
                <div className="min-w-56 flex-1"><Campo etiqueta="Consejero habilitado" valor={ci} onChange={setCi}
                  opciones={[{ valor: '', texto: 'Seleccione…' }, ...habilitados.filter((h) => !h.presente && h.habilitado).map((h) => ({ valor: h.habilitado.ci, texto: `${h.habilitado.nombre} (${h.condicion})` }))]} /></div>
                <div className="w-32"><Campo etiqueta="Método" valor={metodo} onChange={setMetodo} opciones={['HUELLA', 'FACIAL', 'MANUAL']} /></div>
                <button className="btn" onClick={marcar} disabled={!ci}>Registrar asistencia</button>
              </div>
            )}
            <Tabla clave="id_asistencia" filas={asistentes} vacio="Aún no hay asistentes."
              columnas={[
                { titulo: 'Consejero', celda: (a) => `${a.usuario?.nombres ?? ''} ${a.usuario?.apellidos ?? ''}` },
                { titulo: 'Condición', celda: (a) => <Insignia estado={a.es_suplente ? 'SUPLENTE' : 'TITULAR'} /> },
                { titulo: 'Hora', celda: (a) => a.hora_ingreso?.slice(11, 16) }, { titulo: 'Método', campo: 'metodo' },
              ]} />
          </section>

          <section className="card" aria-label="Votación de documentos">
            <h2 className="mb-2 font-bold">Votación de documentos aprobados</h2>
            {docs.length === 0 && <Vacio>No hay documentos de esta sesión.</Vacio>}
            {docs.map((d) => {
              const c = conteos[d.id_documento]
              return (
                <div key={d.id_documento} className="card-plana mb-2">
                  <div className="flex items-center gap-2"><b className="flex-1">#{d.id_documento} · {d.titulo}</b><Insignia estado={d.estado} /></div>
                  {c && (
                    <div className="peq my-2 flex flex-wrap items-center gap-2">
                      <span className="badge b-ok">A favor {c.a_favor}</span><span className="badge b-err">En contra {c.en_contra}</span><span className="badge">Blanco {c.blanco}</span>
                      <span className="suave">de {c.presentes} presentes · {c.mayoria} requiere {c.requerido}</span>
                    </div>
                  )}
                  <div className="flex flex-wrap gap-2">
                    {d.estado === 'APROBADO' && puede('votar') && (
                      <>
                        <button className="btn btn-ok btn-mini" onClick={() => votar(d, 'A FAVOR')}>A favor</button>
                        <button className="btn btn-peligro btn-mini" onClick={() => votar(d, 'EN CONTRA')}>En contra</button>
                        <button className="btn btn-sec btn-mini" onClick={() => votar(d, 'BLANCO')}>Blanco</button>
                      </>
                    )}
                    <button className="btn btn-sec btn-mini" onClick={() => contar(sesionId, d)}>Actualizar conteo</button>
                    {d.estado === 'APROBADO' && puede('votacion.cerrar') && <button className="btn btn-mini" onClick={() => setCerrar(d)}>Cerrar votación</button>}
                  </div>
                </div>
              )
            })}
            <p className="suave peq">El voto es secreto: la auditoría solo registra que el consejero votó, no el sentido de su voto.</p>
          </section>
        </div>
      )}
      {cerrar && <Confirmar titulo="Cerrar votación" mensaje="¿Cerrar la votación? Se generará la resolución con el resultado." onNo={() => setCerrar(null)} onSi={() => cerrarVotacion(cerrar)} />}
    </div>
  )
}
