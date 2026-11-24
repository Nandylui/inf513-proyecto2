import { useCallback, useEffect, useState } from 'react'
import { Banknote, CreditCard, Landmark, QrCode, Receipt, Smartphone, Star, Trash2 } from 'lucide-react'
import api from '../api/client'
import { useToast } from '../context/ToastContext'
import { useForm } from '../hooks/useForm'
import { reglas, reglasTarjeta } from '../lib/validators'
import { bs, fechaHora } from '../lib/format'
import { Campo, Confirmar, Insignia, Modal, Tabla, Tabs, Titulo, Vacio } from '../components/ui'

const ICONO_TIPO = { TARJETA: CreditCard, QR: QrCode, TRANSFERENCIA: Landmark, BILLETERA: Smartphone }
const TIPOS = [{ valor: 'TARJETA', texto: 'Tarjeta de débito/crédito' }, { valor: 'QR', texto: 'Código QR' }, { valor: 'TRANSFERENCIA', texto: 'Transferencia bancaria' }, { valor: 'BILLETERA', texto: 'Billetera móvil' }]

/** Pide el CVV (nunca se almacena) cuando el método elegido es una tarjeta. */
function SolicitarPago({ titulo, monto, metodos, onCerrar, onPagar }) {
  const [id, setId] = useState(String((metodos.find((m) => m.predeterminado) ?? metodos[0])?.id_metodo ?? ''))
  const [cvv, setCvv] = useState('')
  const [error, setError] = useState('')
  const [enviando, setEnviando] = useState(false)
  const metodo = metodos.find((m) => String(m.id_metodo) === id)
  const confirmar = async () => {
    if (!metodo) { setError('Seleccione un método de pago.'); return }
    if (metodo.tipo === 'TARJETA' && reglasTarjeta.cvv(cvv)) { setError(reglasTarjeta.cvv(cvv)); return }
    setEnviando(true)
    try { await onPagar({ id_metodo: Number(id), cvv: metodo.tipo === 'TARJETA' ? cvv : undefined }) } finally { setEnviando(false); setCvv('') }
  }
  return (
    <Modal titulo={titulo} onCerrar={onCerrar} ancho="max-w-md">
      <p className="mb-3 text-lg">Total a pagar: <b>{bs(monto)}</b></p>
      {metodos.length === 0 ? <p className="aviso aviso-warn">Primero registre un método de pago en la pestaña «Métodos de pago».</p> : (
        <div className="grid gap-3">
          <Campo etiqueta="Método de pago" valor={id} onChange={(v) => { setId(v); setError('') }} opciones={metodos.map((m) => ({ valor: String(m.id_metodo), texto: `${m.alias} — ${m.descripcion}${m.vencida ? ' (vencida)' : ''}` }))} />
          {metodo?.tipo === 'TARJETA' && <Campo etiqueta="CVV de la tarjeta" tipo="password" inputMode="numeric" maxLength={4} autoComplete="off" valor={cvv} onChange={(v) => { setCvv(v.replace(/\D/g, '')); setError('') }} ayuda="No se guarda; solo se usa para esta operación." />}
          {error && <p className="error-campo" role="alert">{error}</p>}
          <p className="aviso peq">Pasarela de pagos <b>simulada</b> (proyecto académico): no se realiza ningún cargo real.</p>
        </div>
      )}
      <div className="mt-4 flex justify-end gap-2"><button className="btn btn-sec" onClick={onCerrar}>Cancelar</button><button className="btn" disabled={enviando || metodos.length === 0} onClick={confirmar}>{enviando ? 'Procesando…' : 'Pagar'}</button></div>
    </Modal>
  )
}

