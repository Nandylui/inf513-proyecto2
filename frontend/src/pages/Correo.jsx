import { useEffect, useMemo, useState } from 'react'
import { Mail, Send } from 'lucide-react'
import api from '../api/client'
import { useAuth } from '../context/AuthContext'
import { Campo, Tabla, Titulo } from '../components/ui'

const TITULOS = { 0: 'Ayuda', 1: 'CU1 · Usuarios y roles', 2: 'CU2 · Miembros y quórum', 3: 'CU3 · Convocatorias, excusas y suplentes', 4: 'CU4 · Documentos y flujo', 5: 'CU5 · Asistencia y votación', 6: 'CU6 · Resoluciones y actas', 7: 'CU7 · Fiscalización y público', 8: 'CU8 · Reportes' }

export default function Correo() {
  const { puede } = useAuth()
  const [catalogo, setCatalogo] = useState([])
  const [asunto, setAsunto] = useState('AYUDA[]')
  const [filtro, setFiltro] = useState('')
  const [resp, setResp] = useState(null)
  const [log, setLog] = useState([])
  const [cargando, setCargando] = useState(false)

  const cargarLog = () => api.get('correo/log', { limite: 30 }).then(setLog).catch(() => {})
  useEffect(() => { api.get('correo/comandos').then(setCatalogo); if (puede('auditoria.ver')) cargarLog() }, []) // eslint-disable-line react-hooks/exhaustive-deps

  const grupos = useMemo(() => {
    const f = filtro.toLowerCase()
    const items = catalogo.filter((c) => !f || `${c.nombre}${c.descripcion}${c.sintaxis}`.toLowerCase().includes(f))
    return Object.keys(TITULOS).map(Number).map((cu) => ({ cu, titulo: TITULOS[cu], items: items.filter((c) => c.cu === cu) })).filter((g) => g.items.length)
  }, [catalogo, filtro])

  const ejecutar = async (e) => {
    e?.preventDefault()
    if (!asunto.trim()) return
    setCargando(true)
    try { setResp(await api.post('correo/simular', { asunto })); if (puede('auditoria.ver')) cargarLog() } finally { setCargando(false) }
  }

  return (
    <div>
      <Titulo icono={Mail} subtitulo={<>Los mismos casos de uso del sistema web también se operan enviando un correo con el comando en el <b>asunto</b>: <span className="mono">COMANDO["param1","param2"]</span></>}>Sistema Vía Mail</Titulo>
      <p className="aviso mb-4">Envíe el correo a <b className="mono">grupo07sa@tecnoweb.org.bo</b> desde su correo registrado. Esta consola ejecuta exactamente el mismo procesador, con su identidad, y le muestra la respuesta que recibiría por correo.</p>
      <div className="grid items-start gap-4 lg:grid-cols-[6fr_5fr]">
        <div className="grid gap-4">
          <form className="card" onSubmit={ejecutar}>
            <h2 className="mb-2 font-bold">Ejecutar comando</h2>
            <Campo etiqueta="Asunto del correo" className="mono" valor={asunto} onChange={setAsunto} placeholder='LISSES["PROGRAMADA"]' maxLength={400} />
            <div className="mt-3 flex justify-end"><button className="btn" disabled={!asunto.trim() || cargando}><Send size={16} aria-hidden="true" />{cargando ? 'Procesando…' : 'Enviar comando'}</button></div>
          </form>
          {resp && (
            <section className="card" aria-live="polite">
              <div className="mb-2 flex items-center justify-between"><h2 className="font-bold">Respuesta</h2><span className={`badge ${resp.exito ? 'b-ok' : 'b-err'}`}>{resp.exito ? 'OK' : 'ERROR'}</span></div>
              <p className="peq suave mono mb-2">Asunto de la respuesta: {resp.asunto}</p><pre className="salida">{resp.respuesta}</pre>
            </section>
          )}
          {puede('auditoria.ver') && (
            <section className="card">
              <div className="mb-2 flex items-center justify-between"><h2 className="font-bold">Bitácora de correos (access/error)</h2><button className="btn btn-sec btn-mini" onClick={cargarLog}>Actualizar</button></div>
              <Tabla clave="id_correo_log" filas={log} vacio="Sin correos procesados."
                columnas={[
                  { titulo: 'Fecha', celda: (l) => <span className="peq">{String(l.fecha).replace('T', ' ').slice(0, 19)}</span> }, { titulo: 'Remitente', campo: 'remitente' },
                  { titulo: 'Comando', celda: (l) => <span className="mono">{l.comando ?? '-'}</span> },
                  { titulo: 'Resultado', celda: (l) => <span className={`badge ${l.exito ? 'b-ok' : 'b-err'}`}>{l.exito ? 'OK' : 'ERROR'}</span> },
                ]} />
            </section>
          )}
        </div>
        <section className="card">
          <h2 className="mb-2 font-bold">Catálogo de comandos ({catalogo.length})</h2>
          <Campo etiqueta="Filtrar comandos" valor={filtro} onChange={setFiltro} />
          <div className="mt-2 max-h-[40rem] overflow-auto">
            {grupos.map((g) => (
              <div key={g.cu}><h3 className="mt-3 font-bold">{g.titulo}</h3>
                {g.items.map((c) => (
                  <button key={c.nombre} className="linea block w-full text-left" onClick={() => setAsunto(c.ejemplo)}>
                    <b className="mono">{c.nombre}</b> <span className="suave peq ml-2">{c.permiso ?? 'todos'}</span><br />
                    <span className="peq mono text-accent">{c.sintaxis}</span><br /><span className="peq suave">{c.descripcion}</span>
                  </button>
                ))}
              </div>
            ))}
          </div>
          <p className="peq suave mt-2">Haga clic en un comando para cargar su ejemplo.</p>
        </section>
      </div>
    </div>
  )
}
