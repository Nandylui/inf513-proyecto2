import { useEffect, useState } from 'react'
import { ChartBar, Download } from 'lucide-react'
import api from '../api/client'
import { Campo, Titulo, Vacio } from '../components/ui'
import { fechaCorta } from '../lib/format'

function rutaDe(tipo, sesionId, estado) {
  switch (tipo) {
    case 'estadisticas': return { ruta: 'reportes/estadisticas', csv: 'estadisticas', q: {} }
    case 'asistencia': return { ruta: 'reportes/asistencia', csv: 'asistencia', q: {} }
    case 'consejeros': return { ruta: 'reportes/asistencia', csv: 'asistencia', q: { sesion: 'CONSEJEROS' } }
    case 'sesion': return sesionId ? { ruta: 'reportes/asistencia', csv: 'asistencia', q: { sesion: sesionId } } : null
    case 'resoluciones': return { ruta: 'reportes/resoluciones', csv: 'resoluciones', q: { estado } }
    case 'tramites': return { ruta: 'reportes/tramites', csv: 'tramites', q: {} }
    case 'votaciones': return sesionId ? { ruta: `reportes/votaciones/${sesionId}`, csv: 'votaciones', q: { sesion: sesionId } } : null
    default: return null
  }
}

export default function Reportes() {
  const [tipo, setTipo] = useState('estadisticas')
  const [sesionId, setSesionId] = useState('')
  const [estado, setEstado] = useState('')
  const [rep, setRep] = useState(null)
  const [sesiones, setSesiones] = useState([])

  useEffect(() => { api.get('sesiones').then(setSesiones).catch(() => {}) }, [])
  useEffect(() => {
    const r = rutaDe(tipo, sesionId, estado)
    if (!r) { setRep(null); return }
    api.get(r.ruta, r.q).then(setRep)
  }, [tipo, sesionId, estado])

  const exportar = () => { const r = rutaDe(tipo, sesionId, estado); if (r) api.descargar(`reportes/${r.csv}/csv`, `reporte_${r.csv}.csv`, r.q) }

  return (
    <div>
      <Titulo icono={ChartBar} subtitulo="Asistencia, resoluciones, trámites y votaciones; exportables a CSV (compatible con Excel).">Reportes y estadísticas</Titulo>
      <div className="card">
        <div className="mb-4 flex flex-wrap items-end gap-3">
          <div className="w-64"><Campo etiqueta="Reporte" valor={tipo} onChange={(v) => { setTipo(v); setSesionId('') }} opciones={[
            { valor: 'estadisticas', texto: 'Estadísticas generales' }, { valor: 'asistencia', texto: 'Asistencia por sesión' }, { valor: 'consejeros', texto: 'Asistencia por consejero' },
            { valor: 'sesion', texto: 'Asistencia de una sesión…' }, { valor: 'resoluciones', texto: 'Resoluciones' }, { valor: 'tramites', texto: 'Trámites y documentos' }, { valor: 'votaciones', texto: 'Votaciones de una sesión…' }]} /></div>
          {(tipo === 'sesion' || tipo === 'votaciones') && (
            <div className="w-64"><Campo etiqueta="Sesión" valor={sesionId} onChange={setSesionId} opciones={[{ valor: '', texto: 'Seleccione sesión…' }, ...sesiones.map((s) => ({ valor: String(s.id_sesion), texto: `#${s.id_sesion} · ${fechaCorta(s.fecha_hora)}` }))]} /></div>
          )}
          {tipo === 'resoluciones' && <div className="w-52"><Campo etiqueta="Estado" valor={estado} onChange={setEstado} opciones={[{ valor: '', texto: 'Todos los estados' }, 'APROBADA', 'PROMULGADA', 'VETADA', 'PUBLICADA', 'RECHAZADA']} /></div>}
          <span className="flex-1" />
          <button className="btn btn-sec" onClick={exportar} disabled={!rep}><Download size={16} aria-hidden="true" />Exportar CSV</button>
        </div>
        {rep ? (
          <>
            <h2 className="mb-2 font-bold">{rep.titulo}</h2>
            {rep.resumen && Object.keys(rep.resumen).length > 0 && (
              <div className="mb-3 grid grid-cols-2 gap-3 md:grid-cols-4">{Object.entries(rep.resumen).map(([k, v]) => <div key={k} className="kpi"><b>{String(v)}</b><span>{k.replaceAll('_', ' ')}</span></div>)}</div>
            )}
            <div className="tabla-wrap">
              <table className="tabla"><caption className="sr-only">{rep.titulo}</caption>
                <thead><tr>{rep.columnas.map((c, i) => <th key={i} scope="col">{c}</th>)}</tr></thead>
                <tbody>
                  {rep.filas.length === 0 && <tr><td colSpan={rep.columnas.length}><Vacio>Sin registros.</Vacio></td></tr>}
                  {rep.filas.map((f, i) => <tr key={i}>{f.map((c, j) => <td key={j}>{c === true ? 'SI' : c === false ? 'NO' : (c ?? '-')}</td>)}</tr>)}
                </tbody>
              </table>
            </div>
          </>
        ) : <Vacio>Seleccione los parámetros del reporte.</Vacio>}
      </div>
    </div>
  )
}
