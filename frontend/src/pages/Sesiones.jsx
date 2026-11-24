import { useCallback, useEffect, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { CalendarDays, Plus } from 'lucide-react'
import api from '../api/client'
import { useAuth } from '../context/AuthContext'
import { useToast } from '../context/ToastContext'
import { useForm } from '../hooks/useForm'
import { reglas } from '../lib/validators'
import { Campo, Confirmar, Insignia, Tabla, Titulo } from '../components/ui'
import { fechaHora, paraServidor } from '../lib/format'

const inicial = { fecha_hora: '', lugar: '', tipo: 'ORDINARIA', solicitantes: 0, orden: '' }
const esquema = {
  fecha_hora: [reglas.requerido('Indique la fecha y hora.'), reglas.fechaFutura('La sesión debe convocarse para una fecha futura.')],
  lugar: [reglas.requerido(), reglas.maximo(120)],
  solicitantes: [reglas.entero(), reglas.rango(0, 99)],
}
const esquemaExcusa = { motivo: [reglas.requerido('Indique el motivo de la excusa.'), reglas.minimo(10, 'Describa el motivo con al menos 10 caracteres.'), reglas.maximo(500)] }

export default function Sesiones() {
  const { puede } = useAuth()
  const toast = useToast()
  const [params] = useSearchParams()
  const [sesiones, setSesiones] = useState([])
  const [sel, setSel] = useState(null)
  const [excusas, setExcusas] = useState([])
  const [nueva, setNueva] = useState(false)
  const [punto, setPunto] = useState('')
  const [archivo, setArchivo] = useState(null)
  const [firmado, setFirmado] = useState(false)
  const [cancelar, setCancelar] = useState(false)
  const f = useForm(inicial, esquema)
  const ex = useForm({ motivo: '', ci_titular: '' }, esquemaExcusa)

  const cargar = useCallback(() => api.get('sesiones').then(setSesiones), [])
  const abrir = useCallback((id) => {
    api.get(`sesiones/${id}`).then(setSel)
    api.get(`sesiones/${id}/excusas`).then(setExcusas)
  }, [])
  useEffect(() => { cargar(); const id = params.get('id'); if (id) abrir(id) }, []) // eslint-disable-line react-hooks/exhaustive-deps

  const convocar = f.enviar(async (v) => {
    const orden = v.orden.split('\n').map((x) => x.trim()).filter(Boolean)
    const s = await api.post('sesiones', { fecha_hora: paraServidor(v.fecha_hora), lugar: v.lugar, tipo: v.tipo, orden_del_dia: orden, solicitantes: Number(v.solicitantes) || 0 })
    toast.ok('Sesión convocada; se notificó a los miembros.')
    setNueva(false); f.reiniciar(); cargar(); abrir(s.id_sesion)
  })
  const agregarPunto = async () => {
    if (!punto.trim()) return
    await api.post(`sesiones/${sel.id_sesion}/puntos`, { titulo: punto.trim() })
    setPunto(''); abrir(sel.id_sesion)
  }
  const estado = async (e) => {
    await api.post(`sesiones/${sel.id_sesion}/estado`, { estado: e })
    toast.ok(`Sesión ${e.replace('_', ' ').toLowerCase()}.`); setCancelar(false); cargar(); abrir(sel.id_sesion)
  }
  const excusar = ex.enviar(async (v) => {
    const fd = new FormData()
    fd.append('motivo', v.motivo)
    if (v.ci_titular) fd.append('ci_titular', v.ci_titular)
    fd.append('firmado', firmado ? '1' : '0')
    if (archivo) fd.append('archivo', archivo)
    const r = await api.post(`sesiones/${sel.id_sesion}/excusas`, fd)
    toast.ok(r.habilitacion ? 'Excusa registrada: el suplente quedó habilitado.' : (r.advertencia ?? 'Excusa registrada.'))
    ex.reiniciar(); setArchivo(null); setFirmado(false); abrir(sel.id_sesion)
  })

  const convocarPerm = puede('sesion.convocar')
  return (
    <div>
      <Titulo icono={CalendarDays} subtitulo="Sesiones ordinarias y extraordinarias (Art. 55), orden del día, excusas con adjunto y habilitación automática del suplente."
        acciones={convocarPerm && <button className="btn" onClick={() => setNueva((n) => !n)}><Plus size={16} aria-hidden="true" />{nueva ? 'Cerrar' : 'Convocar sesión'}</button>}>Convocatorias, excusas y suplentes</Titulo>

      {nueva && (
        <form className="card mb-4" onSubmit={convocar} noValidate>
          <h2 className="mb-2 font-bold">Nueva convocatoria</h2>
          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <Campo etiqueta="Fecha y hora" tipo="datetime-local" requerido {...f.campo('fecha_hora')} />
            <Campo etiqueta="Lugar" requerido {...f.campo('lugar')} />
            <Campo etiqueta="Tipo" opciones={['ORDINARIA', 'EXTRAORDINARIA']} {...f.campo('tipo')} />
            {f.valores.tipo === 'EXTRAORDINARIA' && !puede('resolucion.promulgar') && <Campo etiqueta="Miembros solicitantes (mín. 3)" tipo="number" min="0" {...f.campo('solicitantes')} />}
          </div>
          <Campo className="mt-3" etiqueta="Orden del día (un punto por línea)" area placeholder={'Aprobación del POA\nInforme de gestión'} {...f.campo('orden')} />
          <div className="mt-3 flex justify-end"><button className="btn" disabled={f.enviando}>Convocar y notificar al Consejo</button></div>
        </form>
      )}

      <div className="grid items-start gap-4 lg:grid-cols-[5fr_6fr]">
        <div className="card">
          <h2 className="mb-2 font-bold">Sesiones</h2>
          <Tabla clave="id_sesion" filas={sesiones} vacio="Sin sesiones."
            columnas={[
              { titulo: '#', celda: (s) => <button className="underline" onClick={() => abrir(s.id_sesion)} aria-label={`Abrir sesión ${s.id_sesion}`}>{s.id_sesion}</button> },
              { titulo: 'Fecha', celda: (s) => <>{fechaHora(s.fecha_hora)}<br /><span className="suave peq">{s.lugar}</span></> },
              { titulo: 'Tipo', campo: 'tipo' },
              { titulo: 'Estado', celda: (s) => <Insignia estado={s.estado} /> },
            ]} />
        </div>

        {sel && (
          <div className="card" aria-live="polite">
            <div className="flex items-center justify-between gap-2"><h2 className="font-bold">Sesión #{sel.id_sesion} · {sel.tipo}</h2><Insignia estado={sel.estado} /></div>
            <p className="suave mb-2 text-sm">{fechaHora(sel.fecha_hora)} · {sel.lugar} · convocada por {sel.convocada_por}
              {sel.quorum_alcanzado !== null && sel.quorum_alcanzado !== undefined && <> · quórum {sel.quorum_alcanzado ? 'alcanzado' : 'no alcanzado'} ({sel.quorum_presentes}/{sel.quorum_requerido})</>}</p>

            <h3 className="mt-3 font-bold">Orden del día</h3>
            {sel.puntos?.map((p) => <div key={p.id_punto} className="linea"><b>{p.numero}. {p.titulo}</b>{p.descripcion && <span className="suave"> — {p.descripcion}</span>}</div>)}
            {convocarPerm && sel.estado === 'PROGRAMADA' && (
              <>
                <div className="my-2 flex gap-2">
                  <label className="sr-only" htmlFor="nuevo-punto">Nuevo punto</label>
                  <input id="nuevo-punto" className="input" placeholder="Agregar punto al orden del día" value={punto} maxLength={200} onChange={(e) => setPunto(e.target.value)} />
                  <button className="btn btn-sec" onClick={agregarPunto}>Agregar</button>
                </div>
                <div className="mb-3 flex gap-2">
                  <button className="btn btn-sec btn-mini" onClick={() => estado('EN_CURSO')}>Instalar sesión</button>
                  <button className="btn btn-peligro btn-mini" onClick={() => setCancelar(true)}>Cancelar</button>
                </div>
              </>
            )}
            {sel.estado === 'EN_CURSO' && convocarPerm && <button className="btn btn-mini my-2" onClick={() => estado('FINALIZADA')}>Finalizar sesión</button>}

            <h3 className="mt-3 font-bold">Excusas y suplentes habilitados</h3>
            <Tabla clave="id_excusa" filas={excusas} vacio="Sin excusas."
              columnas={[
                { titulo: 'Titular', celda: (e) => `${e.titular?.nombres ?? ''} ${e.titular?.apellidos ?? ''}` },
                { titulo: 'Motivo', campo: 'motivo' },
                { titulo: 'Firma', celda: (e) => (e.firmado_digitalmente ? 'Sí' : 'No') },
                { titulo: 'Sello SHA-256', celda: (e) => <span className="mono peq">{e.archivo_hash ? `${e.archivo_hash.slice(0, 12)}…` : '-'}</span> },
              ]} />
            {sel.habilitaciones?.map((h) => <div key={h.id_habilitacion} className="aviso mt-2">{h.constancia}</div>)}

            {sel.estado === 'PROGRAMADA' && puede('sesion.excusa') && (
              <form className="card-plana mt-3" onSubmit={excusar} noValidate>
                <h3 className="mb-2 font-bold">Presentar excusa</h3>
                {puede('miembros.gestionar') && <Campo className="mb-2" etiqueta="CI del titular" {...ex.campo('ci_titular')} />}
                <Campo etiqueta="Motivo" area requerido {...ex.campo('motivo')} />
                <div className="mt-2 grid gap-2 sm:grid-cols-2">
                  <Campo etiqueta="Adjunto (PDF o imagen, máx. 5 MB)" tipo="file" accept=".pdf,.jpg,.jpeg,.png" valor="" onChange={(a) => {
                    if (a && a.size > 5 * 1024 * 1024) { toast.error('El archivo supera los 5 MB permitidos.'); return }
                    setArchivo(a)
                  }} />
                  <label className="mt-6 flex items-center gap-2 text-sm"><input type="checkbox" checked={firmado} onChange={(e) => setFirmado(e.target.checked)} /> Documento con firma digital</label>
                </div>
                <div className="mt-3 flex justify-end"><button className="btn" disabled={ex.enviando}>Registrar excusa</button></div>
              </form>
            )}
          </div>
        )}
      </div>
      {cancelar && <Confirmar peligro titulo="Cancelar sesión" textoSi="Cancelar sesión" mensaje="¿Cancelar la sesión? Se notificará a los miembros." onNo={() => setCancelar(false)} onSi={() => estado('CANCELADA')} />}
    </div>
  )
}
