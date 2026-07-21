# Flujos de uso del sistema (paso a paso)

Guía funcional de los flujos principales del frontend, pensada para explicarle al usuario
del estudio contable cómo se usa el sistema. Cada flujo indica su **prerrequisito** (contexto
que hay que tener activo antes de empezar) y los pasos tal como aparecen en la pantalla.

> Nota general: todos los flujos de Ventas, Compras, Libro IVA e Importar requieren tener
> seleccionada una **Empresa** y un **Período** (selector en el header). Si el período está
> **Cerrado**, la carga e importación de comprobantes queda bloqueada.

---

## 1. Importar comprobantes desde ARCA/CSV

**Ruta:** Empresas → (empresa) → Períodos → Importar comprobantes
**Prerrequisito:** empresa y período activos. Si el período está cerrado: "El período activo
está **cerrado**: no se pueden importar comprobantes. Abrilo desde Períodos para poder cargar."

1. Elegir el destino con los botones **"Compras (recibidos)"** / **"Ventas (emitidos)"** (por
   defecto, Compras).
2. Clic en **"Archivo CSV"** y seleccionar el archivo. Se detecta automáticamente el separador
   (`;`, `,` o tabulador).
3. Auto-mapeo por nombre de columna (pensado para el export "Mis Comprobantes" de ARCA):
   reconoce encabezados como Fecha, Punto de Venta, Nro. Doc, Denominación, Neto Grav.,
   Alícuota, IVA, Importe Total, No Gravado, Exento, Internos, etc., y precarga el desplegable
   de **"Mapeo de columnas"** correspondiente.
4. Opcional — **Perfil de mapeo**: aplicar un mapeo guardado antes (los perfiles quedan en el
   navegador, indexados por nombre de columna, así sirven aunque el archivo cambie el orden).
   Botones **"Guardar mapeo actual…"** y **"Borrar perfil"**.
5. Revisar/corregir cada campo mapeado (Fecha y Neto gravado son obligatorios).
6. Si hay percepciones/retenciones: **"+ Agregar percepción"** (ventas) o **"+ Agregar
   retención"** (compras) — se elige la columna de importe y el tipo del catálogo.
7. Si el archivo trae más de una alícuota por fila (ej. resumen bancario 21% + 10,5% en
   columnas separadas): **"+ Agregar alícuota"** en "Alícuotas adicionales" (columna del neto +
   alícuota fija + columna de IVA opcional).
8. Regla de alícuota derivada: si no se mapea la columna de alícuota pero sí la de IVA, el
   sistema la deduce comparando IVA/neto contra las alícuotas vigentes (0/2,5/5/10,5/21/27); si
   no puede deducirla, usa 21% por defecto. Si se mapea el IVA, ese valor se respeta tal cual.
9. **Vista previa** (primeras 8 filas), coloreada por fila: OK (verde), con aviso (amarillo —
   ej. "Fecha fuera del período", "CUIT sin 11 dígitos", "Sin nombre/razón social") o con error
   (rojo — ej. "Falta la fecha", "Sin importes"). Resumen con badges de válidas/aviso/error.
10. Si hay filas con error: check **"Omitir las N fila(s) con error"** (tildado por defecto).
11. Clic en **"Importar N comprobante(s) a Ventas/Compras"**.
12. Resultado: "**X** de Y comprobantes creados" (verde sin errores, amarillo con errores; si
    falló alguna fila en el backend, se lista el error puntual de cada una).

---

## 2. Alta de venta

**Ruta:** Empresas → Períodos → Ventas → **"Nueva venta"**

1. **Fecha*** (obligatoria), **Tipo de comprobante**, **Letra**.
2. **Punto de venta**, **Número**, **Tipo de operación**.
3. Opcional: **Número hasta** (rangos), **CAI/CAE**, **Vto. CAI/CAE**.
4. Opcional: **Actividad (IVA/IIBB)** (si se deja vacío, se resuelve por punto de venta),
   **Rubro (F2002)**, **Cuenta Debe/Haber** (mayorización), checkboxes **"Bien de uso"** y
   **"Comprobante anulado"** (despliega "Fecha de anulación").
5. **Cliente/Razón social**: buscador tipo autocompletar (typeahead) por nombre o CUIT contra
   el padrón de clientes de la empresa; al elegir uno se autocompletan CUIT, Condición IVA y
   Provincia. Sin selección, queda como cliente ocasional con el texto tipeado.
