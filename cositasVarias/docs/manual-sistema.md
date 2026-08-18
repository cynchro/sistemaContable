# Manual del Ecosistema Contable

Sistema integral para estudios contables — IVA · Sueldos · AFIP · Gestión

---

## 1. Introducción

### 1.1 Qué es el Ecosistema Contable

El Ecosistema Contable es un sistema integral que reemplaza los múltiples programas sueltos que hoy usa un estudio contable (IVA, sueldos, gestión/CRM fiscal) por una sola plataforma web, multi-empresa y conectada con ARCA. Nace de la ingeniería inversa del "Visual IVA" y de los sistemas de Sueldos y CRM/fiscal del Estudio Haddad.

**Propuesta de valor:**

- **Unificación sin redundancia.** El contribuyente es una única entidad que atraviesa todos los módulos. Se carga una vez.
- **Fidelidad con lo que ya saben usar.** El frontend replica el flujo del Visual IVA que el estudio ya domina.
- **Cumplimiento con ARCA de punta a punta.** Libro IVA Digital, DJ IVA Simple (F2051), factura electrónica (WSFEv1 + CAE), consulta de padrón, y exportaciones SIFERE — validadas byte a byte contra archivos reales.
- **Web y multi-dispositivo.** Se accede desde el navegador, con los datos centralizados y respaldados.
- **Automatización del trabajo repetitivo.** Importación CSV, presets de carga, y a futuro conciliaciones e ingesta automática.

### 1.2 Cómo acceder

El sistema funciona 100% desde el navegador web (Chrome, Edge o Firefox). No requiere instalar ningún programa. La dirección de acceso la provee el administrador del estudio.

Cada usuario tiene su propio **email y clave** para ingresar. El administrador del sistema es quien crea los usuarios y les asigna los permisos correspondientes.

---

## 2. Navegación y conceptos clave

### 2.1 Modelo mental: Estudio → Empresas → Períodos

El sistema se organiza en tres niveles jerárquicos:

1. **Estudio contable (tenant):** es el "inquilino" del sistema. Cada estudio ve solo sus datos. En desarrollo hay uno solo: "Estudio Cynchro".
2. **Empresas (contribuyentes):** los clientes del estudio. Cada empresa tiene su propio plan de cuentas, períodos, comprobantes, empleados y vencimientos. Es la entidad canónica que atraviesa todos los módulos.
3. **Períodos:** cada empresa opera por períodos mensuales (ej. "2026-01", "2026-02"). Los comprobantes de IVA se cargan siempre dentro de un período.

### 2.2 Selectores del header

En la barra superior hay dos selectores que controlan todo el sistema:

- **Empresa activa:** elegir una empresa del listado. Al seleccionarla se habilita el selector de período.
- **Período activo:** elegir un período de esa empresa. El selector muestra si cada período está Abierto o Cerrado.

La selección se persiste en el navegador y sobrevive a recargas. Cambiar de empresa borra el período activo.

### 2.3 Menú lateral

El menú se habilita por secciones según el contexto:

**Siempre habilitados:** Inicio, Empresas, AFIP, Sueldos, Gestión, Administración, Manuales.

**Requieren empresa activa:** Períodos, Clientes, Proveedores, Cuentas, Actividades, Reportes de Mayor, Auditoría ARCA. Sin empresa muestran el hint "Elegí una empresa activa en el header".

**Requieren empresa + período activo:** Ventas, Compras, Libro IVA/DDJJ, Importar, Panel. Sin período muestran "Elegí empresa y período activos".

### 2.4 Roles y permisos

El sistema tiene control de acceso por **permisos** (ej. "ventas", "libro IVA", "liquidar sueldos"). Los permisos se agrupan en **roles** (ej. "Carga de comprobantes", "Liquidación"), y los roles se asignan a **usuarios**.

El rol **Administrador** tiene acceso total a todos los módulos. Los demás roles se configuran según lo que cada persona necesita operar.

---

## 3. Empresas (Contribuyentes)

### 3.1 Listado de empresas

**Ruta:** Menú IVA → Empresas

La pantalla muestra una tabla con todas las empresas del estudio. Cada fila muestra Nombre, CUIT, Condición IVA, Provincia, Teléfono y botones de acción.

### 3.2 Alta de empresa + autocompletar desde ARCA

1. Clic en **"Nueva empresa"**.
2. Ingresar **CUIT** (11 dígitos, sin guiones).
3. Clic en **"Obtener datos de AFIP"**. El sistema consulta el padrón A13 de ARCA y autocompleta: Nombre, Domicilio, Localidad, Provincia, Actividad principal, Inicio de actividades.
4. **Condición IVA:** se carga manualmente (Responsable Inscripto, Monotributo, Exento, etc.). ARCA no expone este dato en el padrón A13.
5. Completar/corregir datos locales: Teléfono, Email, Dirección, Ingresos Brutos.
6. Elegir **Actividad principal** y **secundaria** del catálogo NAES.
7. Clic en **"Guardar"**.

