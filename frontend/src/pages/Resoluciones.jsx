import { useEffect, useState } from 'react'
import { Gavel, Info } from 'lucide-react'
import api from '../api/client'
import { useAuth } from '../context/AuthContext'
import { useToast } from '../context/ToastContext'
import { useForm } from '../hooks/useForm'
import { reglas } from '../lib/validators'
import { Campo, Insignia, Modal, Tabla, Tabs, Titulo, Vacio } from '../components/ui'
import { fechaCorta } from '../lib/format'

const ESTADOS = ['APROBADA', 'PROMULGADA', 'VETADA', 'MANTENIDA', 'PUBLICADA', 'RECHAZADA', 'ARCHIVADA']
const esquemas = {
  veto: { fundamento: [reglas.requerido('El veto debe ser fundamentado.'), reglas.minimo(15, 'Fundamente el veto con al menos 15 caracteres.'), reglas.maximo(1000)] },
  mantener: { votos_favor: [reglas.requerido(), reglas.entero(), reglas.rango(0, 999)], presentes: [reglas.requerido(), reglas.entero(), reglas.rango(1, 999)] },
  publicar: { gaceta: [reglas.requerido('Indique la referencia de la Gaceta.'), reglas.maximo(80)] },
}

function FormularioAccion({ m, onCerrar, onHecho }) {
  const toast = useToast()
  const f = useForm({ fundamento: '', votos_favor: '', presentes: '', gaceta: '' }, esquemas[m.tipo])
  const ruta = { veto: 'vetar', mantener: 'mantener', publicar: 'publicar' }[m.tipo]
  const enviar = f.enviar(async (v) => {
    const cuerpo = m.tipo === 'veto' ? { fundamento: v.fundamento } : m.tipo === 'mantener' ? { votos_favor: Number(v.votos_favor), presentes: Number(v.presentes) } : { gaceta: v.gaceta }
    if (m.tipo === 'mantener' && Number(v.votos_favor) > Number(v.presentes)) { f.setErrores({ votos_favor: 'Los votos a favor no pueden superar a los presentes.' }); return }
    const r = await api.post(`resoluciones/${m.r.id_resolucion}/${ruta}`, cuerpo)
    toast.ok(`Resolución ${r.numero}: ${r.estado}`); onHecho()
  })
  const titulo = { veto: `Vetar ${m.r.numero}`, mantener: `Resolver veto de ${m.r.numero}`, publicar: `Publicar ${m.r.numero}` }[m.tipo]
  return (
    <Modal titulo={titulo} onCerrar={onCerrar}>
      <form onSubmit={enviar} noValidate>
        {m.tipo === 'veto' && <><p className="aviso aviso-warn mb-3">El veto debe ser fundamentado y suspende los efectos de la resolución (Art. 64 b).</p><Campo etiqueta="Fundamento del veto" area requerido {...f.campo('fundamento')} /></>}
        {m.tipo === 'mantener' && (
          <>
            <p className="aviso mb-3">Si al menos dos tercios de los presentes ratifican la resolución, se mantiene; si no, queda sin efecto.</p>
            <div className="grid gap-3 sm:grid-cols-2"><Campo etiqueta="Votos a favor de mantenerla" tipo="number" min="0" requerido {...f.campo('votos_favor')} /><Campo etiqueta="Miembros presentes" tipo="number" min="1" requerido {...f.campo('presentes')} /></div>
          </>
        )}
        {m.tipo === 'publicar' && <Campo etiqueta="Referencia de la Gaceta Universitaria" placeholder="Gaceta N° 12/2027" requerido {...f.campo('gaceta')} />}
        <div className="mt-4 flex justify-end gap-2"><button type="button" className="btn btn-sec" onClick={onCerrar}>Cancelar</button><button className="btn" disabled={f.enviando}>Confirmar</button></div>
      </form>
    </Modal>
  )
}

