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
            'IVA › Factura electrónica (AFIP), Estudio (Sueldos, Vencimientos y tareas), Administración ' +
            '(Administración, Utilidades) y Manuales.',
          'Requieren una EMPRESA activa: Períodos, Clientes, Proveedores, Cuentas, Actividades y ' +
            'Reportes de Mayor. Se desbloquean al elegir empresa en el encabezado.',
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
          'Los grupos del menú (IVA / Estudio / Administración) se abren solos cuando estás parado en una de sus ' +
            'pantallas.',
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
        ],
      },
      {
        titulo: 'Clientes y Proveedores',
        cuerpo: [
          'Padrón de sujetos por empresa. El botón AFIP autocompleta los datos por CUIT desde el padrón.',
          'Un sujeto se puede marcar como Global para compartirlo entre todas las empresas del estudio. ' +
            'Los proveedores admiten varios CAI (con su vencimiento).',
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
        titulo: 'Actividades (DJ IVA por actividad)',
        cuerpo: [
          'Actividades NAES de la empresa para abrir la DJ IVA Simple por actividad. Soporta las estrategias de ' +
            'reparto por punto de venta, por alícuota, por receptor, por porcentajes fijos o por comprobante.',
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
          'Carga masiva desde un CSV (por ejemplo el de "Mis Comprobantes" de ARCA). Se sube el archivo, se ' +
            'mapean las columnas a los campos del sistema (el mapeo se auto-detecta) y se importan al período activo.',
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
