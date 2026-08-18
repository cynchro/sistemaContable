import type { DriveStep } from 'driver.js'

/**
 * Pasos de cada recorrido guiado. Los `element` referencian ids agregados a propósito en
 * los componentes (`tour-*`), separados del resto de las clases/ids de estilo para que no
 * se rompan si cambia el diseño visual.
 */

export const tourNavegacion: DriveStep[] = [
  {
    popover: {
      title: 'Mapa del sistema',
      description:
        'Un repaso rápido de qué hace cada menú y en qué orden se usa. Podés cerrarlo cuando quieras, y volver ' +
        'a verlo desde el ícono de ayuda (🧭) del encabezado.',
    },
  },
  {
    element: '#tour-empresa-selector',
    popover: {
      title: 'Empresa activa',
      description:
        'Elegí acá el contribuyente con el que vas a trabajar. Queda guardado y se mantiene al recargar la página.',
      side: 'bottom',
      align: 'start',
    },
  },
  {
    element: '#tour-periodo-selector',
    popover: {
      title: 'Período activo',
      description:
        'Después elegí el período (mes). Buena parte del menú — Ventas, Compras, Libro IVA — se habilita recién ' +
        'cuando tenés un período activo.',
      side: 'bottom',
      align: 'start',
    },
  },
  {
    element: '#tour-sidebar-iva',
    popover: {
      title: 'Menú IVA — el circuito principal',
      description:
        'Orden recomendado: Empresas/Contribuyentes → Períodos → Ventas/Compras → Libro IVA/DDJJ → Descargas. ' +
        'El resto (Padrón de proveedores, Padrón de clientes, Alertas, Auditoría ARCA, Importar, Factura ' +
        'electrónica) son herramientas de apoyo a ese circuito, se usan cuando hacen falta.',
      side: 'right',
      align: 'start',
    },
  },
  {
    element: '#tour-sidebar-contabilidad',
    popover: {
      title: 'Menú Contabilidad',
      description:
        'Cuentas (plan de cuentas por empresa) y Reportes de Mayor (mayorización de compras y ventas, por ' +
        'rango de fechas) — separado de IVA, porque nace de la contabilidad aunque el motor lo alimente al ' +
        'cargar cada comprobante.',
      side: 'right',
      align: 'start',
    },
  },
  {
    element: '#tour-sidebar-estudio',
    popover: {
      title: 'Menú Estudio',
      description:
        'Aparte del circuito de IVA: Sueldos (liquidación de sueldos) y Tareas y honorarios (workflow del ' +
        'estudio). No dependen de la empresa/período activos.',
      side: 'right',
      align: 'start',
    },
  },
  {
    element: '#tour-sidebar-admin',
    popover: {
      title: 'Menú Administración',
      description:
        'Configuración del estudio: Utilidades (catálogos, rubros, retenciones, conceptos y auditoría de ' +
        'operaciones). Roles y permisos de usuarios se administran desde el SIGE.',
      side: 'right',
      align: 'start',
    },
  },
  {
    element: '#tour-help-toggle',
    popover: {
      title: 'Activar o desactivar los recorridos',
      description:
        'Desde acá podés apagar estos recorridos guiados si no los querés ver más, o reiniciarlos para que ' +
        'vuelvan a aparecer solos la primera vez que entrás a cada pantalla. Cada pantalla tiene además su ' +
        'propio "Ver recorrido", y en Manuales está el detalle completo de cada función.',
      side: 'bottom',
      align: 'end',
    },
  },
]

