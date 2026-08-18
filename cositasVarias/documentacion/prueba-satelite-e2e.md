# Prueba de punta a punta — Satélite Visual IVA (datos reales)

Guía paso a paso para probar en la aplicación real (no en la base de datos) que la migración
histórica de Visual IVA a `ecosistema` (Etapas 0, 1 y 5 del roadmap, `analisis-satelite-visual-iva.md`
§7.7/§8/§10) funciona de punta a punta: padrón real, compras/ventas reales, resolución automática
de proveedor y de cuenta contable, y la bandeja de pendientes.

Todos los casos de esta guía usan **datos reales** de producción (no inventados) — nombres,
CUIT y montos de comprobantes que existieron de verdad en Visual IVA.

## Requisitos previos

1. Levantar el stack: `docker compose up -d` desde la raíz del repo.
2. Abrir `http://localhost:5173` (⚠️ no `127.0.0.1:5173` — rompe el login por CORS).
3. Login: `admin@admin.com` / `admin123` (usuario de desarrollo).

---

## Caso principal: REYMUNDO FRIAS S.R.L. — Diciembre 2024

Elegida porque tiene volumen real alto (95 compras, 6.786 ventas en un solo mes) y porque su
período de diciembre 2024 tiene **1 sola compra pendiente** — perfecto para ver tanto la resolución
automática como la bandeja de pendientes sin tener que buscar mucho.

- **Empresa**: REYMUNDO FRIAS S.R.L. — CUIT `30715862707` (id interno 140)
- **Período**: Diciembre 2024

### Paso 1 — Elegir empresa y período

En el header, click en **Empresa: — elegir —** → buscar "REYMUNDO FRIAS" → seleccionar.
Click en **Período: — elegir —** → seleccionar "Diciembre 2024".

*(Si el selector del header no abre — bug de UI conocido en algunas pestañas ya usadas —, cerrar la
pestaña del navegador y abrir una nueva; o navegar directo por URL:
`/empresas/140/periodos/2818/compras`.)*

### Paso 2 — Plan de cuentas real (menú **Cuentas**)

Confirma que el plan de cuentas de la empresa es el real, extraído de su Visual IVA — no un plan
genérico. Deberías ver cuentas como `4000 VENTAS`, `5000 COMPRAS`, `5002 COMBUSTIBLES Y
LUBRICANTES`, `5004 SERVICIOS Y TASAS`, etc.

### Paso 3 — Padrón único de proveedores (menú **Padrón único**)

Buscar "ACEVEDO" o "AMX ARGENTINA" — son proveedores reales que ya estaban en el Visual IVA del
estudio, no dados de alta a mano. El padrón único tiene 6.481 proveedores reales cargados.

### Paso 4 — Compras del período: ver la resolución automática (menú **Compras**)

Con la empresa+período activos, ir a **Compras**. Deberías ver:

- **95 comprobantes** de compra real de diciembre 2024.
- Un aviso arriba: *"1 comprobante sin proveedor identificado del padrón en este período"*.
- El resto (94 de 95) ya tiene el **proveedor asignado automáticamente por CUIT** contra el padrón
  — no hubo que cargarlos a mano.

Abrí (Editar) cualquier compra que no sea la pendiente, por ejemplo la de **ACEVEDO MARIO RAMON**
($33.930,01) — vas a ver que en la línea de discriminación ya tiene precargada la cuenta
**"RODADOS - GASTOS DE MANTENIMIENTO"**, resuelta sola por el motor de conceptos (sin que nadie la
haya tipeado). Otro ejemplo: la compra de **AMX ARGENTINA SOCIEDAD ANONIMA** ($346.167,24) resuelve
sola a la cuenta **"SERVICIOS Y TASAS"**.

### Paso 5 — Bandeja de pendientes: asignar el proveedor que falta

Click en **"Ver pendientes"** (el aviso amarillo de arriba). Vas a ver la compra:

- **TR ARGENTINA SOCIEDAD ANONIMA**, CUIT `33711557879`, Factura A 00006-00019252, 26/12/2024,
  $1.077.951,00.

Click en **"Asignar proveedor"** sobre esa fila → se abre el modal de edición con el
`SujetoTypeahead` → buscar por CUIT o nombre → si ese CUIT está en el padrón, lo sugiere; si no,
queda como "sujeto ocasional" (no bloquea nada, es la decisión de producto ya tomada). Guardar →
la compra desaparece de la bandeja de pendientes (el contador baja de 1 a 0).

### Paso 6 — Ventas del período (menú **Ventas**)