6. Completar **Tipo de documento**, **Condición IVA**, **Provincia** si no vinieron del padrón.
7. **Discriminación de IVA**: Neto gravado + Alícuota % por línea; IVA opcional (vacío = se
   calcula solo; completo = sobreescribe el cálculo). **"+ Agregar línea"** para más alícuotas.
   - Letra **C** → aviso: "no lleva IVA discriminado, cargá el importe en Neto no gravado".
   - Tipo **Factura T** → aviso: el sistema iguala el reintegro al IVA para que el débito
     fiscal neto quede en cero.
8. **Percepciones**: **"+ Agregar percepción"**, elegir Tipo (catálogo) y opcionalmente
   Alícuota/Base/Provincia/Importe (vacíos = los calcula el sistema).
9. **Comprobantes asociados (NC/ND)**: **"+ Agregar asociado"** para referenciar la factura
   original (punto de venta y número obligatorios).
10. Opcional: **Neto no gravado**, **Exento**, **Imp. internos**. Si todo cae en
    exento/no gravado sin neto gravado: aviso "Esta venta no tiene neto gravado... Revisá antes
    de guardar."
11. Opcional: Moneda, Cotización, Campo auxiliar. Se muestra un **Total estimado** en pantalla
    (el definitivo lo calcula el backend).
12. Clic en **"Guardar"** (los errores de validación del backend se muestran arriba del modal).

---

## 3. Alta de compra

**Ruta:** Empresas → Períodos → Compras → **"Nueva compra"** (con desplegable de presets al lado)

1. **Presets de comprobante manual** (para cargas típicas fuera de ARCA): Resumen bancario,
   Ticket combustible (cód. 81), Cuota de préstamo, Liquidación de tarjeta, Servicio público
   (luz/agua), Póliza de seguro. Cada preset precarga tipo de comprobante, letra, concepto DJ
   IVA y las líneas de alícuota típicas, y muestra la convención de numeración a usar (ej.
   resumen bancario: "Punto de venta = nº de banco (CBU) · Número = MMAAXXXX").
2. **Fecha***, **Tipo de comprobante**, **Letra**, **Punto de venta**, **Número**, **Tipo de
   operación**.
3. Opcional: **CAI/CAE** y **Vto. CAI/CAE**.
4. **Proveedor/Razón social**: mismo typeahead que en ventas (autocompleta CUIT/Condición
   IVA/Provincia, o queda como proveedor ocasional).
5. **Condición IVA**, **Provincia**, **Actividad (IIBB)**, **Rubro (F2002)** y **Concepto (DJ
   IVA)**: 1-Compras de bienes, 2-Locaciones (alquileres), 3-Servicios (luz/agua/gas/tel.),
   4-Inversiones en bienes de uso (default "Bienes"). También Cuenta Debe/Haber.
6. **Discriminación de IVA**: Neto gravado, Alícuota %, IVA (opcional, override), **CF
   computable** (vacío = 100% del IVA de la línea) y Cuenta (mayor) por línea. **"+ Agregar
   línea"**.
   - Letra C → mismo aviso que en ventas.
7. **Retenciones / Percepciones**: **"+ Agregar"**, Tipo + Alícuota/Base/Provincia/Importe.
8. **Neto no gravado**, **Exento**, **Imp. internos** y **Total del comprobante** (importe real
   de la factura, opcional).
9. Si el Total informado difiere de neto+IVA calculado: aviso amarillo "difiere en $X. En
   seguros ese resto suele ser impuesto interno no discriminado" + botón **"Imputar a Imp.
   interno"** (carga la diferencia automáticamente).
10. Opcional: Moneda, Cotización, Campo auxiliar. **Total estimado** en pantalla.
11. Clic en **"Guardar"**.

---

## 4. AFIP/ARCA — Padrón, autocompletar, puntos de venta, Emitir CAE

### 4.1 Consulta de padrón
**Ruta:** menú AFIP → "Consulta de padrón (ARCA)"
1. Ingresar **CUIT** (11 dígitos, sin guiones).
2. Clic en **"Consultar"** (deshabilitado si no tiene 11 dígitos).
3. Resultado: Denominación, CUIT, Tipo/Estado, Domicilio e Impuestos inscriptos. Si falla: "No
   se pudo consultar el padrón. Verificá el CUIT y la conexión con ARCA."

### 4.2 Autocompletar por CUIT (empresa / cliente / proveedor)
- **Empresa nueva**: cargar CUIT → **"Obtener datos de AFIP"** → autocompleta Nombre,
  Domicilio, Localidad, Provincia, Inicio de actividades, Actividad principal/secundaria y
  Condición IVA (matcheada por texto: monotributo/exento/responsable inscripto). Éxito: "Datos
  traídos del padrón de ARCA. El teléfono no lo publica ARCA: cargalo a mano." Error: "No se
  pudo consultar el padrón (¿certificado de ARCA?)."