function Comprobante({ id, onCerrar }) {
  const [c, setC] = useState(null)
  useEffect(() => { api.get(`pagos/${id}/comprobante`).then(setC) }, [id])
  return (
    <Modal titulo="Comprobante de pago" onCerrar={onCerrar}>
      {!c ? <p className="suave">Cargando…</p> : (
        <div id="comprobante">
          <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm">
            <dt className="suave">Referencia</dt><dd className="mono">{c.referencia}</dd>
            <dt className="suave">Estado</dt><dd><Insignia estado={c.estado} /></dd>
            <dt className="suave">Fecha</dt><dd>{c.fecha}</dd>
            <dt className="suave">Concepto</dt><dd>{c.concepto}</dd>
            <dt className="suave">Monto</dt><dd><b>{bs(c.monto)}</b> {c.moneda}</dd>
            <dt className="suave">Método</dt><dd>{c.metodo}</dd>
            {c.autorizacion && <><dt className="suave">Autorización</dt><dd className="mono">{c.autorizacion}</dd></>}
            {c.motivo_rechazo && <><dt className="suave">Motivo de rechazo</dt><dd className="text-err">{c.motivo_rechazo}</dd></>}
            <dt className="suave">Pagador</dt><dd>{c.pagador.nombre} · CI {c.pagador.ci}</dd>
          </dl>
          <p className="aviso aviso-warn peq mt-3">{c.aviso}</p>
          <div className="mt-3 flex justify-end gap-2 no-imprimir"><button className="btn btn-sec" onClick={() => window.print()}>Imprimir</button><button className="btn" onClick={onCerrar}>Cerrar</button></div>
        </div>
      )}
    </Modal>
  )
}

function FormMetodo({ onHecho }) {
  const toast = useToast()
  const [tipo, setTipo] = useState('TARJETA')
  const esquemas = {
    TARJETA: { alias: [reglas.requerido(), reglas.minimo(2)], titular: [reglas.requerido('Ingrese el titular tal como figura en la tarjeta.'), reglas.soloLetras()], numero: [reglasTarjeta.numero], vencimiento: [reglasTarjeta.vencimiento], cvv: [reglasTarjeta.cvv] },
    QR: { alias: [reglas.requerido(), reglas.minimo(2)] },
    TRANSFERENCIA: { alias: [reglas.requerido(), reglas.minimo(2)], banco: [reglas.requerido('Ingrese el banco.')], cuenta: [reglas.requerido('Ingrese el número de cuenta.'), reglas.patron(/^\d{6,20}$/, 'La cuenta debe tener entre 6 y 20 dígitos.')], titular: [reglas.soloLetras()] },
    BILLETERA: { alias: [reglas.requerido(), reglas.minimo(2)], banco: [reglas.maximo(60)], celular: [reglas.requerido('Ingrese el celular.'), reglas.patron(/^[67]\d{7}$/, 'El celular debe tener 8 dígitos y comenzar con 6 o 7.')] },
  }
  const f = useForm({ alias: '', titular: '', numero: '', vencimiento: '', cvv: '', banco: '', cuenta: '', celular: '', predeterminado: false }, esquemas[tipo])
  const enviar = f.enviar(async (v) => {
    const cuerpo = { tipo, alias: v.alias, predeterminado: v.predeterminado }
    if (tipo === 'TARJETA') Object.assign(cuerpo, { titular: v.titular, numero: v.numero.replace(/[\s-]/g, ''), vencimiento: v.vencimiento, cvv: v.cvv })
    if (tipo === 'TRANSFERENCIA') Object.assign(cuerpo, { banco: v.banco, cuenta: v.cuenta, titular: v.titular || undefined })
    if (tipo === 'BILLETERA') Object.assign(cuerpo, { banco: v.banco || undefined, celular: v.celular })
    await api.post('metodos-pago', cuerpo)
    toast.ok('Método de pago registrado.'); f.reiniciar(); onHecho()
  })
  return (
    <form className="card-plana mb-4" onSubmit={enviar} noValidate key={tipo}>
      <h3 className="mb-2 font-bold">Registrar método de pago</h3>
      <div className="grid gap-3 sm:grid-cols-2">
        <Campo etiqueta="Tipo" valor={tipo} onChange={(v) => { setTipo(v); f.reiniciar() }} opciones={TIPOS} />
        <Campo etiqueta="Alias (cómo lo reconocerá)" requerido placeholder="Mi tarjeta personal" {...f.campo('alias')} />
        {tipo === 'TARJETA' && (<>
          <Campo etiqueta="Titular" requerido autoComplete="cc-name" {...f.campo('titular')} />
          <Campo etiqueta="Número de tarjeta" requerido inputMode="numeric" autoComplete="cc-number" placeholder="4111 1111 1111 1111" {...f.campo('numero')} />
          <Campo etiqueta="Vencimiento (MM/AA)" requerido placeholder="08/29" maxLength={5} autoComplete="cc-exp" {...f.campo('vencimiento')} />
          <Campo etiqueta="CVV" tipo="password" requerido inputMode="numeric" maxLength={4} autoComplete="off" ayuda="No se guarda en el sistema." {...f.campo('cvv')} />
        </>)}
        {tipo === 'TRANSFERENCIA' && (<>
          <Campo etiqueta="Banco" requerido {...f.campo('banco')} /><Campo etiqueta="N° de cuenta" requerido inputMode="numeric" {...f.campo('cuenta')} /><Campo etiqueta="Titular de la cuenta" {...f.campo('titular')} />
        </>)}
        {tipo === 'BILLETERA' && (<><Campo etiqueta="Billetera (opcional)" placeholder="Tigo Money" {...f.campo('banco')} /><Campo etiqueta="N° de celular" requerido inputMode="tel" maxLength={8} {...f.campo('celular')} /></>)}
        {tipo === 'QR' && <p className="aviso sm:col-span-2">Se generará un cobro por código QR simulado al momento de pagar.</p>}
      </div>
      <label className="mt-3 flex items-center gap-2 text-sm"><input type="checkbox" checked={f.valores.predeterminado} onChange={(e) => f.cambiar('predeterminado', e.target.checked)} /> Usar como método predeterminado</label>
      {tipo === 'TARJETA' && <p className="aviso peq mt-2">Tarjetas de prueba: <span className="mono">4111 1111 1111 1111</span> (aprobada) · <span className="mono">4000 0000 0000 0002</span> (rechazada) · <span className="mono">4000 0000 0000 9995</span> (fondos insuficientes). Se guarda solo la marca y los últimos 4 dígitos.</p>}
      <div className="mt-3 flex justify-end"><button className="btn" disabled={f.enviando}>Guardar método</button></div>
    </form>
  )
}