### 3.3 Edición y datos CRM

Al editar una empresa se accede a campos adicionales:

- **Tipo de persona:** Física o Jurídica.
- **Inscripción:** fecha de inscripción en AFIP.
- **Contabilidad:** si lleva contabilidad completa.
- **Email** y **Contacto:** datos de la persona de contacto en la empresa.

### 3.4 Socios

Cada empresa puede tener socios (integrantes). Para gestionarlos, usar el desplegable "Abrir" en la fila de la empresa. Cada socio registra: CUIT, nombre, porcentaje de participación, cargo y si es colaborador del estudio.

### 3.5 Credenciales de acceso

El sistema guarda credenciales de acceso a portales del contribuyente (AFIP, Rentas provinciales, procesadoras de tarjeta). Cada credencial tiene: tipo (fiscal/tarjeta), sistema (AFIP/RENTAS/VISA...), usuario y clave. La clave se guarda de forma segura y solo se muestra al consultarla desde esta pantalla (el estudio necesita verla para operar).

### 3.6 Eliminar empresa

Botón **"Eliminar"** con confirmación. Elimina en cascada todos los datos asociados (períodos, comprobantes, sujetos, cuentas). Operación irreversible.

---

## 4. Períodos

### 4.1 Crear período

**Ruta:** Empresas → (empresa) → Períodos → **"Nuevo período"**

1. Ingresar **Nombre** (ej. "2026-01").
2. Definir **Fecha desde** y **Fecha hasta** (típicamente primer y último día del mes).
3. Clic en **"Guardar"**. El período se crea en estado **Abierto**.

### 4.2 Cerrar y abrir períodos

- **Cerrar:** botón **"Cerrar"** en la fila del período. Bloquea la carga y edición de comprobantes, y la importación CSV para ese período. Es el equivalente contable al cierre mensual.
- **Abrir:** si el período ya está cerrado, aparece el botón **"Abrir"** para reabrirlo. Útil para correcciones.

### 4.3 Efecto sobre la carga

Cuando un período está **Cerrado**:

- Las pantallas de Ventas, Compras e Importar muestran un aviso: "El período activo está cerrado: no se pueden cargar comprobantes. Abrilo desde Períodos."
- Los botones de alta, edición e importación se deshabilitan.
- El Libro IVA y las descargas siguen funcionando normalmente (son de consulta).

### 4.4 Accesos directos

Cada fila del listado de períodos tiene accesos directos a Ventas, Compras y Libro IVA de ese período. Clic en cualquiera de ellos activa ese período y navega a la pantalla correspondiente.

---

## 5. IVA — Clientes y Proveedores (Padrón Único)

### 5.1 Concepto

El sistema usa un **padrón único de sujetos**: cada CUIT se registra una sola vez en el estudio, y luego se activa como cliente, proveedor, o ambos, para cada empresa. Esto elimina la duplicación de los sistemas legacy, donde el mismo CUIT se cargaba en cada empresa por separado.

### 5.2 Listado y búsqueda

**Rutas:** `/empresas/{id}/clientes` y `/empresas/{id}/proveedores`

La tabla muestra Nombre/Razón social, CUIT, Condición IVA y Provincia. Se puede buscar por **nombre** o **CUIT** (el buscador filtra en tiempo real). El paginado y ordenamiento son client-side.

### 5.3 Alta de sujeto + autocompletar ARCA

1. Clic en **"Nuevo cliente"** o **"Nuevo proveedor"**.
2. Ingresar **CUIT** y clic en el botón **"AFIP"**. Autocompleta Nombre, Domicilio y Localidad desde el padrón de ARCA.
3. Completar **Condición IVA**, **Provincia** y **Rubro**.
4. Clic en **"Guardar"**. El sujeto queda en el padrón único y activado para esta empresa con el rol elegido.

### 5.4 Roles: mismo sujeto como cliente y proveedor

Un mismo CUIT puede ser cliente en una empresa y proveedor en otra (o ambas en la misma). El sistema lo maneja automáticamente: al darlo de alta en "Proveedores", si ya existía como cliente, simplemente se agrega el rol proveedor para esa empresa.

### 5.5 Imputación contable de proveedores

**Ruta:** Proveedores → clic en un proveedor → **"Imputación contable"**

Esta pantalla define cómo se imputan contablemente las compras de ese proveedor:

1. **Concepto por defecto:** concepto contable asignado a este proveedor para esta empresa.
2. **Regla global por punto de venta:** para cada punto de venta del proveedor, un concepto.
3. **Excepción por empresa:** una empresa puede sobrescribir la regla global de un punto de venta.

La cadena de resolución es: excepción por empresa → regla global por PV → concepto default del sujeto → concepto default de la empresa.

