import { useEffect, useState } from 'react'
import { Plus, Wallet } from 'lucide-react'
import api from '../api/client'
import { useToast } from '../context/ToastContext'
import { useForm } from '../hooks/useForm'
import { reglas } from '../lib/validators'
import { bs, fechaHora } from '../lib/format'
import { Campo, Confirmar, Insignia, Modal, Tabla, Tabs, Titulo } from '../components/ui'
import { GraficoBarras, GraficoDona, TarjetaGrafico } from '../components/Graficos'

const vacio = { codigo: '', nombre: '', descripcion: '', monto: '', permite_plan: false, max_cuotas: 1, activo: true }
const esquema = {
  codigo: [reglas.requerido(), reglas.patron(/^[A-Za-z0-9_]+$/, 'Use solo letras, números y guion bajo.'), reglas.maximo(30)],
  nombre: [reglas.requerido(), reglas.minimo(3), reglas.maximo(120)],
  descripcion: [reglas.maximo(500)],
  monto: [reglas.requerido(), reglas.numero(), reglas.mayorQue(0, 'El monto debe ser mayor que cero.'), reglas.rango(0.01, 5000, 'El monto máximo por operación es Bs 5.000.')],
  max_cuotas: [reglas.entero(), reglas.rango(1, 12)],
}

