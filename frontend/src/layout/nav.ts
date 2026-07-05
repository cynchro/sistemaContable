import {
  cilSpeedometer,
  cilBuilding,
  cilCalendar,
  cilDollar,
  cilCart,
  cilPeople,
  cilUser,
  cilList,
  cilBook,
  cilLibrary,
  cilStorage,
  cilCloudUpload,
  cilFile,
  cilMoney,
  cilCalendarCheck,
  cilSettings,
} from '@coreui/icons'

export type NavEntry =
  | { type: 'title'; name: string }
  | {
      type: 'item'
      name: string
      to: string
      icon: string[]
      /** Deshabilitado cuando falta contexto activo (empresa o período). */
      disabled?: boolean
      /** Tooltip explicando qué falta para habilitarlo. */
      hint?: string
    }

/**
 * Navegación del back-office, agrupada IVA / Estudio / Administración. Los ítems
 * de IVA que dependen de una empresa o un período se resuelven contra el contexto
 * activo (elegido en el header): cuando falta el prerrequisito quedan deshabilitados
 * con un hint, replicando el flujo del Visual IVA (primero se activa empresa+período,
 * después se opera). Las rutas siguen aceptando IDs por URL para deep-links.
 */
export function buildNavigation(empresaId: number | null, periodoId: number | null): NavEntry[] {
  const emp = empresaId ?? 0
  const per = periodoId ?? 0
  const needEmpresa = !empresaId
  const needPeriodo = !empresaId || !periodoId
  const hintEmpresa = 'Elegí una empresa activa en el header'
  const hintPeriodo = 'Elegí empresa y período activos en el header'

  return [
    { type: 'item', name: 'Inicio', to: '/', icon: cilSpeedometer },

    { type: 'title', name: 'IVA' },
    { type: 'item', name: 'Empresas / Contribuyentes', to: '/empresas', icon: cilBuilding },
    {
      type: 'item',
      name: 'Períodos',
      to: `/empresas/${emp}/periodos`,
      icon: cilCalendar,
      disabled: needEmpresa,
      hint: hintEmpresa,
    },
    {
      type: 'item',
      name: 'Ventas',
      to: `/empresas/${emp}/periodos/${per}/ventas`,
      icon: cilDollar,
      disabled: needPeriodo,
      hint: hintPeriodo,
    },
    {
      type: 'item',
      name: 'Compras',
      to: `/empresas/${emp}/periodos/${per}/compras`,
      icon: cilCart,
      disabled: needPeriodo,
      hint: hintPeriodo,
    },
    {
      type: 'item',
      name: 'Clientes',
      to: `/empresas/${emp}/clientes`,
      icon: cilPeople,
      disabled: needEmpresa,
      hint: hintEmpresa,
    },
    {
      type: 'item',
      name: 'Proveedores',
      to: `/empresas/${emp}/proveedores`,
      icon: cilUser,
      disabled: needEmpresa,
      hint: hintEmpresa,
    },
    {
      type: 'item',
      name: 'Cuentas',
      to: `/empresas/${emp}/cuentas`,
      icon: cilLibrary,
      disabled: needEmpresa,
      hint: hintEmpresa,
    },
    {
      type: 'item',
      name: 'Actividades',
      to: `/empresas/${emp}/actividades`,
      icon: cilList,
      disabled: needEmpresa,
      hint: hintEmpresa,
    },
    {
      type: 'item',
      name: 'Libro IVA / DDJJ',
      to: `/empresas/${emp}/periodos/${per}/libro-iva`,
      icon: cilBook,
      disabled: needPeriodo,
      hint: hintPeriodo,
    },
    {
      type: 'item',
      name: 'Importar comprobantes',
      to: `/empresas/${emp}/periodos/${per}/importar`,
      icon: cilCloudUpload,
      disabled: needPeriodo,
      hint: hintPeriodo,
    },
    { type: 'item', name: 'Factura electrónica (AFIP)', to: '/afip', icon: cilFile },

    { type: 'title', name: 'Estudio' },
    { type: 'item', name: 'Sueldos', to: '/sueldos', icon: cilMoney },
    { type: 'item', name: 'Vencimientos y tareas', to: '/gestion', icon: cilCalendarCheck },

    { type: 'title', name: 'Administración' },
    { type: 'item', name: 'Administración', to: '/admin', icon: cilSettings },
    { type: 'item', name: 'Utilidades', to: '/utilidades', icon: cilStorage },
  ]
}