### 5.6 Padrón único (vista global)

**Ruta:** Menú IVA → Padrón único

Muestra **todos los sujetos del estudio** (sin filtrar por empresa). Un mismo CUIT aparece una sola vez, con badges que indican en qué empresas está activo y con qué rol (cliente/proveedor). Clic en un badge navega a la lista de clientes/proveedores de esa empresa con el CUIT precargado en el buscador.

---

## 6. IVA — Ventas

### 6.1 Listado con filtros

**Ruta:** Empresas → (empresa) → Períodos → (período) → Ventas

La tabla muestra: Fecha, Tipo, Letra, PV, Número, Cliente, Neto Gravado, IVA, Percepciones, Total, CAE y estado.

**Filtros disponibles:**

- Rango de **fechas** (desde/hasta)
- **Letra** del comprobante (A, B, C, T...)
- **Cliente** (buscador typeahead contra el padrón)
- **Número** de comprobante
- **Orden:** por fecha, cliente o número

**Acciones por fila:** Editar, Emitir CAE, Verificar, Mover, Eliminar.

**Acciones masivas:** selección múltiple con checkbox + borrar seleccionados.

### 6.2 Alta de venta — ficha completa

Clic en **"Nueva venta"** abre un modal con todos los campos:

#### Datos del comprobante
1. **Fecha de emisión** (obligatoria, default: última usada).
2. **Tipo de comprobante:** Factura, Nota de Débito, Nota de Crédito, etc. (catálogo AFIP).
3. **Letra:** A, B, C, T, etc.
4. **Punto de venta** (número) y **Número** de comprobante.
5. **Número hasta:** para cargar un rango de comprobantes consecutivos.
6. **Tipo de operación:** venta internación, exportación, etc.
7. **CAI/CAE** y **Vto. CAI/CAE:** opcional, para comprobantes ya autorizados.

#### Cliente
8. **Cliente / Razón social:** buscador que busca en el padrón de clientes por nombre o CUIT. Al seleccionar, autocompleta CUIT, Condición IVA y Provincia. Si se tipea un nombre sin seleccionar, queda como "cliente ocasional".
9. Botón **"Nuevo cliente"** para alta inline sin salir de la pantalla.
10. Botón **"AFIP"** junto al CUIT para autocompletar desde padrón ARCA.
11. **Tipo de documento** (CUIT, DNI, etc.), **Condición IVA**, **Provincia**.

#### Discriminación de IVA
12. Por cada línea: **Neto gravado** + **Alícuota (%)** + **IVA** (opcional: si se deja vacío se calcula automáticamente; si se completa, sobreescribe el cálculo).
13. **"+ Agregar línea"** para alícuotas múltiples.
14. Letra **C** → aviso: "no lleva IVA discriminado, cargá el importe en Neto no gravado".
15. Tipo **Factura T** → aviso: el sistema iguala el reintegro al IVA para que el débito fiscal neto quede en cero.

#### Percepciones
16. **"+ Agregar percepción"** → elegir Tipo del catálogo, y opcionalmente Provincia, Alícuota, Base, Importe (los vacíos los calcula el sistema según la configuración del tipo).

#### Comprobantes asociados (NC/ND)
17. **"+ Agregar asociado"** → punto de venta y número de la factura original que se referencia. Obligatorio para Notas de Crédito y Débito electrónicas.

#### Totales e información adicional
18. **Neto no gravado**, **Exento**, **Impuestos internos** (opcionales).
19. Si todo cae en exento/no gravado sin neto gravado: aviso "Esta venta no tiene neto gravado... Revisá antes de guardar."
20. **Actividad (IVA/IIBB):** si se deja vacío, se resuelve automáticamente por punto de venta.
21. **Rubro (F2002):** clasificación para la DDJJ.
22. **Cuenta Debe / Cuenta Haber:** para mayorización contable.
23. **Bien de uso:** checkbox para indicar que es un bien de uso.
24. **Anulado:** checkbox que despliega "Fecha de anulación".
25. Moneda, Cotización, Campo auxiliar (texto libre).
26. **Total estimado** se muestra en vivo (el definitivo lo calcula el backend al guardar).

27. Clic en **"Guardar"**. Los errores de validación se muestran arriba del modal.

### 6.3 Editar venta

Misma ficha que el alta, precargada con los datos actuales. Al guardar, el sistema recalcula los totales.

### 6.4 Emitir CAE (factura electrónica)

En el listado de ventas, cada fila sin CAE muestra el botón **"Emitir CAE"**.

1. Clic en **"Emitir CAE"**.
2. El sistema se comunica con ARCA, solicita el CAE y lo registra en el comprobante.
3. Resultado: "CAE **XXXXXXXXXXXXXX** obtenido (vence AAAA-MM-DD)".
4. La columna CAE muestra un badge verde con el número de CAE.