export default function PagosAdmin() {
  const toast = useToast()
  const [tab, setTab] = useState('resumen')
  const [resumen, setResumen] = useState(null)
  const [conceptos, setConceptos] = useState([])
  const [trans, setTrans] = useState([])
  const [filtro, setFiltro] = useState({ estado: '', concepto: '', q: '', desde: '', hasta: '' })
  const [modal, setModal] = useState(null)
  const [borrar, setBorrar] = useState(null)
  const [planes, setPlanes] = useState([])
  const f = useForm(vacio, esquema)

  const cargarConceptos = () => api.get('conceptos-pago', { todos: 1 }).then(setConceptos)
  const cargarTrans = (fl = filtro) => api.get('pagos-admin/transacciones', fl).then(setTrans)
  useEffect(() => { api.get('pagos-admin/resumen').then(setResumen); cargarConceptos(); cargarTrans(); api.get('planes-pago', { todos: 1 }).then(setPlanes) }, []) // eslint-disable-line react-hooks/exhaustive-deps

  const abrir = (c) => { f.reiniciar(c ? { ...c, descripcion: c.descripcion ?? '' } : vacio); setModal({ id: c?.id_concepto }) }
  const guardar = f.enviar(async (v) => {
    const cuerpo = { ...v, monto: Number(v.monto), max_cuotas: v.permite_plan ? Number(v.max_cuotas) : 1, descripcion: v.descripcion || null }
    if (modal.id) await api.put(`conceptos-pago/${modal.id}`, cuerpo); else await api.post('conceptos-pago', cuerpo)
    toast.ok('Concepto guardado.'); setModal(null); cargarConceptos()
  })
  const eliminar = async (c) => { const r = await api.delete(`conceptos-pago/${c.id_concepto}`); toast.ok(r.message); setBorrar(null); cargarConceptos() }

  return (
    <div>
      <Titulo icono={Wallet} subtitulo="Conceptos de cobro, transacciones de todos los usuarios y planes de pago vigentes.">Administración de pagos</Titulo>
      <Tabs valor={tab} onCambio={setTab} opciones={[{ id: 'resumen', texto: 'Resumen' }, { id: 'conceptos', texto: 'Conceptos de pago' }, { id: 'trans', texto: 'Transacciones' }, { id: 'planes', texto: 'Planes de pago' }]} />

      {tab === 'resumen' && resumen && (
        <>
          <div className="mb-3 grid grid-cols-2 gap-3 md:grid-cols-5">
            <div className="kpi"><b>{bs(resumen.recaudado)}</b><span>recaudado</span></div><div className="kpi"><b>{resumen.aprobados}</b><span>pagos aprobados</span></div>
            <div className="kpi"><b>{resumen.rechazados}</b><span>pagos rechazados</span></div><div className="kpi"><b>{resumen.planes_activos}</b><span>planes activos</span></div><div className="kpi"><b>{resumen.cuotas_pendientes}</b><span>cuotas pendientes</span></div>
          </div>
          <div className="grid gap-3 lg:grid-cols-2">
            <TarjetaGrafico titulo="Recaudación por mes (Bs)" datos={resumen.por_mes} columnas={[{ campo: 'mes', titulo: 'Mes' }, { campo: 'monto', titulo: 'Monto (Bs)' }, { campo: 'pagos', titulo: 'Pagos' }]}>
              <GraficoBarras datos={resumen.por_mes} x="mes" series={[{ campo: 'monto', nombre: 'Bs recaudados' }]} />
            </TarjetaGrafico>
            <TarjetaGrafico titulo="Recaudación por concepto" datos={resumen.por_concepto} columnas={[{ campo: 'concepto', titulo: 'Concepto' }, { campo: 'monto', titulo: 'Monto (Bs)' }, { campo: 'pagos', titulo: 'Pagos' }]}>
              <GraficoDona datos={resumen.por_concepto} nombre="concepto" valor="monto" />
            </TarjetaGrafico>
          </div>
        </>
      )}

      {tab === 'conceptos' && (
        <div className="card">
          <div className="mb-3 flex justify-end"><button className="btn" onClick={() => abrir(null)}><Plus size={16} aria-hidden="true" />Nuevo concepto</button></div>
          <Tabla clave="id_concepto" filas={conceptos} vacio="Sin conceptos."
            columnas={[
              { titulo: 'Código', celda: (c) => <span className="mono">{c.codigo}</span> }, { titulo: 'Nombre', campo: 'nombre' }, { titulo: 'Monto', celda: (c) => bs(c.monto) },
              { titulo: 'Cuotas', celda: (c) => (c.permite_plan ? `hasta ${c.max_cuotas}` : 'pago único') }, { titulo: 'Estado', celda: (c) => <Insignia estado={c.activo ? 'ACTIVO' : 'INACTIVO'} /> },
              { titulo: 'Acciones', celda: (c) => <div className="flex gap-1"><button className="btn btn-sec btn-mini" onClick={() => abrir(c)}>Editar</button><button className="btn btn-peligro btn-mini" onClick={() => setBorrar(c)}>Eliminar</button></div> },
            ]} />
        </div>
      )}

      {tab === 'trans' && (
        <div className="card">
          <form className="mb-3 flex flex-wrap items-end gap-3" onSubmit={(e) => { e.preventDefault(); cargarTrans() }}>
            <div className="w-40"><Campo etiqueta="Estado" valor={filtro.estado} onChange={(v) => setFiltro({ ...filtro, estado: v })} opciones={[{ valor: '', texto: 'Todos' }, 'APROBADO', 'RECHAZADO']} /></div>
            <div className="w-56"><Campo etiqueta="Concepto" valor={filtro.concepto} onChange={(v) => setFiltro({ ...filtro, concepto: v })} opciones={[{ valor: '', texto: 'Todos' }, ...conceptos.map((c) => ({ valor: String(c.id_concepto), texto: c.nombre }))]} /></div>
            <div className="w-48"><Campo etiqueta="Referencia, CI o nombre" valor={filtro.q} onChange={(v) => setFiltro({ ...filtro, q: v })} /></div>
            <div className="w-40"><Campo etiqueta="Desde" tipo="date" valor={filtro.desde} onChange={(v) => setFiltro({ ...filtro, desde: v })} /></div>
            <div className="w-40"><Campo etiqueta="Hasta" tipo="date" valor={filtro.hasta} onChange={(v) => setFiltro({ ...filtro, hasta: v })} /></div>
            <button className="btn">Filtrar</button>
          </form>
          <Tabla clave="id_pago" filas={trans} vacio="Sin transacciones."
            columnas={[
              { titulo: 'Fecha', celda: (p) => fechaHora(p.created_at) }, { titulo: 'Referencia', celda: (p) => <span className="mono">{p.referencia}</span> },
              { titulo: 'Usuario', celda: (p) => `${p.usuario?.nombres ?? ''} ${p.usuario?.apellidos ?? ''}` }, { titulo: 'Concepto', celda: (p) => p.concepto?.nombre },
              { titulo: 'Tipo', campo: 'tipo' }, { titulo: 'Monto', celda: (p) => bs(p.monto) }, { titulo: 'Estado', celda: (p) => <Insignia estado={p.estado} /> },
              { titulo: 'Motivo', celda: (p) => <span className="peq">{p.motivo_rechazo ?? ''}</span> },
            ]} />
        </div>
      )}

      {tab === 'planes' && (
        <div className="card">
          <Tabla clave="id_plan" filas={planes} vacio="No hay planes de pago."
            columnas={[
              { titulo: 'Plan', celda: (p) => `#${p.id_plan}` }, { titulo: 'Usuario', celda: (p) => `${p.usuario?.nombres ?? ''} ${p.usuario?.apellidos ?? ''}` }, { titulo: 'Concepto', celda: (p) => p.concepto?.nombre },
              { titulo: 'Total', celda: (p) => bs(p.monto_total) }, { titulo: 'Pagado', celda: (p) => bs(p.pagado) },
              { titulo: 'Cuotas', celda: (p) => `${p.cuotas.filter((c) => c.estado === 'PAGADA').length}/${p.num_cuotas}${p.cuotas.some((c) => c.vencida) ? ' · vencidas' : ''}` },
              { titulo: 'Estado', celda: (p) => <Insignia estado={p.estado} /> },
            ]} />
        </div>
      )}

      {modal && (
        <Modal titulo={modal.id ? 'Editar concepto de pago' : 'Nuevo concepto de pago'} onCerrar={() => setModal(null)}>
          <form onSubmit={guardar} noValidate>
            <div className="grid gap-3 sm:grid-cols-2">
              <Campo etiqueta="Código" requerido {...f.campo('codigo')} /><Campo etiqueta="Nombre" requerido {...f.campo('nombre')} />
              <Campo className="sm:col-span-2" etiqueta="Descripción" area filas={2} {...f.campo('descripcion')} />
              <Campo etiqueta="Monto (Bs)" tipo="number" step="0.01" min="0" requerido {...f.campo('monto')} />
              {f.valores.permite_plan && <Campo etiqueta="Máximo de cuotas" tipo="number" min="1" max="12" {...f.campo('max_cuotas')} />}
            </div>
            <div className="mt-3 flex flex-wrap gap-4 text-sm">
              <label className="flex items-center gap-2"><input type="checkbox" checked={f.valores.permite_plan} onChange={(e) => f.cambiar('permite_plan', e.target.checked)} /> Permite plan de pagos</label>
              <label className="flex items-center gap-2"><input type="checkbox" checked={f.valores.activo} onChange={(e) => f.cambiar('activo', e.target.checked)} /> Activo</label>
            </div>
            <div className="mt-4 flex justify-end gap-2"><button type="button" className="btn btn-sec" onClick={() => setModal(null)}>Cancelar</button><button className="btn" disabled={f.enviando}>Guardar</button></div>
          </form>
        </Modal>
      )}
      {borrar && <Confirmar peligro textoSi="Eliminar" mensaje={`¿Eliminar el concepto «${borrar.nombre}»? Si ya tiene pagos asociados solo se desactivará.`} onNo={() => setBorrar(null)} onSi={() => eliminar(borrar)} />}
    </div>
  )
}
