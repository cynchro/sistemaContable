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
  cilCalculator,
  cilBriefcase,
  cilShieldAlt,
  cilChartPie,
  cilFindInPage,
} from '@coreui/icons'

/** Ítem de menú (hoja). Puede depender del contexto activo (empresa/período). */
export interface NavItem {
  name: string
  to: string
  icon: string[]
  /** Deshabilitado cuando falta contexto activo (empresa o período). */
  disabled?: boolean
  /** Tooltip explicando qué falta para habilitarlo. */
  hint?: string
}

export type NavEntry =
  | ({ type: 'item' } & NavItem)
  /** Menú desplegable: agrupa ítems de un módulo bajo un padre colapsable. */
  | { type: 'group'; name: string; icon: string[]; items: NavItem[] }

/**
 * Navegación del back-office como menús desplegables por módulo (IVA / Estudio /
 * Administración). Los ítems de IVA que dependen de una empresa o un período se
 * resuelven contra el contexto activo (elegido en el header): cuando falta el
 * prerrequisito quedan deshabilitados con un hint, replicando el flujo del Visual
 * IVA (primero se activa empresa+período, después se opera). Las rutas siguen
 * aceptando IDs por URL para deep-links.
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

    {
      type: 'group',
      name: 'IVA',
      icon: cilCalculator,
      items: [
        { name: 'Empresas / Contribuyentes', to: '/empresas', icon: cilBuilding },
        {
          name: 'Períodos',
          to: `/empresas/${emp}/periodos`,
          icon: cilCalendar,
          disabled: needEmpresa,
          hint: hintEmpresa,
        },
        {
          name: 'Ventas',
          to: `/empresas/${emp}/periodos/${per}/ventas`,
          icon: cilDollar,
          disabled: needPeriodo,
          hint: hintPeriodo,
        },
        {
          name: 'Compras',
          to: `/empresas/${emp}/periodos/${per}/compras`,
          icon: cilCart,
          disabled: needPeriodo,
          hint: hintPeriodo,
        },
        {
          name: 'Clientes',
          to: `/empresas/${emp}/clientes`,
          icon: cilPeople,
          disabled: needEmpresa,
          hint: hintEmpresa,
        },
        {
          name: 'Proveedores',
          to: `/empresas/${emp}/proveedores`,
          icon: cilUser,
          disabled: needEmpresa,
          hint: hintEmpresa,
        },
        {
          name: 'Cuentas',
          to: `/empresas/${emp}/cuentas`,
          icon: cilLibrary,
          disabled: needEmpresa,
          hint: hintEmpresa,
        },
        {
          name: 'Actividades',
          to: `/empresas/${emp}/actividades`,
          icon: cilList,
          disabled: needEmpresa,
          hint: hintEmpresa,
        },
        {
          name: 'Libro IVA / DDJJ',
          to: `/empresas/${emp}/periodos/${per}/libro-iva`,
          icon: cilBook,
          disabled: needPeriodo,
          hint: hintPeriodo,
        },
        {
          name: 'Reportes de Mayor',
          to: `/empresas/${emp}/reportes-mayor`,
          icon: cilBook,
          disabled: needEmpresa,
          hint: hintEmpresa,
        },
        {
          name: 'Auditoría ARCA',
          to: `/empresas/${emp}/auditoria-afip`,
          icon: cilFindInPage,
          disabled: needEmpresa,
          hint: hintEmpresa,
        },
        {
          name: 'Importar comprobantes',
          to: `/empresas/${emp}/periodos/${per}/importar`,
          icon: cilCloudUpload,
          disabled: needPeriodo,
          hint: hintPeriodo,
        },
        { name: 'Factura electrónica (AFIP)', to: '/afip', icon: cilFile },
        {
          name: 'Panel',
          to: `/empresas/${emp}/periodos/${per}/panel`,
          icon: cilChartPie,
          disabled: needPeriodo,
          hint: hintPeriodo,
        },
      ],
    },

    {
      type: 'group',
      name: 'Estudio',
      icon: cilBriefcase,
      items: [
        { name: 'Sueldos', to: '/sueldos', icon: cilMoney },
        { name: 'Vencimientos y tareas', to: '/gestion', icon: cilCalendarCheck },
      ],
    },

    {
      type: 'group',
      name: 'Administración',
      icon: cilShieldAlt,
      items: [
        { name: 'Administración', to: '/admin', icon: cilSettings },
        { name: 'Utilidades', to: '/utilidades', icon: cilStorage },
      ],
    },

    { type: 'item', name: 'Manuales', to: '/manuales', icon: cilBook },
  ]
}