export const tourVentas: DriveStep[] = [
  {
    popover: {
      title: 'Carga de comprobantes de venta',
      description: 'Un repaso rápido de esta pantalla.',
    },
  },
  {
    element: '#tour-nueva-venta',
    popover: {
      title: 'Nueva venta',
      description:
        'Da de alta un comprobante: cabecera (tipo, letra, punto de venta, número, cliente) y la discriminación ' +
        'de IVA por alícuota — el sistema calcula el IVA y el total solo.',
      side: 'bottom',
      align: 'end',
    },
  },
  {
    element: '#tour-filtros-ventas',
    popover: {
      title: 'Filtros y orden',
      description: 'Filtrá por fecha, letra, cliente o número, y elegí el orden del listado.',
      side: 'bottom',
      align: 'start',
    },
  },
  {
    element: '#tour-pendientes-ventas',
    popover: {
      title: 'Comprobantes sin cliente identificado',
      description:
        'Si un comprobante importado no matcheó a ningún cliente del padrón (por CUIT), aparece acá con la ' +
        'cantidad pendiente. "Ver pendientes" despliega esas filas y "Asignar cliente" te deja resolverlo sin ' +
        'salir del listado.',
      side: 'bottom',
      align: 'start',
    },
    skipMissingElement: true,
  },
  {
    element: '#tour-tabla-ventas',
    popover: {
      title: 'Acciones por comprobante',
      description:
        'Desde cada fila podés editar, emitir el CAE (factura electrónica) si todavía no lo tiene, o eliminar ' +
        'el comprobante.',
      side: 'top',
      align: 'center',
    },
  },
]

export const tourEmpresas: DriveStep[] = [
  {
    popover: {
      title: 'Empresas / Contribuyentes',
      description: 'El ABM de los contribuyentes del estudio.',
    },
  },
  {
    element: '#tour-nueva-empresa',
    popover: {
      title: 'Nueva empresa',
      description:
        'Cargá el CUIT y tocá "Buscar": el sistema consulta el padrón de ARCA y completa nombre, domicilio y ' +
        'localidad automáticamente.',
      side: 'bottom',
      align: 'end',
    },
  },
  {
    element: '#tour-tabla-empresas',
    popover: {
      title: 'Abrir una empresa',
      description:
        'El botón "Abrir" te lleva directo a Períodos, Clientes, Proveedores o Actividades de esa empresa. ' +
        '"Editar" administra sus datos (contacto, socios, credenciales).',
      side: 'top',
      align: 'center',
    },
  },
]

export const tourPeriodos: DriveStep[] = [
  {
    popover: {
      title: 'Períodos',
      description: 'Un período es un mes de trabajo.',
    },
  },
  {
    element: '#tour-nuevo-periodo',
    popover: {
      title: 'Nuevo período',
      description: 'Se crea con su fecha de inicio y fin.',
      side: 'bottom',
      align: 'end',
    },
  },
  {
    element: '#tour-tabla-periodos',
    popover: {
      title: 'Trabajar el período',
      description:
        'Desde cada fila entrás directo a Ventas, Compras o Libro IVA de ese período. "Cerrar" lo pasa a solo ' +
        'lectura (protege la liquidación ya presentada); "Abrir" lo reabre si hace falta corregir algo.',
      side: 'top',
      align: 'center',
    },
  },
]

export function tourSujetos(esProveedor: boolean): DriveStep[] {
  const singular = esProveedor ? 'proveedor' : 'cliente'
  return [
    {
      popover: {
        title: esProveedor ? 'Proveedores' : 'Clientes',
        description:
          'El padrón es único por estudio: un mismo CUIT es siempre el mismo sujeto, compartido entre las ' +
          'empresas donde lo actives.',
      },
    },
    {
      element: '#tour-nuevo-sujeto',
      popover: {
        title: `Nuevo ${singular}`,
        description:
          `Si el CUIT ya existe en otra empresa del estudio, el sistema reutiliza sus datos y solo lo activa ` +
          'para esta — no hace falta cargarlo de nuevo. El botón AFIP autocompleta por CUIT desde el padrón.',
        side: 'bottom',
        align: 'end',
      },
    },
    {
      element: '#tour-busqueda-sujetos',
      popover: {
        title: 'Buscar',
        description: `Filtrá por nombre o CUIT, y ordená el listado.`,
        side: 'bottom',
        align: 'start',
      },
    },
    {
      element: '#tour-tabla-sujetos',
      popover: {
        title: 'Acciones',
        description: esProveedor
          ? '"Imputación" configura a qué cuenta contable se cargan por defecto sus compras (por punto de venta ' +
            'o en general), para no elegirla a mano en cada comprobante.'
          : '"Editar" y "Eliminar" administran los datos del cliente.',
        side: 'top',
        align: 'center',
      },
    },
  ]
}