Si ARCA rechaza la emisión, se muestra el error devuelto (ej. "El punto de venta no está habilitado", "CUIT no autorizado").

### 6.5 Verificar comprobante contra ARCA

Botón **"Verificar"** en la fila: consulta el comprobante a ARCA por PV + tipo + número y compara el CAE y el total. Detecta CAEs mal tipeados o discrepancias.

### 6.6 Mover a otro período

Seleccionar uno o varios comprobantes → clic en **"Mover"** → elegir período destino. Útil para corregir comprobantes cargados en el período equivocado.

### 6.7 Pendientes

Panel en el listado: muestra comprobantes cuyo cliente no está en el padrón de la empresa (cliente ocasional). Botón **"Asignar cliente"** para vincularlos retroactivamente.

### 6.8 Borrado

- **Individual:** botón Eliminar en la fila, con confirmación.
- **Masivo:** seleccionar con checkboxes → **"Borrar seleccionados"**.
- Los comprobantes con CAE emitido no se pueden borrar (están registrados en ARCA).

---

## 7. IVA — Compras

### 7.1 Listado

**Ruta:** Empresas → (empresa) → Períodos → (período) → Compras

Idéntico al listado de Ventas pero con proveedores. Mismas columnas, filtros y acciones. La columna adicional es **CF Computable** (crédito fiscal computable).

### 7.2 Alta de compra — paso a paso

Clic en **"Nueva compra"**. A su lado hay un desplegable con **presets de carga manual** para los tipos de comprobante más comunes que no vienen de ARCA:

#### Presets disponibles

| Preset | Tipo comprobante | Numeración sugerida |
|--------|-----------------|---------------------|
| Resumen bancario | Según banco | PV = nro de banco, Nro = MMAAXXXX |
| Ticket combustible | Cód. 81 (Liquidación) | PV + número del ticket |
| Cuota de préstamo | Factura | PV + número de la cuota |
| Liquidación de tarjeta | Factura | PV + número de liquidación |
| Servicio público | Factura | PV + número de factura |
| Póliza de seguro | Factura | PV + número de póliza |

Cada preset precarga: tipo de comprobante, letra, líneas de alícuota típicas y concepto DJ IVA.

#### Campos del formulario (similares a Ventas)

1. **Fecha**, **Tipo de comprobante**, **Letra**, **PV**, **Número**, **Tipo de operación**.
2. **Proveedor:** mismo buscador que en ventas (por nombre o CUIT).
3. **Condición IVA**, **Provincia**, **Actividad (IIBB)**.
4. **Rubro (F2002)** y **Concepto DJ IVA:** 1=Compras de bienes, 2=Locaciones (alquileres), 3=Servicios, 4=Inversiones en bienes de uso.
5. **Cuenta Debe / Cuenta Haber** para mayorización.

#### Discriminación de IVA
6. Por línea: Neto gravado, Alícuota %, IVA (opcional, override), **CF computable** (vacío = 100% del IVA; ej. 50% para gastos parcialmente deducibles), y Cuenta para el mayor.
7. **"+ Agregar línea"** para más alícuotas.

#### Retenciones / Percepciones
8. **"+ Agregar"** → Tipo del catálogo, Alícuota, Base, Provincia, Importe.

#### Totales y diferencia
9. **Neto no gravado**, **Exento**, **Impuestos internos**.
10. **Total del comprobante:** importe real de la factura (opcional).
11. Si el Total informado difiere del neto+IVA calculado: aviso amarillo "difiere en $X. En seguros ese resto suele ser impuesto interno no discriminado" + botón **"Imputar a Imp. interno"** que carga la diferencia automáticamente.

12. Clic en **"Guardar"**.

### 7.3 Editar, mover, borrar, pendientes

Mismas funcionalidades que Ventas. Los pendientes son comprobantes con proveedor ocasional (no vinculado al padrón).

---

## 8. IVA — Importar comprobantes (CSV)

### 8.1 Propósito

Permite cargar comprobantes masivamente desde archivos CSV, típicamente exportados de "Mis Comprobantes" de ARCA o de otros sistemas. Soporta ventas y compras.

**Ruta:** Empresas → (empresa) → Períodos → (período) → Importar comprobantes

**Prerrequisito:** empresa y período activos. Si el período está cerrado, aparece el aviso de bloqueo.

### 8.2 Elegir destino

Botones al inicio: **"Compras (recibidos)"** / **"Ventas (emitidos)"** — por defecto, Compras.

### 8.3 Seleccionar archivo

Clic en **"Archivo CSV"** y seleccionar el archivo. El sistema detecta automáticamente el separador (`;`, `,` o tabulador) y muestra una vista previa de las primeras filas.

### 8.4 Auto-mapeo por nombre de columna

El sistema reconoce encabezados típicos de ARCA: Fecha, Punto de Venta, Nro. Doc, Denominación, Neto Grav., Alícuota, IVA, Importe Total, No Gravado, Exento, Internos, etc. Precarga automáticamente el desplegable de **"Mapeo de columnas"** con la mejor correspondencia.

