import {
  cilSpeedometer,
  cilBuilding,
  cilFile,
  cilDescription,
  cilMoney,
  cilCalendarCheck,
  cilSettings,
} from '@coreui/icons'

export type NavEntry =
  | { type: 'title'; name: string }
  | { type: 'item'; name: string; to: string; icon: string[] }

/** Navegación del back-office. Inicio y Empresas ya tienen pantalla; el resto va a
 * páginas "en construcción" hasta que se implementen sus módulos. */
export const navigation: NavEntry[] = [
  { type: 'item', name: 'Inicio', to: '/', icon: cilSpeedometer },
  { type: 'item', name: 'Empresas / Contribuyentes', to: '/empresas', icon: cilBuilding },
  { type: 'title', name: 'IVA' },
  { type: 'item', name: 'Comprobantes', to: '/iva', icon: cilFile },
  { type: 'item', name: 'Libro IVA y DDJJ', to: '/iva/libro', icon: cilDescription },
  { type: 'item', name: 'Factura electrónica', to: '/afip', icon: cilFile },
  { type: 'title', name: 'Estudio' },
  { type: 'item', name: 'Sueldos', to: '/sueldos', icon: cilMoney },
  { type: 'item', name: 'Vencimientos y tareas', to: '/gestion', icon: cilCalendarCheck },
  { type: 'item', name: 'Administración', to: '/admin', icon: cilSettings },
]