export const tourCuentas: DriveStep[] = [
  {
    element: '#tour-nueva-cuenta',
    popover: {
      title: 'Plan de cuentas',
      description:
        'Cuentas de esta empresa (código + nombre). Se usan para la mayorización: al cargar un comprobante ' +
        'podés imputar el neto de cada línea a una cuenta, y eso alimenta los Reportes de Mayor.',
      side: 'bottom',
      align: 'end',
    },
  },
]

export const tourCompras: DriveStep[] = [
  {
    popover: {
      title: 'Carga de comprobantes de compra',
      description: 'Un repaso rápido de esta pantalla.',
    },
  },
  {
    element: '#tour-nueva-compra',
    popover: {
      title: 'Nueva compra',
      description:
        'El botón ▾ ofrece presets para los comprobantes manuales típicos (resumen bancario, ticket de ' +
        'combustible, cuota de préstamo, liquidación de tarjeta, servicio público, póliza de seguro): cada uno ' +
        'ya trae el tipo, la letra, el concepto y las alícuotas armadas.',
      side: 'bottom',
      align: 'end',
    },
  },
  {
    element: '#tour-filtros-compras',
    popover: {
      title: 'Filtros y orden',
      description: 'Filtrá por fecha, CUIT, proveedor o número, y elegí el orden del listado.',
      side: 'bottom',
      align: 'start',
    },
  },
  {
    element: '#tour-pendientes-compras',
    popover: {
      title: 'Comprobantes sin proveedor identificado',
      description:
        'Si un comprobante importado no matcheó a ningún proveedor del padrón (por CUIT), aparece acá. "Ver ' +
        'pendientes" despliega esas filas y "Asignar proveedor" te deja resolverlo sin salir del listado.',
      side: 'bottom',
      align: 'start',
    },
    skipMissingElement: true,
  },
  {
    element: '#tour-tabla-compras',
    popover: {
      title: 'Acciones por comprobante',
      description:
        'Cada fila permite editar o eliminar el comprobante. Al elegir el proveedor, si tiene una cuenta ' +
        'contable configurada, el sistema precarga esa cuenta en las líneas nuevas.',
      side: 'top',
      align: 'center',
    },
  },
]

export const tourActividades: DriveStep[] = [
  {
    popover: {
      title: 'Actividades',
      description:
        'Esta pantalla junta dos cosas distintas: la actividad NAES (para IIBB y la DJ IVA Simple por ' +
        'actividad) y la clasificación contable (para la mayorización). Un repaso rápido.',
    },
  },
  {
    element: '#tour-act-naes',
    popover: {
      title: 'Actividades (NAES)',
      description: 'Cargá los códigos NAES de la empresa. Definen IIBB y tasa municipal, no el IVA.',
      side: 'right',
      align: 'start',
    },
  },
  {
    element: '#tour-act-mapa-pv',
    popover: {
      title: 'Mapa de puntos de venta → actividad',
      description:
        'Regla general por punto de venta propio. Más abajo hay estrategias adicionales (por alícuota, por ' +
        'receptor, por porcentajes fijos) para cuando una empresa factura varias actividades mezcladas.',
      side: 'left',
      align: 'start',
    },
  },
  {
    element: '#tour-act-clasificacion',
    popover: {
      title: 'Clasificación de ventas por cuenta contable',
      description:
        'Distinto de lo de arriba: acá se resuelve la cuenta contable que se precarga en las líneas de una ' +
        'venta (mayorización), no la actividad de IIBB. Regla general por punto de venta + excepción por tipo ' +
        'de comprobante (por ejemplo, para separar notas de crédito).',
      side: 'top',
      align: 'start',
    },
  },
  {
    element: '#tour-act-conceptos',
    popover: {
      title: 'Mapeo de conceptos → cuentas',
      description:
        'Traduce el catálogo de conceptos del estudio (Utilidades › Conceptos) a una cuenta del plan de esta ' +
        'empresa. Lo usan las reglas de imputación de los proveedores (botón "Imputación" en Proveedores).',
      side: 'top',
      align: 'start',
    },
  },
]