- **Cliente/Proveedor nuevo**: botón **"AFIP"** junto al CUIT; autocompleta Nombre, Domicilio
  y Localidad.

### 4.3 ABM de puntos de venta
**Ruta:** menú AFIP → "Puntos de venta"
1. Elegir **Empresa**.
2. Completar **Número** y **Descripción** → **"Agregar"**.
3. Tabla: Número, Descripción, Emisión (CAE), Estado (Activo/Inactivo).
4. **"Eliminar"** por fila con confirmación.

### 4.4 Emitir CAE desde el listado de ventas
**Ruta:** Empresas → Períodos → Ventas
1. En cada fila sin CAE aparece **"Emitir CAE"**.
2. Al hacer clic, se solicita el CAE a ARCA (WSFEv1).
3. Resultado: "CAE **XXXXXXXXXXXXX** obtenido (vence AAAA-MM-DD)" o el error devuelto por
   ARCA/backend.
4. La columna CAE pasa a mostrar el badge verde "CAE".

---

## 5. Libro IVA / DDJJ

**Ruta:** Empresas → Períodos → Libro IVA — 6 pestañas: Resumen, DDJJ (F2002), IVA Simple
(F2051), Reportes, Mayor, Descargas.

- **Resumen**: Total ventas / IVA débito / Total compras / IVA crédito + "Saldo de IVA del
  período" (débito − crédito) + detalle por alícuota (ventas y compras, con CF computable).
- **DDJJ (F2002)**: débito fiscal (ventas) y crédito fiscal (compras) por alícuota + tarjeta
  final con Débito fiscal, Crédito fiscal computable y Saldo técnico (rojo si a pagar, verde
  si a favor).
- **IVA Simple (F2051)**: "Determinación del impuesto" y "Posición mensual" con los renglones
  oficiales (débito, crédito computable, saldos técnicos y de libre disponibilidad anteriores,
  retenciones/percepciones/pagos, saldo a pagar). Campo **"Ret./perc./pagos sufridos del
  período"** + botón **"Presentar DDJJ del período"** → "✓ DDJJ presentada." (los arrastres de
  meses anteriores se toman automáticamente de la DDJJ ya presentada del período previo).
- **Reportes**: subdiario de ventas y de compras (con totales) y percepciones/retenciones por
  tipo y provincia; **"Imprimir / PDF"** (impresión del navegador).
- **Mayor**: saldos por cuenta contable (Debe/Haber/Saldo/Movimientos); clic en una cuenta →
  detalle de comprobantes imputados. Sin mayorización: aviso "Asigná una cuenta por línea
  (neto) o la cuenta Debe/Haber (total) al cargar ventas o compras."
- **Descargas**:
  - Subdiario CSV/TXT (Ventas/Compras).
  - Libro IVA Digital (Portal IVA de ARCA): comprobantes, alícuotas y anulados (ventas y
    compras).
  - DJ IVA Simple por actividad: Débito fiscal, Restitución de débito, Crédito fiscal,
    Restitución de crédito.
  - SIFERE Convenio Multilateral V4: elegir **Jurisdicción (provincia)** → **"Descargar
    percepciones"**.
  - Si hay Facturas T en el período: aviso con el reintegro total a informar a mano en el
    aplicativo de ARCA (los archivos no lo incluyen).

---

## 6. Sueldos

**Ruta:** menú Sueldos (elegir Empresa; pestañas Legajos, Conceptos, Liquidaciones)

### 6.1 Legajos
1. **"Nuevo legajo"** → legajo, apellido/nombres, CUIL, fecha de ingreso, básico, activo.
2. Guardar. Tabla: Legajo, Apellido y nombre, CUIL, Ingreso, Básico, estado.

### 6.2 Conceptos
1. **"Nuevo concepto"** → Código, Descripción, Tipo (Remunerativo/No remunerativo/Descuento),
   Fórmula.
2. Guardar.

### 6.3 Liquidar
1. Completar **Período** (ej. "2026-01"), Descripción, Fecha de pago → **"Nueva liquidación"**
   (estado Abierta/Bloqueada).
