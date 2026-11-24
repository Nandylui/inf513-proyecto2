import {
  Home, Landmark, Users, UserCog, Calendar, CalendarDays, FileText, Vote, Scale, ShieldCheck, ChartBar, CreditCard, Wallet,
  Lock, KeyRound, List, Mail, Gavel, ClipboardList, Activity, Folder, Circle, Eye, Banknote, Shield,
} from 'lucide-react'

/** Las opciones de menú vienen de la base de datos con una clave de icono; aquí se resuelve a un componente. */
export const ICONOS = {
  inicio: Home, consejo: Landmark, usuarios: UserCog, miembros: Users, sesiones: CalendarDays, calendario: Calendar,
  documentos: FileText, votacion: Vote, resoluciones: Gavel, fiscalizacion: Eye, reportes: ChartBar, pagos: Banknote,
  tarjeta: CreditCard, billetera: Wallet, seguridad: Shield, bitacora: ClipboardList, llave: KeyRound, lista: List,
  correo: Mail, escudo: ShieldCheck, balanza: Scale, candado: Lock, actividad: Activity, carpeta: Folder,
}
export const iconoDe = (clave) => ICONOS[clave] || Circle