export const tourImputacionProveedor: DriveStep[] = [
  {
    popover: {
      title: 'Imputación contable del proveedor',
      description:
        'Configura a qué cuenta se imputan las compras de este proveedor (mayorización), sin elegirla a mano ' +
        'en cada comprobante. Tres secciones, de más a menos específico.',
    },
  },
  {
    element: '#tour-imp-concepto',
    popover: {
      title: 'Concepto contable por defecto',
      description:
        'El proveedor tiene un concepto por defecto (configurado en su ficha, vale para todo el estudio). Acá ' +
        'se puede excepcionar solo para esta empresa. El concepto se traduce a una cuenta del plan de esta ' +
        'empresa en Actividades › "Mapeo de conceptos → cuentas".',
      side: 'bottom',
      align: 'start',
    },
  },
  {
    element: '#tour-imp-pv-global',
    popover: {
      title: 'Regla global por punto de venta',
      description:
        'Pisa el concepto por defecto. Vale para todas las empresas del estudio (el mismo proveedor puede ' +
        'facturar cosas distintas desde distintos puntos de venta).',
      side: 'top',
      align: 'start',
    },
  },
  {
    element: '#tour-imp-pv-excepcion',
    popover: {
      title: 'Excepción para esta empresa',
      description: 'Pisa la regla global de punto de venta, pero solo para la empresa activa.',
      side: 'top',
      align: 'start',
    },
  },
]

export const tourPadronUnico: DriveStep[] = [
  {
    popover: {
      title: 'Padrón único',
      description:
        'Todos los sujetos de este rol en el estudio, en una sola vista, sin filtrar por empresa. Un mismo ' +
        'CUIT aparece una sola vez, con las empresas donde está activado. El padrón de proveedores y el de ' +
        'clientes están separados — son dos mundos distintos, cada uno con su propia pantalla.',
    },
  },
  {
    element: '#tour-padron-buscar',
    popover: {
      title: 'Buscar',
      description: 'Por nombre o CUIT.',
      side: 'bottom',
      align: 'start',
    },
  },
  {
    element: '#tour-padron-tabla',
    popover: {
      title: '"Activo en"',
      description:
        'Cada badge te lleva directo al listado de esa empresa, con el CUIT precargado en la búsqueda. La ' +
        'edición se hace siempre desde ahí, no desde esta pantalla.',
      side: 'top',
      align: 'center',
    },
  },
]

export const tourAlertas: DriveStep[] = [
  {
    popover: {
      title: 'Alertas estadísticas',
      description:
        'Compara el último período de cada empresa contra el promedio de sus períodos anteriores (compras y ' +
        'ventas por separado, mínimo 3 períodos de historial).',
    },
  },
  {
    element: '#tour-alertas-checkbox',
    popover: {
      title: 'Filtro',
      description: 'Desmarcalo para ver también las empresas sin desvío, como referencia.',
      side: 'bottom',
      align: 'start',
    },
  },
  {
    element: '#tour-alertas-tabla',
    popover: {
      title: 'Desvío',
      description:
        'Un desvío por encima del 30% (v1, umbral por defecto) puede señalar la compra de un bien de uso u ' +
        'otro movimiento fuera de lo habitual que convenga revisar.',
      side: 'top',
      align: 'center',
    },
  },
]

export const tourAuditoriaAfip: DriveStep[] = [
  {
    popover: {
      title: 'Auditoría de ventas vs. ARCA',
      description:
        'Compara, para cada punto de venta + tipo + letra ya usados en la empresa, el último número que ARCA ' +
        'reconoce como autorizado contra el último que tenés cargado acá.',
    },
  },
  {
    element: '#tour-auditoria-boton',
    popover: {
      title: 'Consultar ARCA',
      description: 'Se consulta a demanda (no llama a ARCA solo al entrar a la pantalla).',
      side: 'bottom',
      align: 'end',
    },
  },
  {
    element: '#tour-auditoria-tabla',
    popover: {
      title: 'Faltantes',
      description:
        'Las combinaciones con faltantes quedan resaltadas. "Consultar" trae el detalle de un número puntual ' +
        'desde ARCA (fecha, importes, CAE) y, si no está cargado acá, "Cargar como venta" lo trae directo al ' +
        'período que corresponda por fecha.',
      side: 'top',
      align: 'center',
    },
    skipMissingElement: true,
  },
]