### 8.5 Perfiles de mapeo

- **Guardar mapeo actual:** persiste la configuración de columnas en el navegador (localStorage), indexada por nombre de columna.
- **Aplicar perfil:** cargar un mapeo guardado previamente. Funciona aunque el CSV cambie el orden de columnas.
- **Borrar perfil:** elimina un perfil guardado.

### 8.6 Alícuotas múltiples y percepciones

- **Alícuotas adicionales:** si el archivo trae más de una alícuota por fila (ej. columna Neto 21% + columna Neto 10.5%), usar **"+ Agregar alícuota"** para mapear cada columna adicional (neto + alícuota fija + IVA opcional).
- **Percepciones / Retenciones:** **"+ Agregar percepción"** (ventas) o **"+ Agregar retención"** (compras). Se elige la columna del importe y el tipo del catálogo.
- **Regla de alícuota derivada:** si no se mapea la columna de alícuota pero sí la de IVA, el sistema la deduce comparando IVA/neto contra las alícuotas vigentes (0%, 2.5%, 5%, 10.5%, 21%, 27%). Si no puede deducirla, usa 21% por defecto.

### 8.7 Vista previa

Muestra las primeras 8 filas procesadas, coloreadas:

- **Verde:** fila OK.
- **Amarillo:** aviso (ej. "Fecha fuera del período", "CUIT sin 11 dígitos", "Sin nombre/razón social").
- **Rojo:** error (ej. "Falta la fecha", "Sin importes").

Resumen con badges: N válidas / N avisos / N errores.

### 8.8 Importación

- **"Omitir las N fila(s) con error"** (tildado por defecto): las filas con error no se importan.
- Clic en **"Importar N comprobante(s)"**.
- Resultado: "**X** de Y comprobantes creados". Si alguna fila falló en el backend, se lista el error puntual de cada una.

---

## 9. IVA — Libro IVA y DDJJ

**Ruta:** Empresas → (empresa) → Períodos → (período) → Libro IVA

Pantalla con 6 pestañas que concentran toda la información fiscal del período.

### 9.1 Pestaña Resumen

Muestra:

- **Totales de ventas:** total neto, IVA débito, percepciones.
- **Totales de compras:** total neto, IVA crédito, retenciones.
- **Saldo de IVA del período:** débito − crédito (rojo si a pagar, verde si a favor).
- **Detalle por alícuota:** apertura de ventas y compras por cada alícuota (21%, 10.5%, 27%, etc.) con CF computable.

### 9.2 Pestaña DDJJ (F2002)

Determinación del impuesto al valor agregado:

- **Débito fiscal:** ventas agrupadas por alícuota.
- **Crédito fiscal computable:** compras agrupadas por alícuota con el CF computable aplicado.
- **Saldo técnico:** débito − crédito computable. Rojo si a pagar, verde si a favor.
- Tabla final con los conceptos firmados de la DDJJ.

### 9.3 Pestaña IVA Simple (F2051)

Régimen simplificado para pequeños contribuyentes. Dos bloques:

**Determinación del impuesto:**
1. Débito fiscal del período.
2. Crédito fiscal computable.
3. Saldo técnico a favor del período anterior.
4. Resultado: a favor de ARCA o del contribuyente.

**Determinación de la posición mensual:**
5. Saldo técnico a favor de ARCA.
6. Saldo de libre disponibilidad del período anterior.
7. Retenciones, percepciones y pagos a cuenta sufridos en el período.
8. **Saldo a pagar** o **Saldo de libre disponibilidad** resultante.

Campo para ingresar **"Ret./perc./pagos sufridos del período"** y botón **"Presentar DDJJ del período"**. Al presentar: "✓ DDJJ presentada." Los arrastres de meses anteriores se toman automáticamente de la DDJJ ya presentada del período previo.

### 9.4 Pestaña Reportes

**Subdiario de ventas:** comprobante por comprobante, con fecha, tipo, letra, PV, número, cliente, CUIT, neto gravado, IVA y total. Totales al pie. Botón **"Imprimir / PDF"** (impresión del navegador).

**Subdiario de compras:** igual, lado proveedor.

**Percepciones y retenciones:** agrupadas por tipo y provincia, con totales.

### 9.5 Pestaña Mayor

- **Saldos por cuenta contable:** Debe, Haber, Saldo, Movimientos.
- Clic en una cuenta → detalle de comprobantes imputados a esa cuenta.
- Sin mayorización: aviso "Asigná una cuenta por línea (neto) o la cuenta Debe/Haber (total) al cargar ventas o compras."

### 9.6 Pestaña Descargas

Archivos exportables:

| Descarga | Formato | Destino |
|----------|---------|---------|
| Subdiario Ventas/Compras | CSV o TXT | Uso interno / Excel |
| Libro IVA Digital | 4 archivos TXT ancho fijo | Portal IVA de ARCA |
| DJ IVA Simple | TXT | Aplicativo ARCA |
| SIFERE | TXT por jurisdicción | Convenio Multilateral |

**Libro IVA Digital:** genera los 4 archivos requeridos por ARCA: VENTAS_CBTE, VENTAS_ALICUOTAS, COMPRAS_CBTE, COMPRAS_ALICUOTAS, más el archivo de anulados.

**SIFERE V4:** para contribuyentes de Convenio Multilateral. Elegir **Jurisdicción (provincia)** → **"Descargar percepciones"**.

**Aviso Facturas T:** si hay Facturas T en el período, se muestra el reintegro total a informar manualmente en el aplicativo de ARCA (los archivos no lo incluyen).

---

## 10. IVA — Actividades económicas

### 10.1 Propósito

Para contribuyentes con múltiples actividades (NAES), el sistema permite clasificar comprobantes por actividad y generar la DDJJ discriminada.

**Ruta:** Empresas → (empresa) → Actividades

### 10.2 ABM de actividades

- Listado de actividades NAES asignadas a la empresa.
- **"Agregar actividad"** → seleccionar del catálogo AFIP de actividades económicas.
- **"Eliminar"** para quitar una actividad de la empresa.

### 10.3 Estrategias de resolución (precedencia)

Cuando se carga un comprobante sin especificar actividad, el sistema la resuelve en este orden:

1. **Actividad explícita en el comprobante** (si se cargó manualmente).
2. **Actividad por receptor/cliente** (mapeo cliente → actividad).
3. **Actividad por punto de venta** (mapeo PV → actividad).
4. **Actividad por alícuota** (mapeo alícuota → actividad).
5. **Coeficientes porcentuales** (reparto entre actividades, deben sumar 1).

Las pantallas de configuración están en la sección Actividades: Puntos de venta → Actividad, Alícuotas → Actividad, Receptor → Actividad, y Coeficientes.

### 10.4 Clasificación de ventas por cuenta contable

Define qué cuenta contable se asigna a las ventas según el punto de venta y tipo de comprobante:

- **Regla general:** PV → cuenta (aplica a todos los comprobantes de ese PV).
- **Excepción por tipo de comprobante:** PV + Tipo → cuenta (sobrescribe la regla general para ese tipo específico).

### 10.5 Mapeo concepto → cuenta

Cada empresa tiene su propio mapeo de conceptos contables a cuentas del plan de cuentas de la empresa. Se configura en la misma pantalla de Actividades.

---

## 11. IVA — Cuentas, Reportes, Auditoría y Alertas

### 11.1 Plan de cuentas

**Ruta:** Empresas → (empresa) → Cuentas

ABM simple: Código + Nombre de cuenta. El plan de cuentas es por empresa y se usa en:

- Mayorización de comprobantes (cuentas Debe/Haber).
- Imputación contable por línea de discriminación.
- Reportes de Mayor.

### 11.2 Reporte de Mayor analítico

**Ruta:** Empresas → (empresa) → Reportes de Mayor

Reporte avanzado con:

- **Rango de fechas** (desde/hasta, sin limitarse a un período).
- **Filtros:** cuenta, provincia, origen, CUIT.
- **Agrupaciones en cascada:** cuenta → proveedor, provincia → cuenta, etc.
- Filas colapsables.
- **"Imprimir / PDF"** para impresión del navegador.

### 11.3 Auditoría ARCA

**Ruta:** Empresas → (empresa) → Auditoría ARCA

Compara los comprobantes locales con los registrados en ARCA (WSFEv1):

1. Para cada punto de venta, muestra: último número local vs. último número en ARCA.
2. Botón **"Consultar ARCA"** (on-demand, no automático).
3. Modal con detalle del comprobante en ARCA: número, importe, CAE.
4. Botón **"Cargar como venta"** → navega a Ventas con los datos precargados para registrar el comprobante faltante.

### 11.4 Alertas estadísticas

**Ruta:** Menú IVA → Alertas

Motor de alertas v1: compara el período actual contra el promedio de los últimos 6 períodos. Dispara alerta si el desvío supera el **30%** (umbral configurable).

La tabla muestra: empresa, período, métrica (ventas, compras, IVA débito, IVA crédito), valor actual, promedio histórico, y desvío %.

Filtro: **"Solo alertas"** para ver únicamente los períodos con desvío significativo.

---

## 12. Factura Electrónica (AFIP)

### 12.1 Consulta de padrón por CUIT

**Ruta:** Menú AFIP → Consulta de padrón (ARCA)