2. **"Liquidar"** en la fila → modal **"Liquidar — [período]"**.
3. Elegir **Empleado** (carga sus novedades previas o una fila vacía).
4. **Novedades**: por fila, Concepto + Cantidad + Importe; **"+ Agregar"** para más líneas.
5. **"Guardar novedades"** (sin liquidar) o **"Liquidar"** (guarda y liquida en un paso).
6. Resultado: **Recibo** con detalle de líneas (Concepto, Cantidad, Haberes, Descuentos) y
   totales de Haberes, Descuentos y Neto.
7. Si está bloqueada: "La liquidación está bloqueada; no se puede liquidar" (campos
   deshabilitados).

---

## 7. Gestión del estudio

**Ruta:** menú Gestión (pestañas Tareas, Vencimientos, Honorarios)

### 7.1 Tareas
1. **"Nueva tarea"** → **Título*** (obligatorio), Tipo, Contribuyente (empresa o "interna del
   estudio"), Prioridad (baja/media/alta), Fecha límite, Descripción.
2. Tabla: Título (link), Contribuyente, Prioridad, Vence, y un desplegable de **Estado** en la
   fila que aplica el cambio al seleccionarlo.
3. Clic en el título → modal de detalle con:
   - **Comentarios**: lista + textarea + **"Enviar"**.
   - **Historial de estados**: cambios "anterior → nuevo" con observación y fecha (automático).
4. **"Eliminar"** por fila.

### 7.2 Vencimientos
1. Elegir **Empresa** → completar **Obligación**, **Agencia**, **Fecha de vencimiento** →
   **"Agregar"**.
2. Tabla con desplegable de **Estado** en la fila (cambia al seleccionarlo).
3. **"Eliminar"** por fila.

### 7.3 Honorarios
1. Elegir **Empresa** → **"Nuevo honorario"**.
2. **Valor UC**, **Fecha**, **Descripción**.
3. **Servicios**: por línea, Servicio (catálogo) + Complejidad (factor, ej. "×1.5") +
   Cantidad; **"+ Agregar"**.
4. Guardar. Tabla: Fecha, Descripción, Valor UC, Total (calculado por el backend).
5. **"Eliminar"** por fila.

---

## 8. Administración

**Ruta:** menú Administración (pestañas Roles y permisos, Permisos, Usuarios)

### 8.1 Roles y permisos
1. Nombre del rol → **"Nuevo rol"**.
2. Tabla con estado (Activo/Inactivo) y **"Gestionar permisos"**.
3. Modal **"Permisos del rol: [nombre]"**: columna **Asignados** (cada uno con **"Quitar"**) y
   columna **Disponibles** (cada uno con **"Asignar"**). Cambios instantáneos, sin botón
   "Guardar" separado.

### 8.2 Permisos (catálogo)
1. **key** (ej. `iva.ventas`) + Descripción → **"Nuevo permiso"**.

### 8.3 Usuarios
- Solo lectura desde la UI: "Los usuarios se crean por seeder (CLI):
  `php seeders/AdminSeeder.php <email> <clave> "<estudio>"`. Desde acá se ven y se gestionan sus
  roles." Tabla: Usuario, Rol, Tipo (Desarrollador/Estándar). **No hay alta de usuarios desde
  el frontend.**

---

## 9. Empresas y Períodos

### 9.1 Alta de empresa (con ARCA)
**Ruta:** menú Empresas → **"Nueva empresa"**
1. Ingresar **CUIT** → **"Obtener datos de AFIP"** (autocompleta, ver 4.2).
2. Completar/corregir **Nombre / Razón social*** (obligatorio), Dirección, Localidad,
   Provincia, Teléfono, Condición IVA, Email.
3. Elegir **Actividad principal** y **Actividad secundaria** (catálogo AFIP).
4. Completar Inicio de actividad e Ingresos Brutos.
5. **"Guardar"**.
6. En el listado: **"Abrir"** (desplegable a Períodos/Clientes/Proveedores/Actividades de esa
   empresa), **"Editar"**, **"Eliminar"** (con confirmación).

### 9.2 Períodos — crear, cerrar, abrir
**Ruta:** Empresas → (empresa) → Períodos
1. **"Nuevo período"** → Nombre (ej. "2026-01"), Desde, Hasta → **"Guardar"**.
2. Tabla: Nombre, Desde, Hasta, Estado (Abierto/Cerrado) + accesos directos a Ventas, Compras
   y Libro IVA de ese período.
3. **"Cerrar"** (bloquea altas/ediciones de comprobantes e importaciones) o, si ya está
   cerrado, **"Abrir"** para reabrirlo.
4. **"Eliminar"** con confirmación.