export const tourReporteMayor: DriveStep[] = [
  {
    popover: {
      title: 'Reportes de Mayor',
      description: 'Cruza compras y ventas de todos los períodos de la empresa, imputadas a cada cuenta.',
    },
  },
  {
    element: '#tour-mayor-filtros',
    popover: {
      title: 'Filtros',
      description: 'Rango de fechas, cuenta, provincia, origen (compras/ventas) o un CUIT puntual.',
      side: 'bottom',
      align: 'start',
    },
  },
  {
    element: '#tour-mayor-agrupar',
    popover: {
      title: 'Agrupar (cascada)',
      description:
        'Elegí cómo agrupar el resultado — por ejemplo "Provincia → Cuenta" para la DDJJ anual de convenio ' +
        'multilateral, o "Proveedor" para un pedido puntual.',
      side: 'bottom',
      align: 'start',
    },
  },
  {
    element: '#tour-mayor-resultado',
    popover: {
      title: 'Resultado',
      description:
        'Cada grupo es colapsable y muestra su subtotal; al llegar a los movimientos ves comprobante, sujeto y ' +
        'provincia. "Imprimir/PDF" exporta lo que estás viendo.',
      side: 'top',
      align: 'center',
    },
    skipMissingElement: true,
  },
]

export const tourImportar: DriveStep[] = [
  {
    popover: {
      title: 'Importar comprobantes',
      description:
        'Carga masiva desde un CSV (por ejemplo "Mis Comprobantes" de ARCA). Subí el archivo primero para que ' +
        'este recorrido te muestre el resto de las secciones.',
    },
  },
  {
    element: '#tour-import-archivo',
    popover: {
      title: 'Archivo CSV',
      description: 'Elegí si es de Ventas o Compras (arriba) y subí el CSV.',
      side: 'bottom',
      align: 'start',
    },
  },
  {
    element: '#tour-import-perfiles',
    popover: {
      title: 'Perfil de mapeo',
      description:
        'Una vez armado el mapeo de columnas de un origen (tu banco, tu punto de venta), guardalo como perfil ' +
        '— la próxima vez lo elegís acá sin volver a mapear. Se guardan en este navegador.',
      side: 'bottom',
      align: 'start',
    },
    skipMissingElement: true,
  },
  {
    element: '#tour-import-mapeo',
    popover: {
      title: 'Mapeo de columnas',
      description:
        'Asociá cada campo del sistema a una columna del CSV. Se auto-detecta por el nombre del encabezado; ' +
        'revisá y corregí lo que haga falta. Los campos con * son obligatorios.',
      side: 'top',
      align: 'start',
    },
    skipMissingElement: true,
  },
  {
    element: '#tour-import-extra',
    popover: {
      title: 'Percepciones y alícuotas adicionales',
      description:
        'Sumá columnas de percepción/retención (asociadas a un tipo del catálogo), o líneas de IVA extra para ' +
        'comprobantes con más de una alícuota por fila (ej. un resumen bancario con 21% y 10,5% en columnas ' +
        'separadas).',
      side: 'top',
      align: 'start',
    },
    skipMissingElement: true,
  },
  {
    element: '#tour-import-preview',
    popover: {
      title: 'Vista previa',
      description:
        'Cada fila se valida antes de importar: rojo = error (bloquea, salvo que tildes "omitir con error"), ' +
        'amarillo = aviso (se importa igual). Un resumen arriba de la tabla cuenta válidas/avisos/errores.',
      side: 'top',
      align: 'center',
    },
    skipMissingElement: true,
  },
]

export const tourPanel: DriveStep[] = [
  {
    popover: {
      title: 'Panel',
      description:
        'Tablero de indicadores del período activo: cantidades de comprobantes, débito/crédito de IVA y ' +
        'montos, con gráficos. Es una muestra, pensada como base para un tablero más completo.',
    },
  },
]

