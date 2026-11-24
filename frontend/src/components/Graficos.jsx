import { useState } from 'react'
import { Bar, BarChart, CartesianGrid, Cell, Legend, Line, LineChart, Pie, PieChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts'
import { Table2, ChartColumn } from 'lucide-react'

export const COLORES = Array.from({ length: 8 }, (_, i) => `var(--c-chart-${i + 1})`)

const estiloTooltip = {
  contentStyle: { background: 'var(--c-surface)', border: '1px solid var(--c-border)', borderRadius: 8, color: 'var(--c-text)', fontSize: 13 },
  labelStyle: { color: 'var(--c-text)', fontWeight: 700 },
  itemStyle: { color: 'var(--c-text)' },
  cursor: { fill: 'var(--c-surface-2)', opacity: 0.6 },
}
const eje = { tick: { fontSize: 12 }, axisLine: { stroke: 'var(--c-border)' }, tickLine: false }

/** Tarjeta de gráfico con alternativa accesible: el botón «Ver tabla» muestra los mismos datos en formato tabular. */
export function TarjetaGrafico({ titulo, descripcion, datos, columnas, children, alto = 240 }) {
  const [tabla, setTabla] = useState(false)
  const vacio = !datos || datos.length === 0
  return (
    <section className="card" aria-label={titulo}>
      <div className="mb-2 flex items-start justify-between gap-2">
        <div><h2 className="font-bold">{titulo}</h2>{descripcion && <p className="suave peq">{descripcion}</p>}</div>
        {!vacio && (
          <button className="btn btn-sec btn-mini no-imprimir" onClick={() => setTabla((t) => !t)} aria-pressed={tabla}>
            {tabla ? <ChartColumn size={14} aria-hidden="true" /> : <Table2 size={14} aria-hidden="true" />}{tabla ? 'Ver gráfico' : 'Ver tabla'}
          </button>
        )}
      </div>
      {vacio ? <p className="suave p-6 text-center">Aún no hay datos para graficar.</p>
        : tabla ? (
          <div className="tabla-wrap" style={{ maxHeight: alto }}>
            <table className="tabla"><caption className="sr-only">{titulo}</caption>
              <thead><tr>{columnas.map((c) => <th key={c.campo} scope="col">{c.titulo}</th>)}</tr></thead>
              <tbody>{datos.map((d, i) => <tr key={i}>{columnas.map((c) => <td key={c.campo}>{d[c.campo]}</td>)}</tr>)}</tbody>
            </table>
          </div>
        ) : <div style={{ height: alto }} role="img" aria-label={`${titulo}. Use «Ver tabla» para consultar los valores.`}>{children}</div>}
    </section>
  )
}

/** Barras (una o varias series). series = [{ campo, nombre }] */
export function GraficoBarras({ datos, x, series, horizontal = false, apilado = false }) {
  const una = series.length === 1
  return (
    <ResponsiveContainer width="100%" height="100%">
      <BarChart data={datos} layout={horizontal ? 'vertical' : 'horizontal'} margin={{ top: 8, right: 8, left: horizontal ? 40 : -12, bottom: 0 }} barCategoryGap="28%">
        <CartesianGrid vertical={horizontal} horizontal={!horizontal} />
        {horizontal ? <><XAxis type="number" {...eje} allowDecimals={false} /><YAxis type="category" dataKey={x} width={110} {...eje} /></>
          : <><XAxis dataKey={x} {...eje} interval={datos.length <= 12 ? 0 : 'preserveStartEnd'} /><YAxis {...eje} allowDecimals={false} /></>}
        <Tooltip {...estiloTooltip} />
        {!una && <Legend wrapperStyle={{ fontSize: 12 }} />}
        {series.map((s, i) => (
          <Bar key={s.campo} dataKey={s.campo} name={s.nombre} fill={COLORES[i]} stackId={apilado ? 'a' : undefined}
            radius={apilado ? 0 : horizontal ? [0, 4, 4, 0] : [4, 4, 0, 0]} maxBarSize={36} stroke="var(--c-surface)" strokeWidth={apilado ? 2 : 0} />
        ))}
      </BarChart>
    </ResponsiveContainer>
  )
}

/** Líneas (una o varias series). */
export function GraficoLineas({ datos, x, series }) {
  return (
    <ResponsiveContainer width="100%" height="100%">
      <LineChart data={datos} margin={{ top: 8, right: 8, left: -12, bottom: 0 }}>
        <CartesianGrid vertical={false} />
        <XAxis dataKey={x} {...eje} interval="preserveStartEnd" tickFormatter={(v) => (/^\d{4}-\d{2}-\d{2}$/.test(v) ? v.slice(5) : v)} />
        <YAxis {...eje} allowDecimals={false} />
        <Tooltip {...estiloTooltip} cursor={{ stroke: 'var(--c-border)' }} />
        {series.length > 1 && <Legend wrapperStyle={{ fontSize: 12 }} />}
        {series.map((s, i) => (
          <Line key={s.campo} type="monotone" dataKey={s.campo} name={s.nombre} stroke={COLORES[i]} strokeWidth={2} dot={{ r: 3, stroke: 'var(--c-surface)', strokeWidth: 2 }} activeDot={{ r: 5 }} />
        ))}
      </LineChart>
    </ResponsiveContainer>
  )
}

/** Dona con leyenda (la identidad nunca depende solo del color). */
export function GraficoDona({ datos, nombre, valor }) {
  return (
    <ResponsiveContainer width="100%" height="100%">
      <PieChart>
        <Pie data={datos} dataKey={valor} nameKey={nombre} innerRadius="52%" outerRadius="80%" paddingAngle={2} stroke="var(--c-surface)" strokeWidth={2}>
          {datos.map((d, i) => <Cell key={d[nombre]} fill={COLORES[i % COLORES.length]} />)}
        </Pie>
        <Tooltip {...estiloTooltip} />
        <Legend wrapperStyle={{ fontSize: 12 }} />
      </PieChart>
    </ResponsiveContainer>
  )
}