export default function Pagos() {
  const toast = useToast()
  const [tab, setTab] = useState('pagar')
  const [conceptos, setConceptos] = useState([])
  const [metodos, setMetodos] = useState([])
  const [pagos, setPagos] = useState([])
  const [planes, setPlanes] = useState([])
  const [solicitud, setSolicitud] = useState(null)
  const [comprobante, setComprobante] = useState(null)
  const [nuevoMetodo, setNuevoMetodo] = useState(false)
  const [borrar, setBorrar] = useState(null)
  const [cancelar, setCancelar] = useState(null)
  const [planNuevo, setPlanNuevo] = useState({ id_concepto: '', num_cuotas: 2 })

  const cargar = useCallback(() => Promise.all([
    api.get('conceptos-pago').then(setConceptos), api.get('metodos-pago').then(setMetodos), api.get('pagos').then(setPagos), api.get('planes-pago').then(setPlanes),
  ]), [])
  useEffect(() => { cargar() }, [cargar])

  const resultado = (p) => {
    if (p.estado === 'APROBADO') toast.ok(`Pago aprobado. Referencia ${p.referencia}.`)
    else toast.error(`Pago rechazado: ${(p.motivo_rechazo ?? 'la pasarela no autorizó la operación').replace(/\.+$/, '')}.`)
    setSolicitud(null); cargar(); setComprobante(p.id_pago)
  }
  const pagarConcepto = (c) => setSolicitud({ titulo: `Pagar: ${c.nombre}`, monto: c.monto, accion: async (d) => resultado(await api.post('pagos', { id_concepto: c.id_concepto, ...d })) })
  const pagarCuota = (p, c) => setSolicitud({ titulo: `Cuota ${c.numero}/${p.num_cuotas} · ${p.concepto?.nombre}`, monto: c.monto, accion: async (d) => resultado(await api.post(`cuotas/${c.id_cuota}/pagar`, d)) })

  const crearPlan = async (e) => {
    e.preventDefault()
    const c = conceptos.find((x) => String(x.id_concepto) === String(planNuevo.id_concepto))
    if (!c) { toast.error('Seleccione un concepto para el plan.'); return }
    if (planNuevo.num_cuotas < 2 || planNuevo.num_cuotas > c.max_cuotas) { toast.error(`El número de cuotas debe estar entre 2 y ${c.max_cuotas}.`); return }
    await api.post('planes-pago', { id_concepto: c.id_concepto, num_cuotas: Number(planNuevo.num_cuotas) })
    toast.ok('Plan de pagos creado.'); setPlanNuevo({ id_concepto: '', num_cuotas: 2 }); cargar()
  }
  const conPlan = conceptos.filter((c) => c.permite_plan)
  const conceptoPlan = conPlan.find((c) => String(c.id_concepto) === String(planNuevo.id_concepto))

  return (
    <div>
      <Titulo icono={Banknote} subtitulo="Pagos electrónicos de aranceles y trámites del Consejo Facultativo (pasarela simulada).">Mis pagos</Titulo>
      <Tabs valor={tab} onCambio={setTab} opciones={[{ id: 'pagar', texto: 'Pagar' }, { id: 'metodos', texto: 'Métodos de pago' }, { id: 'planes', texto: 'Planes de pago' }, { id: 'historial', texto: 'Historial' }]} />

      {tab === 'pagar' && (
        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
          {conceptos.map((c) => (
            <section key={c.id_concepto} className="card flex flex-col" aria-label={c.nombre}>
              <h2 className="font-bold">{c.nombre}</h2><p className="suave peq flex-1">{c.descripcion}</p>
              <p className="my-2 text-2xl font-bold text-primary">{bs(c.monto)}</p>
              <div className="flex gap-2"><button className="btn" onClick={() => pagarConcepto(c)}>Pagar ahora</button>
                {c.permite_plan && <button className="btn btn-sec" onClick={() => { setPlanNuevo({ id_concepto: String(c.id_concepto), num_cuotas: 2 }); setTab('planes') }}>En cuotas (hasta {c.max_cuotas})</button>}</div>
            </section>
          ))}
          {conceptos.length === 0 && <Vacio>No hay conceptos de pago disponibles.</Vacio>}
        </div>
      )}

      {tab === 'metodos' && (
        <div className="card">
          <div className="mb-3 flex justify-end"><button className="btn" onClick={() => setNuevoMetodo((n) => !n)}>{nuevoMetodo ? 'Cerrar formulario' : 'Nuevo método de pago'}</button></div>
          {nuevoMetodo && <FormMetodo onHecho={() => { setNuevoMetodo(false); cargar() }} />}
          {metodos.length === 0 && <Vacio>No tiene métodos de pago registrados.</Vacio>}
          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            {metodos.map((m) => {
              const I = ICONO_TIPO[m.tipo] ?? CreditCard
              return (
                <div key={m.id_metodo} className="tarjeta-visual">
                  <div className="flex items-center justify-between"><I size={26} aria-hidden="true" />{m.predeterminado && <span className="badge !bg-white/25 !text-inherit"><Star size={12} aria-hidden="true" />&nbsp;Predeterminado</span>}</div>
                  <p className="mt-3 font-bold">{m.alias}</p><p className="mono text-sm">{m.descripcion}</p>{m.vencida && <p className="peq font-bold">Vencida</p>}
                  <div className="mt-3 flex gap-2">
                    {!m.predeterminado && <button className="btn btn-mini !border-current !bg-white/20 !text-inherit" onClick={async () => { await api.post(`metodos-pago/${m.id_metodo}/predeterminado`); cargar() }}>Predeterminar</button>}
                    <button className="btn btn-mini !border-current !bg-white/20 !text-inherit" onClick={() => setBorrar(m)} aria-label={`Eliminar ${m.alias}`}><Trash2 size={14} aria-hidden="true" /></button>
                  </div>
                </div>
              )
            })}
          </div>
        </div>
      )}

      {tab === 'planes' && (
        <div className="grid gap-4">
          <form className="card" onSubmit={crearPlan}>
            <h2 className="mb-2 font-bold">Crear plan de pagos en cuotas</h2>
            <div className="flex flex-wrap items-end gap-3">
              <div className="min-w-64 flex-1"><Campo etiqueta="Concepto" valor={planNuevo.id_concepto} onChange={(v) => setPlanNuevo({ ...planNuevo, id_concepto: v })} opciones={[{ valor: '', texto: '— Seleccione —' }, ...conPlan.map((c) => ({ valor: String(c.id_concepto), texto: `${c.nombre} — ${bs(c.monto)}` }))]} /></div>
              <div className="w-36"><Campo etiqueta={`N° de cuotas${conceptoPlan ? ` (2-${conceptoPlan.max_cuotas})` : ''}`} tipo="number" min="2" max={conceptoPlan?.max_cuotas ?? 12} valor={planNuevo.num_cuotas} onChange={(v) => setPlanNuevo({ ...planNuevo, num_cuotas: v })} /></div>
              {conceptoPlan && planNuevo.num_cuotas >= 2 && <p className="suave peq pb-2">≈ {bs(conceptoPlan.monto / planNuevo.num_cuotas)} por cuota, cada 30 días.</p>}
              <button className="btn">Crear plan</button>
            </div>
          </form>
          {planes.length === 0 && <div className="card"><Vacio>No tiene planes de pago.</Vacio></div>}
          {planes.map((p) => (
            <section key={p.id_plan} className="card" aria-label={`Plan ${p.id_plan}`}>
              <div className="mb-2 flex flex-wrap items-center gap-2"><h2 className="flex-1 font-bold">Plan #{p.id_plan} · {p.concepto?.nombre}</h2><Insignia estado={p.estado} />
                {p.estado === 'ACTIVO' && <button className="btn btn-sec btn-mini" onClick={() => setCancelar(p)}>Cancelar plan</button>}</div>
              <p className="suave peq mb-2">Total {bs(p.monto_total)} · pagado {bs(p.pagado)} · <strong>saldo pendiente {bs(Math.max(0, p.monto_total - p.pagado))}</strong> · {p.num_cuotas} cuotas</p>
              <div className="barra mb-2" role="progressbar" aria-valuemin={0} aria-valuemax={100} aria-valuenow={Math.round((p.pagado * 100) / (p.monto_total || 1))}><i style={{ width: `${Math.min(100, (p.pagado * 100) / (p.monto_total || 1))}%` }} /></div>
              <Tabla clave="id_cuota" filas={p.cuotas}
                columnas={[
                  { titulo: 'Cuota', celda: (c) => `${c.numero}/${p.num_cuotas}` }, { titulo: 'Monto', celda: (c) => bs(c.monto) },
                  { titulo: 'Vence', celda: (c) => String(c.fecha_vencimiento).slice(0, 10) },
                  { titulo: 'Estado', celda: (c) => <><Insignia estado={c.estado} />{c.vencida && <span className="badge b-err ml-1">VENCIDA</span>}</> },
                  { titulo: 'Acciones', celda: (c) => p.estado === 'ACTIVO' && c.estado === 'PENDIENTE' && <button className="btn btn-mini" onClick={() => pagarCuota(p, c)}>Pagar cuota</button> },
                ]} />
            </section>
          ))}
        </div>
      )}

      {tab === 'historial' && (
        <div className="card">
          <Tabla clave="id_pago" filas={pagos} vacio="Aún no realizó pagos."
            columnas={[
              { titulo: 'Fecha', celda: (p) => fechaHora(p.created_at) }, { titulo: 'Referencia', celda: (p) => <span className="mono">{p.referencia}</span> },
              { titulo: 'Concepto', celda: (p) => p.concepto?.nombre }, { titulo: 'Tipo', campo: 'tipo' }, { titulo: 'Monto', celda: (p) => bs(p.monto) },
              { titulo: 'Estado', celda: (p) => <Insignia estado={p.estado} /> },
              { titulo: 'Comprobante', celda: (p) => <button className="btn btn-sec btn-mini" onClick={() => setComprobante(p.id_pago)}><Receipt size={14} aria-hidden="true" />Ver</button> },
            ]} />
        </div>
      )}

      {solicitud && <SolicitarPago titulo={solicitud.titulo} monto={solicitud.monto} metodos={metodos} onCerrar={() => setSolicitud(null)} onPagar={solicitud.accion} />}
      {comprobante && <Comprobante id={comprobante} onCerrar={() => setComprobante(null)} />}
      {borrar && <Confirmar peligro textoSi="Eliminar" mensaje={`¿Eliminar el método «${borrar.alias}»?`} onNo={() => setBorrar(null)} onSi={async () => { await api.delete(`metodos-pago/${borrar.id_metodo}`); toast.ok('Método eliminado.'); setBorrar(null); cargar() }} />}
      {cancelar && <Confirmar peligro textoSi="Cancelar plan" mensaje="¿Cancelar este plan? Las cuotas pendientes quedarán canceladas." onNo={() => setCancelar(null)} onSi={async () => { await api.post(`planes-pago/${cancelar.id_plan}/cancelar`); toast.ok('Plan cancelado.'); setCancelar(null); cargar() }} />}
    </div>
  )
}