6.786 comprobantes de venta reales de diciembre 2024. La mayoría (6.036) están "sin cliente
identificado" — es esperado y correcto: son ventas a Consumidor Final sin CUIT (facturas B/C de
mostrador), no un error de la migración. Los ~750 restantes sí resuelven cliente automáticamente
(clientes con CUIT real, facturas A).

### Paso 7 — Libro IVA / DDJJ (menú **Libro IVA / DDJJ**)

Con los 95 comprobantes de compra + 6.786 de venta ya cargados, el Libro IVA muestra los totales
reales del período (débito fiscal, crédito fiscal, saldo de IVA) calculados sobre datos de
producción reales — no un ejemplo armado. Recorré las pestañas Resumen, DDJJ F2002, Reportes
(subdiario) y Descargas.

### Paso 8 — Reportes de Mayor (menú **Reportes de Mayor**)

Esta es la prueba de que la clasificación automática por cuenta realmente sirve para algo más que
mostrarse bonita en el modal: **Reportes de Mayor** cruza todos los períodos de la empresa por
rango de fechas e imputa el neto de cada línea a su cuenta.

1. Completar **Desde**: `01/01/2015`, **Hasta**: `31/12/2026` (cubre toda la migración histórica).
2. Dejar **Cuenta / Provincia / Origen** en "Todas"/"Todos".
3. Click **Generar**.

⚠️ Con todo el histórico de una empresa de alto volumen esta consulta puede tardar — si no
responde en un minuto, acotar el rango de fechas a un semestre o un año.

Vas a ver la cascada Cuenta → Proveedor con los montos reales agrupados — por ejemplo, toda la
plata que REYMUNDO FRIAS le pagó a "AMX ARGENTINA" a lo largo de los años, agrupada bajo "SERVICIOS
Y TASAS", sin que nadie haya tenido que armar esa cuenta a mano comprobante por comprobante.

---

## Caso alternativo, más chico y controlado: Etapa 0 (MADASA S.R.L., agosto 2026)

Si el caso de arriba resulta pesado para una demo en vivo (95+6.786 comprobantes), este es el caso
mínimo armado a propósito para probar el mismo flujo con muy pocos datos:

- **Empresa**: MADASA S.R.L. — CUIT `30714327638`
- **Período**: Agosto 2026
- 3 compras (2 matchean proveedor real automáticamente, 1 queda en la bandeja de pendientes a
  propósito, con un CUIT válido pero inexistente en el padrón: `20111111112`) + 2 ventas (Factura B
  a Consumidor Final).

Mismo recorrido que arriba (Compras → ver aviso de pendientes → Ventas), pero con 5 comprobantes
en vez de miles — útil para explicar el flujo sin esperar cargas pesadas.

---

## Qué significa cada resultado (para explicarle al cliente)

| Lo que ves en la app | Lo que demuestra |
|---|---|
| Plan de cuentas real de cada empresa | El plan de cuentas se migró de verdad, cuenta por cuenta, no se inventó uno genérico |
| Padrón único con 6.481 proveedores reales | Ya no hay que cargar proveedores a mano — están todos desde el primer día |
| 97,5% de las compras con proveedor automático | El sistema reconoce solo, por CUIT, quién es el proveedor de cada comprobante histórico |
| Cuenta contable ya precargada en la línea de IVA | La imputación contable (a qué cuenta va cada gasto) también se resuelve sola, con la misma lógica que va a usar para los comprobantes nuevos de acá en adelante |
| Bandeja de pendientes con 1 comprobante | El sistema avisa, no oculta, los casos que no pudo resolver solo — y dejan de estar pendientes en cuanto se los asigna una vez |
| Reportes de Mayor con años de histórico | Ya se puede sacar, hoy mismo, "cuánto le pagamos a tal proveedor en total" sin re-cargar nada |

---

## Otros casos de prueba con volumen real (por si se quiere variar el ejemplo)

Empresas con más comprobantes migrados (útiles si se quiere mostrar volumen):

| Empresa | CUIT | Ventas migradas |
|---|---|---|
| REYMUNDO FRIAS S.R.L. | 30715862707 | 153.651 |
| ARCE, PATRICIA DEL VALLE | 27329998334 | 108.853 |
| LAVALLE S.R.L. | 30715402587 | 52.293 |
| DISMAR SRL | 30709102687 | 38.907 |
| PLAZA 25 S. R. L. | 30716280353 | 37.789 |

El rango histórico completo cubierto es **10/01/2015 al 31/05/2026**, sobre 329 empresas reales.
