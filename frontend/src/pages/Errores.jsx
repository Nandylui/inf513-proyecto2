import { Link } from 'react-router-dom'
import { Lock, SearchX } from 'lucide-react'

export function NoAutorizado({ permiso }) {
  return (
    <div className="card mx-auto mt-10 max-w-lg text-center">
      <Lock size={40} className="mx-auto text-err" aria-hidden="true" />
      <h1 className="mt-2 text-xl font-bold">Acceso restringido</h1>
      <p className="suave mt-1">Su perfil no tiene el permiso necesario para esta pantalla{permiso ? ` (${permiso})` : ''}. La matriz de acceso la administra el administrador del sistema.</p>
      <Link to="/panel" className="btn mt-4">Volver al inicio</Link>
    </div>
  )
}

export function NoEncontrado() {
  return (
    <div className="card mx-auto mt-10 max-w-lg text-center">
      <SearchX size={40} className="mx-auto text-muted" aria-hidden="true" />
      <h1 className="mt-2 text-xl font-bold">Página no encontrada</h1>
      <p className="suave mt-1">La dirección solicitada no existe en el sistema.</p>
      <Link to="/panel" className="btn mt-4">Volver al inicio</Link>
    </div>
  )
}