export default function Resoluciones() {
  const { puede } = useAuth()
  const toast = useToast()
  const [tab, setTab] = useState('res')
  const [lista, setLista] = useState([])
  const [filtro, setFiltro] = useState('')
  const [sesiones, setSesiones] = useState([])
  const [sesionActa, setSesionActa] = useState(null)
  const [acta, setActa] = useState(null)
  const [modal, setModal] = useState(null)

  const cargar = (fl = filtro) => api.get('resoluciones', { q: fl }).then(setLista)
  useEffect(() => { cargar('') }, []) // eslint-disable-line react-hooks/exhaustive-deps

  const promulgar = async (r) => { const x = await api.post(`resoluciones/${r.id_resolucion}/promulgar`); toast.ok(x.advertencia ?? `Resolución ${r.numero} promulgada.`); cargar() }
  const verActa = (s) => { setSesionActa(s); setActa(null); api.get(`sesiones/${s.id_sesion}/acta`, null, { silencioso: true }).then(setActa).catch(() => setActa(null)) }
  const generarActa = async (s) => { const a = await api.post(`sesiones/${s.id_sesion}/acta`); setActa(a); toast.ok('Acta generada con sello de integridad.') }

  return (
    <div>
      <Titulo icono={Gavel} subtitulo="El Decano promulga o veta en 5 días hábiles (Art. 64); el veto se supera con dos tercios; las resoluciones se publican en la Gaceta (Art. 57).">Resoluciones, veto y actas</Titulo>
      <Tabs valor={tab} onCambio={(t) => { setTab(t); if (t === 'actas' && !sesiones.length) api.get('sesiones').then(setSesiones) }} opciones={[{ id: 'res', texto: 'Resoluciones' }, { id: 'actas', texto: 'Actas de sesión' }]} />

      {tab === 'res' && (
        <div className="card">
          <div className="mb-3 w-56"><Campo etiqueta="Estado" valor={filtro} onChange={(v) => { setFiltro(v); cargar(v) }} opciones={[{ valor: '', texto: 'Todas' }, ...ESTADOS]} /></div>
          <Tabla clave="id_resolucion" filas={lista} vacio="Sin resoluciones."
            columnas={[
              { titulo: 'Número', celda: (r) => <b>{r.numero}</b> },
              { titulo: 'Asunto', celda: (r) => <>{r.documento?.titulo}<br /><span className="suave peq">Sesión #{r.id_sesion} · {r.mayoria_requerida}</span></> },
              { titulo: 'Votos F/C/B', celda: (r) => `${r.votos_favor}/${r.votos_contra}/${r.votos_blanco}` },
              { titulo: 'Resultado', celda: (r) => <Insignia estado={r.resultado} /> }, { titulo: 'Estado', celda: (r) => <Insignia estado={r.estado} /> },
              { titulo: 'Plazo promulgar', celda: (r) => (r.fecha_limite_promulgacion ? fechaCorta(r.fecha_limite_promulgacion) : '-') },
              { titulo: 'Acciones', celda: (r) => (
                <div className="flex flex-wrap items-center gap-1">
                  {r.estado === 'APROBADA' && puede('resolucion.promulgar') && <button className="btn btn-mini" onClick={() => promulgar(r)}>Promulgar</button>}
                  {r.estado === 'APROBADA' && puede('resolucion.vetar') && <button className="btn btn-peligro btn-mini" onClick={() => setModal({ tipo: 'veto', r })}>Vetar</button>}
                  {r.estado === 'VETADA' && puede('resolucion.mantener') && <button className="btn btn-mini" onClick={() => setModal({ tipo: 'mantener', r })}>Resolver veto</button>}
                  {['PROMULGADA', 'MANTENIDA'].includes(r.estado) && puede('resolucion.publicar') && <button className="btn btn-mini" onClick={() => setModal({ tipo: 'publicar', r })}>Publicar en Gaceta</button>}
                  {r.estado === 'PUBLICADA' && <span className="suave peq">{r.gaceta_referencia}</span>}
                  {r.veto_fundamento && <span className="suave peq inline-flex items-center gap-1" title={r.veto_fundamento}><Info size={13} aria-hidden="true" />veto: {r.veto_fundamento.slice(0, 40)}{r.veto_fundamento.length > 40 ? '…' : ''}</span>}
                </div>) },
            ]} />
        </div>
      )}

      {tab === 'actas' && (
        <div className="grid items-start gap-4 lg:grid-cols-[20rem_1fr]">
          <div className="card"><h2 className="mb-2 font-bold">Sesiones</h2>
            {sesiones.map((s) => (
              <button key={s.id_sesion} className={`linea block w-full text-left ${sesionActa?.id_sesion === s.id_sesion ? 'bg-primary-soft' : ''}`} onClick={() => verActa(s)}>
                <b>#{s.id_sesion}</b> {fechaCorta(s.fecha_hora)} <Insignia estado={s.estado} />
              </button>
            ))}
          </div>
          <div className="card">
            {sesionActa ? (
              <>
                <div className="mb-2 flex items-center justify-between gap-2"><h2 className="font-bold">Acta de la sesión #{sesionActa.id_sesion}</h2>
                  {puede('acta.generar') && <button className="btn" onClick={() => generarActa(sesionActa)}>{acta ? 'Regenerar acta' : 'Generar acta'}</button>}</div>
                {acta ? <><pre className="salida">{acta.contenido}</pre><p className="peq suave mono mt-2">Sello SHA-256: {acta.hash}</p></> : <Vacio>Esta sesión aún no tiene acta.</Vacio>}
              </>
            ) : <Vacio>Seleccione una sesión.</Vacio>}
          </div>
        </div>
      )}
      {modal && <FormularioAccion m={modal} onCerrar={() => setModal(null)} onHecho={() => { setModal(null); cargar() }} />}
    </div>
  )
}
