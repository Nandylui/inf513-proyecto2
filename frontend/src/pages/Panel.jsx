import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { LayoutDashboard } from 'lucide-react'
import api from '../api/client'
import { useAuth } from '../context/AuthContext'
import { Cargando, Insignia, Titulo, Vacio } from '../components/ui'
import { GraficoBarras, GraficoDona, GraficoLineas, TarjetaGrafico } from '../components/Graficos'
import { bs, fechaHora, rolLegible } from '../lib/format'

const Kpi = ({ valor, etiqueta }) => <div className="kpi"><b>{valor}</b><span>{etiqueta}</span></div>

export default function Panel() {
  const { usuario, puede } = useAuth()
  const [d, setD] = useState(null)
  const [sesiones, setSesiones] = useState([])
  const [notis, setNotis] = useState([])
  const [cargando, setCargando] = useState(true)

  useEffect(() => {
    const tareas = [
      puede('reporte.ver') ? api.get('dashboard').then(setD) : Promise.resolve(),
      puede('sesion.ver') ? api.get('sesiones', { q: 'PROGRAMADA' }, { silencioso: true }).then((r) => setSesiones(r.slice(0, 4))).catch(() => {}) : Promise.resolve(),
      api.get('notificaciones', null, { silencioso: true }).then((r) => setNotis(r.slice(0, 5))).catch(() => {}),
    ]
    Promise.allSettled(tareas).finally(() => setCargando(false))
  }, [puede])

  if (cargando) return <Cargando />
  const k = d?.kpis
  const a = d?.accesos
  const p = d?.pagos

  return (
    <div>
      <Titulo icono={LayoutDashboard} subtitulo={`${rolLegible(usuario?.rol)} · Sistema de Gestión del Consejo Facultativo`}>Bienvenido, {usuario?.nombre}</Titulo>

      {k && (
        <section aria-label="Indicadores del Consejo" className="mb-4 grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
          <Kpi valor={k.usuarios_activos} etiqueta="usuarios activos" />
          <Kpi valor={k.mandatos_vigentes} etiqueta="mandatos vigentes" />
          <Kpi valor={k.sesiones_programadas} etiqueta="sesiones programadas" />
          <Kpi valor={k.documentos_en_revision} etiqueta="documentos en revisión" />
          <Kpi valor={k.resoluciones} etiqueta="resoluciones emitidas" />
          <Kpi valor={`${k.tasa_aprobacion}%`} etiqueta="tasa de aprobación" />
        </section>
      )}

      {d && (
        <>
          <h2 className="mb-2 mt-5 text-lg font-bold">Actividad del Consejo</h2>
          <div className="grid gap-3 lg:grid-cols-2">
            <TarjetaGrafico titulo="Sesiones por mes" descripcion="Últimos 6 meses y próximos 2" datos={d.sesiones_por_mes} columnas={[{ campo: 'etiqueta', titulo: 'Mes' }, { campo: 'total', titulo: 'Sesiones' }]}>
              <GraficoBarras datos={d.sesiones_por_mes} x="etiqueta" series={[{ campo: 'total', nombre: 'Sesiones' }]} />
            </TarjetaGrafico>
            <TarjetaGrafico titulo="Resoluciones por estado" datos={d.resoluciones_por_estado} columnas={[{ campo: 'estado', titulo: 'Estado' }, { campo: 'total', titulo: 'Resoluciones' }]}>
              <GraficoDona datos={d.resoluciones_por_estado} nombre="estado" valor="total" />
            </TarjetaGrafico>
            <TarjetaGrafico titulo="Asistencia frente al quórum" descripcion="Últimas sesiones realizadas" datos={d.asistencia_por_sesion} columnas={[{ campo: 'sesion', titulo: 'Sesión' }, { campo: 'presentes', titulo: 'Presentes' }, { campo: 'quorum', titulo: 'Quórum requerido' }]}>
              <GraficoBarras datos={d.asistencia_por_sesion} x="sesion" series={[{ campo: 'presentes', nombre: 'Presentes' }, { campo: 'quorum', nombre: 'Quórum requerido' }]} />
            </TarjetaGrafico>
            <TarjetaGrafico titulo="Documentos por tipo de trámite" datos={d.documentos_por_tramite} columnas={[{ campo: 'tramite', titulo: 'Trámite' }, { campo: 'total', titulo: 'Documentos' }]}>
              <GraficoBarras datos={d.documentos_por_tramite} x="tramite" horizontal series={[{ campo: 'total', nombre: 'Documentos' }]} />
            </TarjetaGrafico>
            <TarjetaGrafico titulo="Votos emitidos en resoluciones" datos={d.votos} columnas={[{ campo: 'voto', titulo: 'Voto' }, { campo: 'total', titulo: 'Total' }]}>
              <GraficoDona datos={d.votos} nombre="voto" valor="total" />
            </TarjetaGrafico>
            {p && !p.propios && (
              <TarjetaGrafico titulo="Recaudación por mes" descripcion={`Recaudado: ${bs(p.recaudado)} · ${p.aprobados} pagos aprobados · ${p.rechazados} rechazados`} datos={p.por_mes} columnas={[{ campo: 'mes', titulo: 'Mes' }, { campo: 'monto', titulo: 'Monto (Bs)' }, { campo: 'pagos', titulo: 'Pagos' }]}>
                <GraficoBarras datos={p.por_mes} x="mes" series={[{ campo: 'monto', nombre: 'Bs recaudados' }]} />
              </TarjetaGrafico>
            )}
          </div>
          {p?.propios && (
            <div className="card mt-3"><h2 className="font-bold">Mis pagos</h2>
              <p className="mt-1">Pagado: <b>{bs(p.recaudado)}</b> · {p.aprobados} aprobados · {p.rechazados} rechazados. <Link to="/pagos" className="underline">Ir a mis pagos</Link></p></div>
          )}
        </>
      )}

      {a && (
        <>
          <h2 className="mb-2 mt-6 text-lg font-bold">Flujo de accesos al sistema <span className="suave text-sm font-normal">(últimos {a.dias} días)</span></h2>
          <section aria-label="Indicadores de acceso" className="mb-3 grid grid-cols-2 gap-3 md:grid-cols-5">
            <Kpi valor={a.ingresos_hoy} etiqueta="ingresos exitosos hoy" />
            <Kpi valor={a.fallidos_hoy} etiqueta="intentos fallidos hoy" />
            <Kpi valor={a.accesos_periodo} etiqueta="accesos a recursos" />
            <Kpi valor={a.denegados_periodo} etiqueta="accesos denegados" />
            <Kpi valor={a.visitas_total?.toLocaleString('es-BO')} etiqueta="visitas totales" />
          </section>
          <div className="grid gap-3 lg:grid-cols-2">
            <TarjetaGrafico titulo="Ingresos y fallos por día" datos={a.serie_diaria} columnas={[{ campo: 'dia', titulo: 'Día' }, { campo: 'ingresos', titulo: 'Ingresos' }, { campo: 'fallidos', titulo: 'Fallidos' }, { campo: 'accesos', titulo: 'Accesos' }]}>
              <GraficoLineas datos={a.serie_diaria} x="dia" series={[{ campo: 'ingresos', nombre: 'Ingresos exitosos' }, { campo: 'fallidos', nombre: 'Intentos fallidos' }, { campo: 'accesos', nombre: 'Accesos a recursos' }]} />
            </TarjetaGrafico>
            <TarjetaGrafico titulo="Recursos más accedidos" datos={a.recursos_mas_accedidos} columnas={[{ campo: 'recurso', titulo: 'Recurso' }, { campo: 'accesos', titulo: 'Accesos' }]}>
              <GraficoBarras datos={a.recursos_mas_accedidos} x="recurso" horizontal series={[{ campo: 'accesos', nombre: 'Accesos' }]} />
            </TarjetaGrafico>
          </div>
        </>
      )}

      <div className="mt-6 grid gap-3 lg:grid-cols-2">
        <section className="card" aria-label="Próximas sesiones">
          <h2 className="mb-2 font-bold">Próximas sesiones</h2>
          {sesiones.length === 0 ? <Vacio>No hay sesiones programadas.</Vacio> : sesiones.map((s) => (
            <div key={s.id_sesion} className="linea"><b>#{s.id_sesion} · {s.tipo}</b> <Insignia estado={s.estado} /><br /><span className="suave">{fechaHora(s.fecha_hora)} · {s.lugar}</span></div>
          ))}
          {puede('sesion.ver') && <Link to="/sesiones" className="mt-2 inline-block underline">Ver todas las sesiones →</Link>}
        </section>
        <section className="card" aria-label="Mis notificaciones">
          <h2 className="mb-2 font-bold">Mis notificaciones</h2>
          {notis.length === 0 ? <Vacio>Sin notificaciones.</Vacio> : notis.map((n) => (
            <div key={n.id_notificacion} className="linea"><b>{n.asunto}</b> {!n.leida && <span className="badge">nueva</span>}<br /><span className="suave peq">{fechaHora(n.fecha)} · {n.tipo}</span></div>
          ))}
          <Link to="/fiscalizacion" className="mt-2 inline-block underline">Ver todas →</Link>
        </section>
      </div>
    </div>
  )
}