1. Ingresar **CUIT** (11 dígitos, sin guiones).
2. Clic en **"Consultar"** (deshabilitado si no tiene 11 dígitos).
3. Resultado: Denominación, CUIT, Tipo/Estado, Domicilio e Impuestos inscriptos. Si falla: "No se pudo consultar el padrón. Verificá el CUIT y la conexión con ARCA."

### 12.2 Puntos de venta

**Ruta:** Menú AFIP → Puntos de venta

1. Elegir **Empresa** del selector.
2. Completar **Número** y **Descripción** → **"Agregar"**.
3. Tabla: Número, Descripción, Emisión (CAE), Estado (Activo/Inactivo).
4. **"Eliminar"** por fila con confirmación.

### 12.3 Ambiente

Banner visible en la sección AFIP: indica **Homologación** o **Producción**. En homologación, los CAE emitidos no son válidos fiscalmente (son de prueba).

---

## 13. Sueldos

### 13.1 Legajos

**Ruta:** Menú Sueldos → elegir Empresa → pestaña Legajos

1. **"Nuevo legajo"** → completar: Legajo, Apellido/Nombres, CUIL, Fecha de ingreso, Básico, Categoría, Departamento, Activo.
2. Tabla: Legajo, Apellido y nombre, CUIL, Ingreso, Básico, estado (Activo/Inactivo).
3. Acciones: Editar, Eliminar.

**Familiares:** dentro de cada empleado se pueden cargar familiares (parentesco, porcentaje de deducción de ganancias).

**Configuración de empresa:** datos de la empresa para recibos (razón social, domicilio, actividad).

### 13.2 Conceptos

**Ruta:** Menú Sueldos → pestaña Conceptos

Catálogo de conceptos de liquidación del estudio:

1. **"Nuevo concepto"** → Código, Descripción, Tipo (Remunerativo / No remunerativo / Descuento), Fórmula.
2. **Fórmula:** lenguaje propio que permite: aritmética básica, referencias a otros conceptos por código (`NNN#`), variables (`BASICO`, `ANTIG`, `CAN`, `IMP`), y rangos (`NNN#...MMM#`). Ejemplo: `BASICO * 0.11` (jubilación 11%).
3. Guardar.

### 13.3 Liquidaciones

**Ruta:** Menú Sueldos → pestaña Liquidaciones

**Crear liquidación:**
1. Completar **Período** (ej. "2026-01"), Descripción, Fecha de pago.
2. **"Nueva liquidación"** → estado Abierta.

**Cargar novedades y liquidar:**
3. Clic en **"Liquidar"** en la fila → modal **"Liquidar — [período]"**.
4. Elegir **Empleado** (carga sus novedades previas o una fila vacía).
5. **Novedades:** por fila, Concepto + Cantidad + Importe. **"+ Agregar"** para más.
6. **"Guardar novedades"** (guarda sin liquidar) o **"Liquidar"** (guarda y calcula recibo).

**Recibo:** muestra detalle por concepto con Haberes, Descuentos y Neto. Si la liquidación está bloqueada: "La liquidación está bloqueada; no se puede liquidar."

### 13.4 SAC (aguinaldo)

Disponible dentro de la sección Sueldos, por empleado.

Cálculo según Ley 23.041: mejor remuneración remunerativa del semestre × 50%, proporcional por días trabajados / 180. Es cálculo/preview (no persiste liquidación automáticamente).

### 13.5 Vacaciones

Disponible dentro de la sección Sueldos, por empleado.

Cálculo según Ley 20.744: días por antigüedad al 31/12 (14/21/28/35 días), valor día = remuneración / 25, importe = valor día × días. Es cálculo/preview.

### 13.6 Contribuciones patronales

Disponible dentro de cada liquidación. Cálculo de contribuciones (seguridad social, obra social, etc.): base imponible × porcentaje + importe fijo, por empleado. Las definiciones (porcentajes, topes, detracciones) se configuran desde la sección Sueldos.

---

## 14. Gestión del Estudio

### 14.1 Tareas

**Ruta:** Menú Gestión → pestaña Tareas

Workflow interno del estudio:

1. **"Nueva tarea"** → Título (obligatorio), Tipo, Contribuyente (empresa o "interna del estudio"), Prioridad (baja/media/alta/urgente), Fecha límite, Descripción.
2. Tabla: Título (link al detalle), Contribuyente, Prioridad, Vence, Estado.
3. **Estados:** pendiente → en progreso → en revisión → completada / cancelada.
4. **Cambiar estado:** desplegable en la fila, con cambio automático.

**Detalle de tarea (clic en el título):**
- **Comentarios:** historial + textarea + **"Enviar"**.
- **Historial de estados:** tabla con cambios "anterior → nuevo", observación y fecha (automático).
- **"Eliminar"** en el modal.

### 14.2 Vencimientos

**Ruta:** Menú Gestión → pestaña Vencimientos

Obligaciones fiscales de cada contribuyente:

1. Elegir **Empresa** → completar **Obligación**, **Agencia** (AFIP, ARBA, municipal), **Fecha de vencimiento** → **"Agregar"**.
2. Tabla con empresa, obligación, agencia, fecha, estado.
3. **Estados:** creado → documentación recibida → documentación cargada → en control → presentado.
4. Cambiar estado con desplegable en la fila.
5. **"Eliminar"** por fila.

### 14.3 Honorarios

**Ruta:** Menú Gestión → pestaña Honorarios

Documentos de honorarios del estudio:

**Catálogos previos del estudio:**
- **Servicios:** unidades de cuenta (UC), aplica persona física/jurídica.
- **Factores de complejidad:** niveles con multiplicador (ej. "×1.0", "×1.5", "×2.0").

**Crear honorario:**
1. Elegir **Empresa** → **"Nuevo honorario"**.
2. **Valor UC** (ej. valor de la unidad de cuenta al momento del documento), **Fecha**, **Descripción**.
3. **Servicios:** por línea, Servicio + Complejidad + Cantidad. **"+ Agregar"**.
4. Guardar. El sistema calcula Total = UC × Valor UC × Factor × Cantidad por línea.
5. **"Eliminar"** por fila.

---

## 15. Administración

### 15.1 Roles y permisos

**Ruta:** Menú Administración → pestaña Roles y permisos

1. **"Nuevo rol"** → ingresar nombre. Se crea Activo.
2. Tabla: Nombre, Estado (Activo/Inactivo), **"Gestionar permisos"**.
3. Modal **"Permisos del rol: [nombre]"**: columna **Asignados** (con botón **"Quitar"**) y columna **Disponibles** (con botón **"Asignar"**). Cambios instantáneos, sin botón "Guardar".

### 15.2 Catálogo de permisos

**Ruta:** Menú Administración → pestaña Permisos

1. Código del permiso (ej. "ventas", "liquidar") + Descripción → **"Nuevo permiso"**.
2. Tabla con todos los permisos del sistema y sus descripciones.

### 15.3 Usuarios

**Ruta:** Menú Administración → pestaña Usuarios

- Muestra todos los usuarios del estudio con su nombre, rol y tipo.
- La creación de usuarios y la asignación inicial de roles la realiza el administrador del sistema.
- Tabla: Usuario, Rol, Tipo (Administrador / Estándar).

### 15.4 Utilidades

**Ruta:** Menú Administración → Utilidades

5 pestañas:

- **Catálogos base (AFIP):** consulta de catálogos AFIP de solo lectura (condiciones IVA, provincias, tipos de comprobante, tipos de documento, tipos de moneda, tipos de operación).
- **Rubros:** alta, baja y modificación de rubros.
- **Retenciones / Percepciones:** alta, baja y modificación de tipos de retención. Los provistos por AFIP son de solo lectura; se pueden crear tipos propios del estudio.
- **Conceptos:** catálogo de conceptos contables del estudio, usados para imputación contable.
- **Auditoría de operaciones:** registro de acciones realizadas en el módulo IVA (usuario, operación, comprobante, fecha y hora).

---

## 16. Apéndices

### 16.1 Glosario

**CAE:** Código de Autorización Electrónica. Número que ARCA asigna a cada comprobante electrónico autorizado.

**CF computable:** Crédito Fiscal computable. Porcentaje del IVA de una compra que el contribuyente puede deducir. Típicamente 100%, pero puede ser menor (ej. 50% para gastos parcialmente deducibles).

**CUIT:** Clave Única de Identificación Tributaria. 11 dígitos que identifican a personas y empresas ante ARCA.

**CUIL:** Clave Única de Identificación Laboral. Similar al CUIT, usada para empleados en relación de dependencia.

**DDJJ:** Declaración Jurada. Presentación fiscal ante ARCA.

**F2002:** Formulario de DDJJ de IVA. Declaración mensual del impuesto al valor agregado.

**F2051:** Formulario de IVA Simple. Régimen simplificado para pequeños contribuyentes.

**Libro IVA Digital:** Régimen de información de ARCA donde se reportan todos los comprobantes de ventas y compras en formato TXT de ancho fijo.

**NAES:** Nomenclador de Actividades Económicas. Catálogo oficial de actividades de ARCA.

**NC/ND:** Nota de Crédito / Nota de Débito. Comprobantes que ajustan facturas ya emitidas.

**Percepción:** Retención de impuestos en la fuente (IVA, IIBB, municipales) que se suma al total del comprobante.

**PV:** Punto de Venta. Número que identifica una sucursal o canal de facturación.

**SIFERE:** Sistema de Información Fiscal y de Recaudación. Régimen de información para contribuyentes de Convenio Multilateral.

**UC:** Unidad de Cuenta. Unidad de medida para calcular honorarios.

---

**Versión del manual:** 1.0 — Julio 2026. Este documento se actualiza junto con el sistema. Consultar al administrador por la versión más reciente.
