export const fechaHora = (s) => (s ? String(s).replace('T', ' ').slice(0, 16) : '-')
export const fechaCorta = (s) => (s ? String(s).slice(0, 10) : '-')
export const bs = (n) => `Bs ${Number(n ?? 0).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
export const rolLegible = (r) => (r ? r.replaceAll('_', ' ') : '')
export const detalleJson = (d) => (d ? (typeof d === 'string' ? d : JSON.stringify(d)) : '')
/** Valor para <input type="datetime-local"> en formato que espera Laravel. */
export const paraServidor = (dt) => (dt ? dt.replace('T', ' ') : dt)
