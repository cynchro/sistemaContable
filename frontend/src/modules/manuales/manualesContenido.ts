/**
 * Contenido de los manuales de uso del sistema, organizado por área (las mismas que el
 * menú principal). Estructura pensada para crecer: hoy IVA está completo; Sueldos y
 * Automatización quedan como próximas. Agregar un área = sumar un objeto a MANUALES.
 */
export interface ManualSubseccion {
  titulo: string
  /** Párrafos o pasos; se renderizan como lista/bloques claros. */
  cuerpo: string[]
}

export interface ManualSeccion {
  id: string
  titulo: string
  estado: 'disponible' | 'en_preparacion'
  intro: string
  subsecciones: ManualSubseccion[]
}

export const MANUALES: ManualSeccion[] = [
  {
    id: 'navegacion',
    titulo: 'Navegación / Cómo moverse por el sistema',
    estado: 'disponible',
    intro:
      'El sistema trabaja siempre sobre una "empresa activa" y un "período activo" que se eligen en la barra ' +
      'superior (el encabezado). Es el mismo modelo del Visual IVA de escritorio: primero se activan empresa y ' +
      'período, y recién ahí se habilitan las pantallas que operan sobre ellos. Por eso los ítems del menú ' +
      'lateral se van "encendiendo" a medida que elegís ese contexto.',
    subsecciones: [
      {
        titulo: 'La única acción que desbloquea el menú: elegir empresa y período (en el encabezado)',
        cuerpo: [
          'Arriba de todo hay dos selectores: "Empresa: — elegir —" y "Período: — elegir —". El de período está ' +
            'deshabilitado hasta que elijas una empresa; una vez elegida, lista sus períodos y muestra si cada uno ' +
            'está Abierto o Cerrado.',
          'Elegir la empresa (y luego el período) es lo que habilita los ítems del menú. La elección queda ' +
            'guardada y sobrevive a recargas de la página.',
          'Ojo: cambiar de empresa borra el período activo (el período pertenece a la empresa), así que los ítems ' +
            'que dependen del período se vuelven a bloquear hasta que elijas un período nuevo.',
        ],
      },
      {
        titulo: 'Qué necesita cada ítem del menú',
        cuerpo: [
          'Siempre habilitados (no dependen del contexto): Inicio, IVA › Empresas / Contribuyentes, ' +
            'IVA › Padrón de proveedores, IVA › Padrón de clientes, IVA › Alertas, ' +
            'IVA › Factura electrónica (AFIP), Estudio (Sueldos, Tareas y honorarios), ' +
            'Administración (Utilidades) y Manuales.',
          'Requieren una EMPRESA activa: Períodos, Clientes, Proveedores, Cuentas, ' +
            'Reportes de Mayor y Auditoría ARCA. Se desbloquean al elegir empresa en el encabezado.',
          'Requieren EMPRESA + PERÍODO activos: Ventas, Compras, Libro IVA / DDJJ, Importar comprobantes y Panel. ' +
            'Se desbloquean al elegir empresa y período.',
          'En resumen hay dos "llaves": elegir empresa abre el bloque de pantallas por-empresa; elegir empresa ' +
            'más período abre el bloque de pantallas del período.',
        ],
      },
      {
        titulo: 'Detalles útiles',
        cuerpo: [
          'Un ítem deshabilitado se ve gris; al pasar el mouse muestra un aviso con lo que falta ("Elegí una ' +
            'empresa activa en el header" o "Elegí empresa y período activos en el header").',
          'El período no necesita estar Abierto para entrar a las pantallas: con un período Cerrado igual podés ' +
            'ver Ventas/Compras/Libro IVA; lo que se bloquea es cargar o editar (no la navegación).',
          'Los grupos del menú (IVA / Contabilidad / Estudio / Administración) se abren solos cuando estás ' +
            'parado en una de sus pantallas.',
          'Un enlace directo (por ejemplo el que te comparte un compañero apuntando a una venta puntual) funciona ' +
            'igual aunque el menú esté bloqueado: el contexto activo es solo para navegar por el costado.',
        ],
      },
    ],
  },
  {
    id: 'iva',
    titulo: 'IVA',
    estado: 'disponible',
    intro:
      'Circuito completo de IVA: cargar comprobantes de ventas y compras, liquidar el período (Libro IVA y ' +
      'declaraciones juradas), generar las exportaciones para ARCA/rentas y emitir factura electrónica. ' +
      'Todo trabaja sobre una empresa (contribuyente) y un período activos, que se eligen en la barra superior.',
    subsecciones: [
      {
        titulo: 'Empresas / Contribuyentes',
        cuerpo: [
          'Es el ABM de contribuyentes del estudio. Para dar de alta uno nuevo, escribí el CUIT y tocá "Buscar": ' +
            'el sistema consulta el padrón de ARCA y completa nombre, domicilio y localidad automáticamente.',
          'Cada empresa guarda además sus datos de contacto, socios y credenciales de acceso (las claves se ' +
            'guardan cifradas). La empresa activa se elige en el selector del encabezado y condiciona todo el resto.',
        ],
      },
      {
        titulo: 'Períodos',
        cuerpo: [
          'Un período es un mes de trabajo (por ejemplo 2026-05). Se crea con su fecha de inicio y fin.',
          'Mientras está Abierto se pueden cargar y editar comprobantes. Al Cerrarlo queda de solo lectura ' +
            '(protege la liquidación presentada); se puede reabrir si hace falta corregir.',
          'Un comprobante cargado en el período equivocado se puede Mover a otro período sin recargarlo.',
        ],
      },
      {
        titulo: 'Ventas',
        cuerpo: [
          'Listado y carga de los comprobantes de venta del período. En el alta se cargan la cabecera ' +
            '(tipo, letra, punto de venta, número, cliente) y la discriminación de IVA por alícuota; el sistema ' +
            'calcula el IVA y el total.',
          'Soporta percepciones por comprobante (integran el total), comprobantes asociados para notas de ' +
            'crédito/débito, CAI/CAE, y casos especiales como Factura C (sin IVA) y Factura T (reintegro al turista). ' +
            'Avisa si una venta queda sin neto gravado (todo a exento) por si fue un error de carga.',
          'Desde el listado se puede emitir el CAE (factura electrónica) de un comprobante que aún no lo tiene.',
          'Si algún comprobante quedó sin cliente asignado (por ejemplo, importado con un CUIT que no matcheó a ' +
            'nadie del padrón), aparece un aviso arriba del listado con la cantidad pendiente; "Ver pendientes" ' +
            'despliega esas filas y "Asignar cliente" abre el mismo modal de edición para resolverlo.',
        ],
      },
      {
        titulo: 'Compras',
        cuerpo: [
          'Igual que ventas pero del lado del proveedor, con el crédito fiscal computable por línea.',
          'El botón "Nueva compra ▾" ofrece presets para los comprobantes manuales típicos (resumen bancario, ' +
            'ticket de combustible, cuota de préstamo, liquidación de tarjeta, servicio público, póliza de seguro): ' +
            'cada preset ya trae el tipo, la letra, el concepto y las alícuotas, y recuerda la convención de ' +
            'punto de venta / número del estudio.',
          'En seguros, cargá el "Total del comprobante" y usá "Imputar a Imp. interno" para el resto que la ' +
            'aseguradora no discrimina. Para percepciones sobre montos exentos (ej. medicamentos), usá la columna ' +
            '"Base" de la percepción aunque no haya neto gravado.',
          'Igual que en Ventas, un aviso arriba del listado avisa cuántas compras quedaron sin proveedor asignado, ' +
            'con "Ver pendientes" para resolverlas desde ahí.',
          'Al elegir el proveedor, si tiene una cuenta contable configurada (ver "Imputación contable de ' +
            'proveedores" más abajo) el sistema precarga esa cuenta en las líneas nuevas — se puede pisar a mano ' +
            'en una línea puntual (por ejemplo, un resumen bancario con varias cuentas).',
        ],
      },
      {
        titulo: 'Clientes y Proveedores (padrón único)',
        cuerpo: [
          'El padrón de sujetos es único por estudio (no por empresa): un mismo CUIT es siempre el mismo sujeto. ' +
            'Al dar de alta un cliente o proveedor por CUIT, si ese CUIT ya existe en otra empresa del estudio, ' +
            'el sistema reutiliza sus datos y solo lo "activa" para la empresa nueva — no hay que volver a ' +
            'cargarlo. El botón AFIP autocompleta los datos por CUIT desde el padrón de ARCA en el alta.',
          'Los proveedores admiten varios CAI (con su vencimiento).',
          '"Padrón de proveedores" y "Padrón de clientes" (más abajo en el menú de IVA, separados — nunca ' +
            'mezclados) muestran todos los sujetos de ese rol en el estudio en una sola vista, con las empresas ' +
            'donde cada uno está activo — útil para buscar sin recorrer empresa por empresa; la edición se sigue ' +
            'haciendo desde la pantalla de Clientes/Proveedores de cada empresa.',
        ],
      },
      {
        titulo: 'Imputación contable de proveedores',
        cuerpo: [
          'Desde el listado de Proveedores, el botón "Imputación" abre las reglas que resuelven a qué cuenta ' +
            'contable se imputan las compras de ese proveedor (para la mayorización), sin tener que elegirla a ' +
            'mano en cada comprobante.',
          'Hay tres niveles, de más a menos específico: (1) una regla por punto de venta del proveedor válida ' +
            'para todas las empresas del estudio, con su excepción para esta empresa en particular si hace falta ' +
            '(caso típico: un mismo proveedor que factura combustible desde un PV y otros insumos desde otro); ' +
            '(2) un "concepto contable por defecto" del proveedor (también a nivel estudio, con excepción por ' +
            'empresa); (3) si no hay ninguna regla, no se precarga nada y se elige la cuenta a mano.',
          'El concepto (por ejemplo "Combustibles y lubricantes") se define una sola vez en Utilidades › ' +
            'Conceptos, y cada empresa lo mapea a su propia cuenta del plan de cuentas en Actividades › "Mapeo ' +
            'de conceptos → cuentas" (dos empresas pueden mapear el mismo concepto a cuentas distintas de su ' +
            'propio plan).',
        ],
      },
      {
        titulo: 'Cuentas (plan de cuentas)',
        cuerpo: [
          'Plan de cuentas por empresa. Se usa para la mayorización: al cargar un comprobante podés imputar el ' +
            'neto de cada línea a una cuenta (por ejemplo, en un resumen bancario, el 21% a "Comisiones" y el ' +
            '10,5% a "Intereses"). Con eso se alimentan los reportes de Mayor.',
        ],
      },
      {
        titulo: 'Actividades (DJ IVA por actividad y clasificación contable)',
        cuerpo: [
          'Actividades NAES de la empresa para abrir la DJ IVA Simple por actividad. Soporta las estrategias de ' +
            'reparto por punto de venta, por alícuota, por receptor, por porcentajes fijos o por comprobante.',
          'La misma pantalla tiene, más abajo, dos secciones aparte para la mayorización: "Clasificación de ' +
            'ventas por cuenta contable" (regla general por punto de venta propio + excepción por tipo de ' +
            'comprobante, por ejemplo para separar notas de crédito de facturas) y "Mapeo de conceptos → ' +
            'cuentas" (dónde cada empresa asigna una cuenta de su plan a cada concepto contable del estudio, ' +
            'usado por la imputación de proveedores).',
        ],
      },
      {
        titulo: 'Alertas estadísticas',
        cuerpo: [
          'Compara, para cada empresa, el total del último período contra el promedio de sus períodos ' +
            'anteriores (compras y ventas por separado, con al menos 3 períodos de historial). Un desvío grande ' +
            '(por defecto más del 30%) puede señalar la compra de un bien de uso u otro movimiento fuera de lo ' +
            'habitual que convenga revisar. Es una v1: el umbral es un valor por defecto, todavía sin confirmar ' +
            'con el contador.',
        ],
      },
      {
        titulo: 'Auditoría de ventas vs. ARCA',
        cuerpo: [
          'Compara, para cada combinación de punto de venta + tipo + letra ya usada en la empresa, el último ' +
            'número que ARCA reconoce como autorizado (WSFEv1) contra el último cargado localmente. Se consulta ' +
            'a demanda con el botón "Consultar ARCA" / "Actualizar" (no llama a ARCA solo).',
          'Las combinaciones con faltantes quedan resaltadas; se puede elegir un número puntual y "Consultar" ' +
            'para ver el detalle de ese comprobante en ARCA (fecha, importes, CAE) y, si no está cargado acá ' +
            'todavía, "Cargar como venta" para traerlo directo al período que corresponda por fecha.',
        ],
      },
      {
        titulo: 'Libro IVA / DDJJ',
        cuerpo: [
          'El centro de la liquidación del período. Pestañas: Resumen (totales y saldo de IVA), DDJJ F2002 ' +
            '(débito vs crédito computable), IVA Simple / F2051 (con presentación que guarda el arrastre), ' +
            'Reportes (subdiario de ventas/compras y percepciones, con impresión a PDF) y Mayor (por cuenta).',
          'La pestaña Descargas genera los archivos para ARCA y rentas: subdiario en CSV/TXT, Libro IVA Digital ' +
            '(los 4 archivos del Portal IVA), DJ IVA Simple y SIFERE Convenio Multilateral (percepciones IIBB).',
        ],
      },
      {
        titulo: 'Reportes de Mayor',
        cuerpo: [
          'Reportes analíticos por rango de fechas (cruza todos los períodos de la empresa), con filtros por ' +
            'cuenta, proveedor, provincia y origen, y subtotales en cascada (por ejemplo Cuenta → Proveedor → ' +
            'comprobantes). Útil para la DDJJ anual de convenio ("gastos por provincia") y pedidos puntuales.',
        ],
      },
      {
        titulo: 'Importar comprobantes',
        cuerpo: [
          'Carga masiva desde un CSV (por ejemplo el de "Mis Comprobantes" de ARCA, o cualquier export). Se sube ' +
            'el archivo, se mapean las columnas a los campos del sistema (el mapeo se auto-detecta) y se importan ' +
            'al período activo. El IVA y el total los calcula el motor, igual que en la carga manual.',
          'Antes de importar, cada fila se valida y se marca con color: en rojo las que tienen error (falta la ' +
            'fecha, o no tiene ningún importe) y en amarillo los avisos (fecha fuera del período, CUIT que no ' +
            'tiene 11 dígitos). Un resumen indica cuántas son válidas, cuántas con aviso y cuántas con error, y ' +
            'podés tildar "omitir las filas con error" para importar solo las válidas. Si el período está cerrado, ' +
            'avisa y no deja importar.',
          'Si no mapeás la columna de alícuota pero sí la del IVA, el sistema deduce la alícuota (IVA ÷ neto). ' +
            'Para comprobantes con más de una alícuota en la misma fila (por ejemplo un resumen bancario con ' +
            'columnas separadas de 21% y 10,5%), usá "Alícuotas adicionales" para sumar cada línea con su neto y ' +
            'su alícuota.',
          'El mapeo se puede guardar como Perfil reutilizable: una vez que armaste el mapeo de un origen (tu banco, ' +
            'tu punto de venta), tocá "Guardar mapeo actual…" y la próxima vez lo elegís del desplegable "Perfil de ' +
            'mapeo" sin volver a mapear. Los perfiles quedan guardados en este navegador.',
        ],
      },
      {
        titulo: 'Factura electrónica (AFIP)',
        cuerpo: [
          'Consulta de padrón por CUIT y ABM de puntos de venta. La emisión de CAE se dispara desde el listado ' +
            'de ventas. Requiere el certificado de ARCA configurado.',
        ],
      },
      {
        titulo: 'Panel',
        cuerpo: [
          'Tablero de indicadores del período con gráficos (cantidades, IVA débito/crédito, montos). Es una ' +
            'muestra pensada como base para un tablero más completo.',
        ],
      },
    ],
  },
  {
    id: 'administracion',
    titulo: 'Administración',
    estado: 'disponible',
    intro:
      'Configuración del estudio que no depende de una empresa ni un período: permisos de usuarios, catálogos ' +
      'base y datos propios del tenant que usan las reglas de imputación y otras funciones de IVA.',
    subsecciones: [
      {
        titulo: 'Administración (roles y permisos)',
        cuerpo: [
          'Pestaña "Roles y permisos": lista de roles del estudio, alta de rol nuevo, y un gestor por rol para ' +
            'asignar o quitar permisos puntuales (por ejemplo, dar acceso de solo lectura a Ventas sin dar el ' +
            'resto del módulo IVA).',
          'Pestaña "Permisos": catálogo de los permisos disponibles (alta de permisos nuevos).',
          'Pestaña "Usuarios": listado de los usuarios del estudio con su rol asignado. El alta de un usuario ' +
            'nuevo se hace por línea de comando (no tiene formulario en esta pantalla todavía).',
        ],
      },
      {
        titulo: 'Utilidades',
        cuerpo: [
          'Pestaña "Catálogos base": visores de solo lectura de los catálogos AFIP del sistema (condiciones ' +
            'de IVA, tipos de comprobante, tipos de documento, provincias, monedas, etc.).',
          'Pestaña "Rubros": ABM de rubros (F2002) del estudio.',
          'Pestaña "Retenciones / Percepciones": ABM de los tipos de retención/percepción. Los tipos estándar ' +
            'de AFIP son de solo lectura (badge "Estándar"); el estudio puede agregar los propios (badge ' +
            '"Propio"), con su base de cálculo.',
          'Pestaña "Conceptos": catálogo de conceptos contables propios del estudio (por ejemplo "Combustibles ' +
            'y lubricantes"), usados para la imputación por defecto de proveedores — cada empresa después mapea ' +
            'estos conceptos a una cuenta de su propio plan en Actividades.',
          'Pestaña "Auditoría de operaciones": registro paginado de las altas/ediciones/bajas hechas en el ' +
            'módulo IVA (quién, cuándo, qué endpoint, con qué datos).',
        ],
      },
    ],
  },
  {
    id: 'sueldos',
    titulo: 'Sueldos',
    estado: 'en_preparacion',
    intro:
      'Liquidación de sueldos: legajos, conceptos con fórmula, recibos, contribuciones, SAC y vacaciones. ' +
      'El manual detallado de esta área se publicará próximamente.',
    subsecciones: [],
  },
  {
    id: 'automatizacion',
    titulo: 'Automatización',
    estado: 'en_preparacion',
    intro:
      'Automatización de tareas repetitivas del estudio (ingesta de comprobantes, conciliaciones, recordatorios ' +
      'de vencimientos). Área en diseño; el manual se irá completando a medida que se agreguen funciones.',
    subsecciones: [],
  },
]