export const tourAfip: DriveStep[] = [
  {
    popover: {
      title: 'AFIP / Factura electrónica',
      description:
        'Requiere el certificado de ARCA configurado en el backend (homologación o producción); sin ' +
        'certificado válido vas a ver un error de autenticación.',
    },
  },
  {
    element: '#tour-afip-padron',
    popover: {
      title: 'Consulta de padrón',
      description:
        'Buscá por CUIT los datos de un contribuyente en ARCA. El mismo servicio autocompleta el alta de ' +
        'clientes/proveedores y de empresas por CUIT.',
      side: 'bottom',
      align: 'start',
    },
  },
  {
    element: '#tour-afip-puntos-venta',
    popover: {
      title: 'Puntos de venta',
      description: 'ABM de los puntos de venta de cada empresa — se usan al emitir el CAE de una venta.',
      side: 'top',
      align: 'start',
    },
  },
]

export const tourSueldos: DriveStep[] = [
  {
    popover: {
      title: 'Sueldos',
      description: 'Liquidación de sueldos: legajos, conceptos con fórmula, y liquidaciones/recibos.',
    },
  },
  {
    element: '#tour-sueldos-empresa',
    popover: {
      title: 'Empresa',
      description: 'Elegí la empresa cuyos sueldos vas a gestionar (selector propio de esta pantalla).',
      side: 'bottom',
      align: 'start',
    },
  },
  {
    element: '#tour-sueldos-tabs',
    popover: {
      title: 'Pestañas',
      description:
        'Legajos (ABM de empleados), Conceptos (con su fórmula de cálculo) y Liquidaciones (alta + liquidar: ' +
        'elegís empleado, cargás novedades y generás el recibo).',
      side: 'bottom',
      align: 'end',
    },
  },
]

export const tourGestion: DriveStep[] = [
  {
    popover: {
      title: 'Gestión del estudio',
      description: 'Workflow interno del estudio, no específico de una empresa.',
    },
  },
  {
    element: '#tour-gestion-tabs',
    popover: {
      title: 'Pestañas',
      description:
        'Tareas (workflow con estado y comentarios) y Honorarios (documentos de honorarios por ' +
        'servicio/complejidad). Los Vencimientos se manejan en el SIGE.',
      side: 'bottom',
      align: 'start',
    },
  },
]

export const tourAdmin: DriveStep[] = [
  {
    popover: {
      title: 'Administración',
      description: 'Roles, permisos y usuarios del estudio (RBAC).',
    },
  },
  {
    element: '#tour-admin-tabs',
    popover: {
      title: 'Pestañas',
      description:
        '"Roles y permisos" (alta de rol + asignar/quitar permisos puntuales), "Permisos" (catálogo) y ' +
        '"Usuarios" (listado con su rol — el alta de un usuario nuevo es por línea de comando).',
      side: 'bottom',
      align: 'start',
    },
  },
]

export const tourUtilidades: DriveStep[] = [
  {
    popover: {
      title: 'Utilidades',
      description: 'Catálogos y datos propios del estudio que usan las reglas de imputación y otras funciones.',
    },
  },
  {
    element: '#tour-utilidades-tabs',
    popover: {
      title: 'Pestañas',
      description:
        'Catálogos base (visores de los catálogos AFIP), Rubros, Retenciones/Percepciones (estándar de AFIP ' +
        'de solo lectura + propias del estudio editables), Conceptos (para la imputación de proveedores) y ' +
        'Auditoría de operaciones (registro de altas/ediciones/bajas del módulo IVA).',
      side: 'bottom',
      align: 'start',
    },
  },
]

export const tourLibroIva: DriveStep[] = [
  {
    popover: {
      title: 'Libro IVA y DDJJ',
      description: 'El centro de la liquidación del período — un repaso de sus pestañas.',
    },
  },
  {
    element: '#tour-libro-tabs',
    popover: {
      title: 'Pestañas',
      description:
        'Resumen (totales y saldo de IVA), DDJJ F2002 (débito vs. crédito computable), IVA Simple/F2051 (con ' +
        'presentación), Reportes (subdiario y percepciones, con impresión a PDF) y Mayor (movimientos por cuenta).',
      side: 'bottom',
      align: 'start',
    },
  },
  {
    element: '#tour-libro-tab-descargas',
    popover: {
      title: 'Descargas',
      description:
        'Acá se generan todos los archivos para presentar: subdiario CSV/TXT, Libro IVA Digital (Portal IVA), ' +
        'DJ IVA Simple y SIFERE (percepciones IIBB).',
      side: 'bottom',
      align: 'end',
    },
  },
]
