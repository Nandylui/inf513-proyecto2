import { useCallback, useEffect, useState } from 'react'
import { ClipboardList, ChevronLeft, ChevronRight } from 'lucide-react'
import api from '../api/client'
import { Campo, Tabla, Tabs, Titulo } from '../components/ui'
import { GraficoBarras, GraficoLineas, TarjetaGrafico } from '../components/Graficos'
import { fechaHora } from '../lib/format'

const EVENTOS = ['LOGIN_OK', 'LOGIN_FALLIDO', 'LOGOUT', 'ACCESO', 'DENEGADO']
const ETIQUETA = { LOGIN_OK: 'Ingreso exitoso', LOGIN_FALLIDO: 'Intento fallido', LOGOUT: 'Cierre de sesión', ACCESO: 'Acceso a recurso', DENEGADO: 'Acceso denegado' }
const CLASE = { LOGIN_OK: 'b-ok', LOGIN_FALLIDO: 'b-err', LOGOUT: '', ACCESO: '', DENEGADO: 'b-warn' }

export default function Bitacora() {
  const [tab, setTab] = useState('registro')
  const [filtro, setFiltro] = useState({ evento: '', ip: '', q: '', desde: '', hasta: '' })
  const [pagina, setPagina] = useState(1)
  const [lista, setLista] = useState(null)
  const [resumen, setResumen] = useState(null)
  const [visitas, setVisitas] = useState(null)
  const [dias, setDias] = useState('14')

  const cargar = useCallback((fl, pag) => api.get('bitacora', { ...fl, page: pag, por_pagina: 20 }).then(setLista), [])
  useEffect(() => { cargar(filtro, pagina) }, [pagina]) // eslint-disable-line react-hooks/exhaustive-deps
  useEffect(() => { api.get('bitacora/resumen', { dias }).then(setResumen); api.get('visitas/resumen').then(setVisitas) }, [dias])

  const filtrar = (e) => { e.preventDefault(); setPagina(1); cargar(filtro, 1) }

  return (
    <div>
      <Titulo icono={ClipboardList} subtitulo="Registro persistente en base de datos de ingresos exitosos, intentos fallidos, direcciones IP y recursos más accedidos.">Bitácora de accesos</Titulo>
      <Tabs valor={tab} onCambio={setTab} opciones={[{ id: 'registro', texto: 'Registro de eventos' }, { id: 'analisis', texto: 'Análisis de accesos' }]} />

      {tab === 'registro' && (
        <div className="card">
          <form className="mb-3 flex flex-wrap items-end gap-3" onSubmit={filtrar}>
            <div className="w-48"><Campo etiqueta="Evento" valor={filtro.evento} onChange={(v) => setFiltro({ ...filtro, evento: v })} opciones={[{ valor: '', texto: 'Todos' }, ...EVENTOS.map((e) => ({ valor: e, texto: ETIQUETA[e] }))]} /></div>
            <div className="w-40"><Campo etiqueta="IP" placeholder="192.168." valor={filtro.ip} onChange={(v) => setFiltro({ ...filtro, ip: v })} /></div>
            <div className="w-52"><Campo etiqueta="Correo o recurso" valor={filtro.q} onChange={(v) => setFiltro({ ...filtro, q: v })} /></div>
            <div className="w-40"><Campo etiqueta="Desde" tipo="date" valor={filtro.desde} onChange={(v) => setFiltro({ ...filtro, desde: v })} /></div>
            <div className="w-40"><Campo etiqueta="Hasta" tipo="date" valor={filtro.hasta} onChange={(v) => setFiltro({ ...filtro, hasta: v })} /></div>
            <button className="btn">Filtrar</button>
          </form>
          {lista && (
            <>
              <Tabla clave="id_bitacora" filas={lista.datos} vacio="Sin eventos."
                columnas={[
                  { titulo: 'Fecha', celda: (b) => fechaHora(b.fecha) }, { titulo: 'Evento', celda: (b) => <span className={`badge ${CLASE[b.evento]}`}>{ETIQUETA[b.evento] ?? b.evento}</span> },
                  { titulo: 'Usuario', celda: (b) => (b.usuario ? `${b.usuario.nombres} ${b.usuario.apellidos}` : b.correo ?? '-') },
                  { titulo: 'IP', celda: (b) => <span className="mono">{b.ip ?? '-'}</span> }, { titulo: 'Recurso', celda: (b) => <span className="mono peq">{b.metodo ? `${b.metodo} ` : ''}{b.recurso ?? ''}</span> },
                  { titulo: 'Estado', celda: (b) => b.codigo_http ?? '-' }, { titulo: 'Detalle', celda: (b) => <span className="peq">{b.detalle ?? ''}</span> },
                ]} />
              <nav className="mt-3 flex items-center justify-between no-imprimir" aria-label="Paginación">
                <span className="suave peq">{lista.total} eventos · página {lista.pagina} de {lista.ultima_pagina}</span>
                <div className="flex gap-2">
                  <button className="btn btn-sec btn-mini" disabled={lista.pagina <= 1} onClick={() => setPagina((p) => p - 1)}><ChevronLeft size={14} aria-hidden="true" />Anterior</button>
                  <button className="btn btn-sec btn-mini" disabled={lista.pagina >= lista.ultima_pagina} onClick={() => setPagina((p) => p + 1)}>Siguiente<ChevronRight size={14} aria-hidden="true" /></button>
                </div>
              </nav>
            </>
          )}
        </div>
      )}

      {tab === 'analisis' && resumen && (
        <>
          <div className="mb-3 flex flex-wrap items-end justify-between gap-3">
            <div className="w-48"><Campo etiqueta="Periodo" valor={dias} onChange={setDias} opciones={[{ valor: '7', texto: 'Últimos 7 días' }, { valor: '14', texto: 'Últimos 14 días' }, { valor: '30', texto: 'Últimos 30 días' }]} /></div>
            <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
              <div className="kpi"><b>{resumen.ingresos_hoy}</b><span>ingresos hoy</span></div><div className="kpi"><b>{resumen.fallidos_hoy}</b><span>fallidos hoy</span></div>
              <div className="kpi"><b>{resumen.accesos_periodo}</b><span>accesos al sistema</span></div><div className="kpi"><b>{resumen.denegados_periodo}</b><span>accesos denegados</span></div>
            </div>
          </div>
          <div className="grid gap-3 lg:grid-cols-2">
            <TarjetaGrafico titulo="Ingresos, fallos y accesos por día" datos={resumen.serie_diaria} columnas={[{ campo: 'dia', titulo: 'Día' }, { campo: 'ingresos', titulo: 'Ingresos' }, { campo: 'fallidos', titulo: 'Fallidos' }, { campo: 'accesos', titulo: 'Accesos' }]}>
              <GraficoLineas datos={resumen.serie_diaria} x="dia" series={[{ campo: 'ingresos', nombre: 'Ingresos exitosos' }, { campo: 'fallidos', nombre: 'Intentos fallidos' }, { campo: 'accesos', nombre: 'Accesos a recursos' }]} />
            </TarjetaGrafico>
            <TarjetaGrafico titulo="Recursos más accedidos (API)" datos={resumen.recursos_mas_accedidos} columnas={[{ campo: 'recurso', titulo: 'Recurso' }, { campo: 'accesos', titulo: 'Accesos' }]}>
              <GraficoBarras datos={resumen.recursos_mas_accedidos} x="recurso" horizontal series={[{ campo: 'accesos', nombre: 'Accesos' }]} />
            </TarjetaGrafico>
            <section className="card"><h2 className="mb-2 font-bold">IP más activas</h2>
              <Tabla filas={resumen.ips_mas_activas} vacio="Sin datos." columnas={[{ titulo: 'IP', celda: (r) => <span className="mono">{r.ip}</span> }, { titulo: 'Eventos', campo: 'eventos' }, { titulo: 'Fallidos', campo: 'fallidos' }]} /></section>
            <section className="card"><h2 className="mb-2 font-bold">Correos con intentos fallidos</h2>
              <Tabla filas={resumen.fallidos_por_correo} vacio="Sin intentos fallidos." columnas={[{ titulo: 'Correo', campo: 'correo' }, { titulo: 'Intentos', campo: 'intentos' }]} /></section>
            {visitas && (
              <TarjetaGrafico titulo={`Páginas más visitadas (total ${visitas.total.toLocaleString('es-BO')})`} datos={visitas.paginas} columnas={[{ campo: 'pagina', titulo: 'Página' }, { campo: 'contador', titulo: 'Visitas' }]} alto={300}>
                <GraficoBarras datos={visitas.paginas.slice(0, 10)} x="pagina" horizontal series={[{ campo: 'contador', nombre: 'Visitas' }]} />
              </TarjetaGrafico>
            )}
          </div>
        </>
      )}
    </div>
  )
}
