import {
  cilSpeedometer,
  cilBuilding,
  cilFile,
  cilMoney,
  cilCalendarCheck,
  cilSettings,
} from '@coreui/icons'

export type NavEntry =
  | { type: 'title'; name: string }
  | { type: 'item'; name: string; to: string; icon: string[] }

/**
 * Navegación del back-office. Solo se listan pantallas reales (no placeholders).
 * El IVA (clientes/proveedores, ventas/compras, libro IVA y DDJJ) se opera desde
 * **Empresas → Períodos → Ventas/Compras/Libro IVA**: por eso no hay un ítem
 * "Comprobantes" suelto (dependen de empresa + período). Gestión del estudio y
 * Administración quedan como "en construcción" hasta implementarlas.
 */
export const navigation: NavEntry[] = [
  { type: 'item', name: 'Inicio', to: '/', icon: cilSpeedometer },
  { type: 'title', name: 'Contable / IVA' },
  { type: 'item', name: 'Empresas / Contribuyentes', to: '/empresas', icon: cilBuilding },
  { type: 'item', name: 'Factura electrónica (AFIP)', to: '/afip', icon: cilFile },
  { type: 'title', name: 'Estudio' },
  { type: 'item', name: 'Sueldos', to: '/sueldos', icon: cilMoney },
  { type: 'item', name: 'Vencimientos y tareas', to: '/gestion', icon: cilCalendarCheck },
  { type: 'item', name: 'Administración', to: '/admin', icon: cilSettings },
]
