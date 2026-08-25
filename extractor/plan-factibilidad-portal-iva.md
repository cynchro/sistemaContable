# Plan de factibilidad — flujo Portal IVA (DDJJ → Libro Ventas/Compras → IMPORTAR)

> Reemplaza el enfoque original de scrapear "Mis Comprobantes" (bloqueado: no
> funciona con cuenta de monotributo, y aun con una RI hubiera significado
> leer una grilla de solo lectura sin garantías). Este camino usa una
> funcionalidad que ARCA ya construyó para exactamente este propósito: dentro
> de una declaración jurada de IVA, el Libro Ventas y el Libro Compras tienen
> un botón **IMPORTAR** que trae del lado de ARCA los comprobantes
> electrónicos que tiene registrados para ese CUIT+período (emitidos propios y
> — lo que nos interesa — recibidos de terceros, matcheados por CUIT
> receptor). Es más robusto que leer una tabla: es un job asincrónico nativo
> de ARCA, no HTML que puede cambiar de un día para el otro.

## ⚠️ Límite duro (no negociable)

**El automatismo entra a una DDJJ, importa, lee la grilla, y sale. Nunca
presiona nada de la familia "Presentar" / "Confirmar presentación" / "Generar
F.2002 definitivo".** Presentar una DDJJ es un acto fiscal real ante ARCA —
corresponde a la categoría de acciones que requieren decisión humana explícita
del contador, no del bot. Esto se codifica como un allowlist de botones
permitidos (Importar, Actualizar, Continuar dentro del wizard de carga,
navegación entre solapas) en vez de un blocklist de los prohibidos — más
seguro por diseño: lo que no está explícitamente permitido, no se clickea.

Relacionado, a confirmar en la exploración en vivo:
- ¿Crear "Nueva declaración jurada" para un período que ya tiene un borrador
  existente abre ese borrador o crea uno nuevo/duplicado? Si el contador
  después abre el Portal IVA a mano para presentar de verdad, no queremos que
  nuestro "borrador de lectura" le pise datos o le genere confusión.
- ¿Hay forma de descartar/eliminar el borrador después de leer los datos, para
  no dejar objetos huérfanos en la cuenta de ARCA del contribuyente?
- Si no se puede descartar limpiamente, ¿es aceptable para el estudio dejar
  ese borrador (sin presentar) como efecto colateral conocido? — esto es una
  decisión del usuario/contador, no técnica.

## Paso a paso: factibilidad de cada paso del flujo que describiste

| # | Paso | Tipo de acción Playwright | Factibilidad | Qué falta verificar en vivo |
|---|------|---------------------------|--------------|------------------------------|
| 1 | Login + entrar a "Portal IVA" | navigate + click (mismo patrón que "Mis Comprobantes": buscar el servicio, puede pedir "Agregar Servicio" la primera vez) | Alta | Si ya está agregado a la cuenta de prueba, se salta el modal de alta que vimos con "Mis Comprobantes" |
| 2 | "Nueva declaración jurada" | click | Alta | — |
| 3 | Elegir período (ej. 07/2026) + "CONTINUAR" | seleccionar un `<select>` o similar + click | Alta | Formato del selector de período (dropdown vs. dos campos mes/año) |
| 4 | "Registración y declaración" → "INGRESAR" | click | Alta | — |
| 5 | "Datos Iniciales" → "CON MOVIMIENTOS" → "CONTINUAR" | click radio/opción + click | Alta | Qué pasa si el período no tiene movimientos — ¿ofrece igual "CON MOVIMIENTOS" o lo oculta? (empresas nuevas/período flojo) |
| 6 | Solapa "Libro Ventas" → "IMPORTAR" | click tab + click botón | Alta | — |
| 7 | Esperar "Procesando" → "ACTUALIZAR" hasta que termine | **polling** — la parte más delicada | Media | Ver §Polling abajo |
| 8 | Ver ventas en la grilla | leer DOM de la tabla, **o mejor: buscar si hay export nativo** | Media-Alta | Ver §Extracción de datos abajo |
| 9-11 | Ídem para "Libro Compras" | mismo patrón que 6-8 | Media-Alta | — |
| 12 | Exportar y enviar a ecosistema | Depende de qué exista en el paso 8/11 | **Depende del hallazgo en vivo** | Ver §Extracción de datos |

### §Polling (paso 7 y 10)

"Esperá a que cambie a Procesando y presioná ACTUALIZAR hasta que finalice" es
un patrón de job asincrónico. Dos formas de automatizarlo, de mejor a peor:

1. **Mirar la red** (`read_network_requests` en la exploración en vivo): si
   "ACTUALIZAR" dispara un XHR/fetch a un endpoint tipo
   `GET /consultarEstadoImportacion?id=...`, el sidecar puede pollear ese
   endpoint directo (con la sesión de Playwright) en vez de clickear un botón
   en loop — más rápido y más robusto a cambios visuales.
2. **Clickear "ACTUALIZAR" en loop** con backoff (ej. cada 3-5s, timeout total
   de 1-2 min) y leer el texto de estado en el DOM — funciona pero más frágil
   y más lento.

Se define en la exploración en vivo cuál aplica.

### §Extracción de datos (paso 8/11/12) — esto define el diseño real

Hay tres escenarios posibles, en orden de qué tan bueno es cada uno:

**A. La grilla tiene un botón de exportar nativo (CSV/Excel/TXT).**
El mejor caso: se descarga el archivo (Playwright puede interceptar la
descarga) y se parsea con un parser tabular simple — mucho más confiable que
leer HTML de una tabla que puede paginar, tener columnas ocultas, etc. Si
existe, **usar esto siempre**, ignorar el DOM de la grilla.

**B. No hay export, pero la grilla trae los datos de un endpoint JSON interno**
(muy probable — un SPA moderno como parece ser este Portal IVA típicamente
pagina/ordena pidiéndole datos a una API propia). Con `read_network_requests`
se puede encontrar ese endpoint y llamarlo directo (misma sesión/cookies) —
segundo mejor caso, evita parsear DOM.

**C. Solo queda leer la tabla HTML fila por fila.**
El peor caso pero siempre posible con `read_page`/`find`. Frágil a cambios de
layout, hay que manejar paginación si existe.

**No se puede saber cuál de los tres aplica sin verlo en vivo.** Esto es
exactamente lo que bloqueaba la Task #3 y sigue bloqueando esta versión del
plan hasta tener credenciales de un contribuyente Responsable Inscripto real.

## Por qué este camino es mejor que "Mis Comprobantes" (aparte de que
funciona con RI)

- **Cubre ventas Y compras con el mismo mecanismo** (dos solapas del mismo
  wizard), en vez de dos fuentes distintas.
- **Para ventas, es potencialmente mejor que nuestra Auditoría ARCA actual**
  (`AUTOMATIZACIONES.md` §3, basada en WSFEv1/`FECompUltimoAutorizado`): esa
  solo puede comparar combos punto de venta+tipo+letra ya usados localmente
  (limitación documentada en §5 de ese archivo). El import del Portal IVA
  trae **todo** lo que ARCA tiene para el período, sin ese punto ciego. Vale
  la pena, más adelante, evaluar si conviene que la Auditoría ARCA use esta
  misma fuente en vez de (o además de) WSFEv1 — no es parte de este plan,
  queda anotado para después.
- **Para compras, es el único camino con algo de solidez** que encontramos:
  no es un endpoint público, pero es una función de producto de ARCA (no
  scraping de una tabla de reporting), lo que sugiere que va a ser más
  estable en el tiempo que leer "Mis Comprobantes" a mano.

## Ajustes al diseño del sidecar (respecto al plan original)

`src/scrape/comprobantes.ts` deja de ser "leer una tabla" y pasa a ser
**"conducir un wizard + esperar un job + extraer por el mejor canal
disponible (A/B/C de arriba)"**. Se reorganiza en:

- `src/flows/portalIva.ts`: navega el wizard (pasos 1-6 y 9), con un allowlist
  de acciones permitidas (nunca clickea nada de presentar/confirmar).
- `src/flows/esperarImportacion.ts`: implementa el polling (§Polling).
- `src/extract/libroVentas.ts` / `libroCompras.ts`: implementa la extracción
  según lo que se confirme en vivo (A, B o C).

La Task #3 se re-scopea a este flujo (no a "Mis Comprobantes").

## Decisiones tomadas (2026-07-26)

- **Salida: xlsx local, no push a ecosistema (por ahora).** El sidecar deja
  los comprobantes en `comprobantesIva/salida/{cuit}_{periodo}_{libro}_
  {timestamp}.xlsx`. La integración con ecosistema (tabla staging, endpoints,
  pantalla de revisión — Tasks #4/#5/#6/#7) queda pospuesta hasta decidir el
  paso siguiente con datos reales en la mano. Implementado en
  `src/output/xlsx.ts` (ExcelJS), ya funcional (no depende de ver el portal).
- **Reestructura de código**: `src/scrape/comprobantes.ts` (diseño viejo,
  "leer una tabla") se eliminó. Arquitectura nueva:
  - `src/flows/portalIva.ts` — navega el wizard (allowlist de acciones).
  - `src/flows/esperarImportacion.ts` — polling del job de importación.
  - `src/extract/libroVentas.ts` / `libroCompras.ts` — extracción (A/B/C).
  - `src/output/xlsx.ts` — escritor local (implementado).
  - `src/types.ts` — `ComprobanteScrapeado`/`PeriodoFiscal`/`Libro` compartidos.
  Todo lo de `flows/` y `extract/` son stubs que tiran error explícito hasta
  la exploración en vivo — a propósito, para no adivinar selectores.
- **Reusar un borrador de DDJJ existente en vez de crear uno nuevo**: pedido
  explícito del usuario. `abrirDdjj()` debe detectar si ya hay un borrador
  para el período pedido y reabrirlo, no crear uno adicional. Ajusta también
  la pregunta abierta de la Task #8 (menos objetos nuevos creados en ARCA si
  ya existe uno para reusar).
- **Política de credenciales para las pruebas (importante, ver también
  CLAUDE.md / reglas del asistente)**: el asistente **nunca** entra la
  clave/contraseña de ARCA en un formulario, ni siquiera si el usuario la
  comparte y autoriza explícitamente — es una restricción dura, no depende
  del canal (chat o archivo). El único flujo válido: **el usuario se loguea
  manualmente en una pestaña de Chrome**, y desde ahí el asistente continúa
  usando la sesión ya autenticada (cookies del browser), sin ver ni tocar la
  contraseña en ningún momento.

  **Actualización 2026-07-26**: para el uso normal del proyecto standalone
  (`extractor/`, ya dockerizado), el usuario decidió que `npm run login`
  **sí puede leer `ARCA_CLAVE_FISCAL` de un `.env` y automatizar el login,
  incluso headless dentro de Docker** — el punto no negociable no es "nunca
  se automatiza el login", es **"el asistente nunca lo corre ni lo ve"**. La
  persona que pone la clave en su `.env` y ejecuta `docker compose run
  extractor npm run login` es siempre un humano con su propia cuenta; el
  código (`src/auth/login.ts`) hace el fill automatizado en su nombre, igual
  que cualquier script de automatización que un dueño de cuenta corre sobre
  sí mismo. `src/auth/login.ts` se endureció para no fallar en silencio si
  ARCA pide verificación adicional (`LoginNoConfirmadoError`, con
  instrucciones de caer al modo manual headed esa vez). Detalle en
  `extractor/README.md`.
- **Matiz reconfirmado sobre "solo consulta, no guardar nada"**: crear/abrir
  una DDJJ (incluso solo para llegar al botón IMPORTAR) ya es un objeto con
  estado en ARCA, distinto de "presentar" pero no 100% sin huella. El límite
  duro sigue siendo no presentar/confirmar nunca; la Task #8 (cuenta de
  prueba vs. real) sigue abierta y se resuelve antes de tocar una cuenta que
  importe de verdad.

## Conversión a plantilla Visual IVA (2026-07-27)

Con la extracción funcionando (CSV real en `salida/`), siguiente paso del
pipeline pedido por el usuario: `extractor → csv → xlsx tipo Visual IVA →
subir al nuevo sistema`. Se probó explícitamente **sin volver a extraer**,
usando los dos CSV ya guardados de una corrida anterior.

**Arquitectura real del xls de referencia** (`flujo/Plantilla_Visual_IVA_
202605.xls`, no versionado — datos de un cliente): no es un archivo plano,
son 4 hojas. **Compras/Ventas** son las hojas "humanas" (el contador tipea,
con desplegables tipo texto: `"001 FACTURAS A"`, `"80 - CUIT"`, `"PES
PESOS"`). **Hoja2/Hoja3** (literalmente tituladas *"VISUAL IVA IMPORTACION
DE COMPRAS/VENTAS"*) son las que el sistema realmente importa — traducen
esos textos a códigos puros vía `VLOOKUP` contra tablas de referencia
ocultas en columnas lejanas (DE en adelante). Se extrajeron esas tablas
completas (tipo de comprobante: 85 códigos CITI de 3 dígitos; tipo de
documento: 9; moneda: 62; tipo de operación de compra: 8; tipo de operación
de venta: 2; actividad: 2; concepto: 3) a `src/output/catalogosVisualIva.ts`.

**Hallazgos de la comparación columna por columna** (Compras: 28 columnas
usadas de 125 en el archivo; Ventas: 31 de 127 — el resto son las tablas
ocultas):
- El código de "Tipo de Comprobante" que trae el CSV de ARCA (sin ceros a
  la izquierda) **coincide numéricamente** con el código CITI de 3 dígitos
  para todos los tipos estándar — validado contra datos reales (1→001
  Factura A, 3→003 NC A, 6→006 Factura B, 11→011 Factura C). Alcanza con
  `padStart(3, "0")`, no hace falta una tabla de traducción aparte.
- El código de moneda "PES" coincide directo (hay una entrada especial
  `"PES PESOS"/"PES"` en la tabla, distinta del resto que son códigos AFIP
  numéricos). Para "DOL" (dólar) se encontró un **typo real en la plantilla
  original**: el código guardado es `"DOL "` (con un espacio de más) — se
  resuelve comparando con `.trim()` en vez de asumir el catálogo limpio.
- La plantilla **solo soporta 4 alícuotas** (0/10,5/21/27%) — no tiene
  columnas para 2,5%/5% (no existían cuando se armó el template en 2018).
  No afectó los datos probados (ningún comprobante las usa).
- La plantilla **no tiene columna para "Importe Otros Tributos"** (ni en
  Compras ni en Ventas). Si un comprobante lo tiene (pasó con 1 de 59 en la
  prueba, $21.722,86), se pierde en este archivo — se avisa por consola,
  no se inventa dónde ponerlo. El dato real no se pierde del todo: sigue en
  el CSV original que también se guarda en `salida/`.
- **"Crédito Fiscal Computable" siempre viene vacío en el original**
  (0 de 174 filas completadas en el ejemplo real) — aparentemente el
  software de escritorio Visual IVA lo calcula solo al importar, no es un
  campo de carga manual. Confirmado cruzando con el "VERIFICADOR" de cada
  fila (fórmula `TOTAL - SUM(resto de columnas)`, que incluye esa columna
  en la suma): con Crédito Fiscal vacío el verificador da ~0; llenarlo con
  el dato real de ARCA rompe esa reconciliación para cada fila. Se decidió
  con el usuario dejarlo **siempre vacío**, igual que el original.
- **Tipo de Operación de Compra/Venta, Actividad y Concepto** no vienen en
  el CSV de ARCA (son categorización contable). Se usa el mismo criterio
  que ya tiene `ecosistema` para el DJ IVA Simple v1 (actividad principal,
  sin bienes de uso, concepto "Productos") como default fijo.

**Diferencia deliberada de diseño**: Hoja2/Hoja3 se generan con los
**valores ya calculados**, no con fórmulas `VLOOKUP` en vivo como el
original. El original las necesita porque el contador tipea directo en
Compras/Ventas; acá el dato ya viene resuelto de ARCA, así que replicar
fórmulas solo agregaría fragilidad (dependen de que Excel/LibreOffice
recalcule al abrir) sin ganar nada para el consumidor final.

**Validación**: se generó el xlsx contra los dos CSV reales (51 compras +
8 ventas) y se forzó el recálculo real de fórmulas con LibreOffice headless
(`soffice --convert-to xlsx`, que sí recalcula). Resultado: **58 de 59
comprobantes reconcilian el VERIFICADOR a ~0**; el único con diferencia es
exactamente el caso conocido de Otros Tributos sin columna — y el
VERIFICADOR dio **exactamente $21.722,86**, el mismo importe, confirmando
que la fórmula y el aviso por consola apuntan al mismo lugar con el monto
correcto.

Implementado en `src/output/catalogosVisualIva.ts` (catálogos),
`src/output/plantillaVisualIva.ts` (generador), `src/cli/convertir.ts`
(CLI: `npm run convertir -- --compras <csv> --ventas <csv> --salida <xlsx>`,
no toca ARCA ni Playwright — solo lee CSV ya guardados).

## Primer libro extraído de punta a punta — y el siguiente bug (2026-07-27)

Con el zip resuelto, **Libro Ventas se extrajo completo de punta a punta**:
8 comprobantes reales, xlsx guardado en `salida/`. Falló el paso siguiente
(pasar a Libro Compras) por el mismo tipo de suposición incorrecta que ya
había aparecido dos veces: `irALibro` clickeaba `#btnLibroVentas`/
`#btnLibroCompras`, pero esos botones **solo existen en la pantalla
intermedia del menú** (las 3 tarjetas) — una vez adentro de un libro (recién
se había extraído Ventas), esa página no los tiene; en su lugar hay un
botón "CONTINUAR AL LIBRO COMPRAS" distinto. Se cambió `irALibro` a navegar
**directo por URL** (`verVentas.do?t=31` / `verCompras.do?t=21`, códigos de
sección confirmados fijos) en vez de depender de qué botón esté disponible
en la pantalla actual — funciona sin importar desde dónde se llame.

## El CSV real vino comprimido en un zip (2026-07-27)

Con el fix del BOM aplicado, seguía fallando en el mismo lugar exacto — la
captura de debug del CSV crudo (agregada en el ajuste anterior) reveló por
qué: el archivo descargado **no es un CSV, es un zip** (firma `PK\x03\x04`,
confirmado con `file` y un dump hex) con un único archivo adentro
(`comprobantes_periodo_202607_ventas_...csv`). Los dos ejemplos que había
bajado el usuario a mano NO estaban comprimidos — no se sabe si depende del
tamaño del export, del navegador, o de alguna otra condición del lado de
ARCA.

Se agregó `contenidoCsv()` en `extract/libroCsv.ts`: detecta la firma de zip
en los primeros bytes y, si está, lo descomprime con `jszip` (la misma
librería que usa la propia app de ARCA para generarlo — nueva dependencia
del proyecto) tomando el único archivo de adentro; si no es zip, usa el
buffer tal cual. Validado contra el zip real ya descargado en una corrida
anterior (quedó guardado en `debug/` gracias al mecanismo de diagnóstico) —
el CSV de adentro coincide exactamente con los datos que se habían visto en
la exploración en vivo (mismo comprobante, mismos importes), sin BOM, mismo
formato que los ejemplos originales.

`debug/` ahora guarda dos archivos por corrida de extracción: el archivo
crudo tal como lo entregó el browser (`descarga_{libro}_*.raw`, sea zip o
CSV) y, si hizo falta descomprimir, el CSV ya extraído (`csv_{libro}_*.csv`).

## Ajustes tras la primera corrida real end-to-end (2026-07-27)

Con los selectores del wizard y de la sesión ya arreglados, el flujo llegó
por primera vez hasta descargar el CSV real de Ventas — y falló recién ahí,
en el parseo. Dos causas encontradas y corregidas, ambas por el mismo patrón
de "no asumir, mirar la evidencia":

1. **Botón "Ingresar" no encontrado pese a estar visible en la captura**:
   mismo problema que ya había aparecido con el link "Portal IVA" —
   `getByRole('button', {name: 'Ingresar'})` depende del *nombre accesible*
   calculado (que puede estar pisado por un `aria-label` raro, como el que
   ya se había visto en otro botón de esta misma app), no del texto visible.
   Se migraron `abrirDdjj`'s clicks de "Ingresar"/"Continuar" a
   `locator("button").filter({hasText: ...})` (texto de DOM real), mismo
   criterio que ya se había aplicado al link de Portal IVA.
2. **Primer header del CSV inaccesible por nombre** (`"Fecha de Emisión"`
   devolvía `undefined`): los CSV de ejemplo que aportó el usuario eran
   ISO-8859-1 sin BOM, pero nada garantiza que el export real de esta cuenta
   sea igual — probablemente venga en UTF-8 **con BOM** (común para que
   Excel detecte el encoding solo), y decodificar eso como latin1 corrompe
   el primer header con bytes basura en vez de un `﻿` limpio. Se
   corrigió detectando el BOM en los **bytes crudos del buffer** (`EF BB
   BF`) antes de decodificar, en vez de asumir siempre latin1.

**Mejora de proceso, no solo de código**: se agregaron dos mecanismos de
diagnóstico para no seguir adivinando a ciegas en corridas reales (donde no
hay forma de "ver" el browser, corre headless):
- `cli/traer.ts` guarda una captura de pantalla en `debug/error_*.png` si
  algo falla — así se pudo confirmar visualmente que "Portal IVA" e
  "Ingresar" SÍ estaban en pantalla, descartando que fuera un problema de
  timing/contenido y apuntando al selector.
- `extract/libroCsv.ts` guarda una copia cruda del CSV descargado en
  `debug/csv_{libro}_*.csv` en cada corrida (no solo si falla) — para poder
  inspeccionar el archivo real en vez de asumir que tiene el mismo formato
  que los dos ejemplos aportados a mano.

## Hallazgo operativo: ARCA invalida la sesión rápido (2026-07-27)

Primera corrida real de `npm run traer` (usuario, cuenta propia, fuera de
esta sesión de exploración): falló en `irAPortalIva` con "no se encontró
Portal IVA en Más utilizados". Dos bugs encontrados y corregidos:

1. **Bug de timing** (mío): `irAPortalIva` chequeaba el link "Portal IVA"
   apenas navegaba, sin esperar — la sección "Servicios | Más utilizados"
   carga de forma asíncrona (confirmado en la exploración: se ve un spinner
   antes de que aparezcan las tarjetas). Se cambió a `waitFor({state:
   "visible", timeout: 15000})`.
2. **Sesión expirada** (hallazgo real, no bug de código): con el fix de
   timing igual seguía fallando. Diagnóstico: se reabrió la sesión guardada
   de forma aislada (sin tocar credenciales, solo el archivo de sesión ya
   persistido) y navegó a `.../portal/app/expiredSession` — **ARCA invalidó
   la sesión en ~42 minutos**. No es un bug de cómo se lee el storageState
   (las cookies estaban ahí, con nombre y dominio correctos); es que ARCA
   tiene un timeout de sesión corto (típico en portales fiscales/
   gubernamentales) y/o la sesión está atada a algo más que la cookie (hay
   cookies `TS...` de Akamai/bot-protection en el storageState — no se
   puede descartar que el fingerprint del proceso del browser importe).

**Implicancia de diseño**: el modelo "logueate una vez, reusá para siempre"
con el que se armó todo esto (sesión persistida en disco) no alcanza solo —
hace falta **renovar la sesión si expiró, no solo detectar que existe el
archivo**. Se agregó `src/auth/ensureSession.ts`
(`asegurarSesionVigente`): antes de arrancar el flujo, navega al home del
portal y si ARCA redirige a `expiredSession`/`login.xhtml`, vuelve a loguear
(reusando `ARCA_CLAVE_FISCAL` de `.env`, sin intervención del asistente,
mismo criterio ya acordado) y persiste la sesión renovada. Si no hay
`ARCA_CLAVE_FISCAL` disponible, tira un error claro pidiendo correr `npm run
login` de nuevo, en vez de fallar de forma confusa más adelante en el flujo.

## Resuelto: selectores reales del wizard y del botón CSV (Task #9, 2026-07-26)

Sesión de exploración adicional (misma cuenta RI, sesión todavía activa) para
completar `flows/portalIva.ts` con selectores reales en vez de los stubs que
tiraban error. Todo confirmado inspeccionando el DOM real con
`javascript_tool` (no adivinado):

| Paso | Selector confirmado |
|---|---|
| Link "Portal IVA" en "Más utilizados" | `a.full-width` con texto exacto "Portal IVA" — **abre pestaña nueva** (`target="_blank"`); un click programático (`element.click()` vía JS) queda bloqueado como popup no confiable, hace falta un click real (Playwright `locator.click()` sí genera un evento confiable, no tiene este problema) |
| "Nueva declaración jurada" → Ingresar | texto de botón "Ingresar" (único con ese texto en esa pantalla) |
| Selector de período | `select#periodo`, valores en formato **YYYYMM** (ej. `"202607"`), no MM/YYYY |
| Continuar | texto de botón "Continuar" |
| "Registración y declaración" → Ingresar | **no** por texto (hay dos botones "Ingresar" en esa pantalla) — se usa `[aria-label*="iva.btn.home.liva.alt"]`, un hallazgo casual: ARCA tiene una key de i18n sin traducir en el `aria-label`, que resulta ser un selector semántico y estable |
| Tarjeta Libro Ventas / Libro Compras | `#btnLibroVentas` / `#btnLibroCompras` (además tienen `href="verVentas.do?t=31"` / `href="verCompras.do?t=21"` — navegación directa por URL sería una alternativa) |
| Botón CSV de la grilla | **no tiene clase distintiva** (a diferencia de Excel `.buttons-excel` y PDF `.buttons-pdf`) — es el primer hijo de `.dt-buttons`. El código valida el texto ("CSV") antes de clickear, para no fallar en silencio si ARCA reordena los botones |
| Dropdown "Importar" | `#btnDropdownImportar` |
| "Importar desde ARCA..." | `#lnkImportarAFIP` |
| Modal: confirmar importar | `#btnImportarAFIPImportar` (**no ejecutado en vivo** — se abrió el modal y se canceló con `#btnImportarAFIPCancelar`, mismo criterio que la sesión anterior: no duplicar una importación ya "Procesada") |
| "Historial de Importaciones..." | `#lnkTareas` |

**Hallazgo extra que resuelve el polling** (`esperarImportacion`): el
endpoint `ajax.do?f=listaTareas&c={21|31}` (mismo que alimenta "Historial de
Importaciones") devuelve JSON con el estado de cada job —
`{"estado":"TE","progresoActualEstimado":100,...}` para el job ya
"Procesada" visto en la sesión anterior. `"TE"` = Terminada. Se implementó
el polling contra este endpoint (cada 3s, timeout 120s por defecto) en vez
de clickear "ACTUALIZAR" en loop — igual que se hizo con el CSV en vez del
endpoint JSON, se prefiere la fuente más directa disponible.

**Lo que queda sin confirmar** (documentado como tal en el código, no
asumido): el camino "período sin borrador previo" → pantalla "Datos
Iniciales / CON MOVIMIENTOS". `abrirDdjj()` lo detecta (si no aparece la
tarjeta "Registración y declaración" después de 15s) y tira un error
explícito en vez de improvisar un comportamiento no verificado.

## Resuelto: desglose por alícuota/percepciones (Task #10) — vía CSV, no vía el endpoint JSON (2026-07-26)

El usuario aportó dos archivos reales bajados a mano del botón **CSV** de la
grilla (`comprobantes_periodo_202605_{compras|ventas}_...csv`, otro cliente
y período, en `extractor/flujo/`, referenciados desde `flujo/instructivo.md`).
Análisis:

- **Mismo origen que veníamos explorando** (nombre de archivo = patrón del
  botón CSV de DataTables Buttons en Libro Ventas/Compras del Portal IVA).
- **Trae el desglose completo que el endpoint JSON no traía**: neto/IVA por
  cada alícuota (0/2,5/5/10,5/21/27%), más percepciones (IIBB, otros
  impuestos nacionales, IVA), impuestos municipales/internos, otros
  tributos, crédito fiscal computable (compras) — prácticamente 1:1 con los
  campos del modal "Detalles del Comprobante" que ya habíamos visto.
- Encoding **ISO-8859-1**, separador `;`, decimal con coma, sin separador de
  miles en estos ejemplos.

**Se reemplazó el mecanismo de extracción**: `extract/libroApi.ts` (endpoint
JSON `listaComprobantesIncluidos`) se borró; ahora `extract/libroCsv.ts`
descarga el CSV (intercepta el evento `download` de Playwright tras
clickear el botón) y lo parsea con un parser propio sin dependencias
(`extract/csv.ts`). El modelo `ComprobanteScrapeado` (`types.ts`) se amplió
para reflejar todos estos campos + un array `alicuotas[]`.

**Validado contra los dos CSV reales** (script descartable, ya borrado —
resultado documentado acá): 176 comprobantes de compras + 363 de ventas
parseados sin errores. Reconciliación (`no gravado + exento + Σ alícuotas +
percepciones + impuestos ≟ importe total`): **ventas 363/363 OK**; **compras
165/176 OK, 11 con diferencia** — las 11 son todas **Tipo de Comprobante
"11" (Factura C)** de un mismo proveedor: ARCA deja **todos** los campos de
desglose en 0 para Factura C (ni siquiera "Importe No Gravado"), porque no
discrimina IVA — no es un bug del parser, es un hueco real de la fuente,
consistente con el mismo criterio que ya tiene `ecosistema` (aviso "sin IVA,
cargá en No gravado" en `VentaFormModal`). Documentado, no "arreglado" —
haría falta decidir en el paso de integración si ese importe total se
imputa a no-gravado o se deja para carga manual.

También se encontraron comprobantes reales con **más de una alícuota**
(9 en compras, 11 en ventas) que parsearon correctamente — confirma que el
enfoque cubre el caso general, no solo comprobantes de una sola tasa.

`⚠️ Pendiente de verificar en vivo`: el selector del botón CSV
(`.buttons-csv`, clase estándar de DataTables Buttons — no confirmada contra
el DOM real, se clickeó por coordenadas en la exploración anterior) y que
`download.path()` de Playwright funcione igual dentro del contenedor Docker
(debería, Chromium headless soporta descargas sin cambios, pero no se probó
todavía end-to-end).

## Hallazgos de la exploración en vivo (2026-07-26)

Sesión con una cuenta de Responsable Inscripto real (usuario logueado por su
cuenta en Chrome, el asistente nunca vio la contraseña — ver política de
credenciales arriba). Se navegó **sin disparar ninguna importación nueva ni
tocar nada de presentar/confirmar** — todo lo que sigue es de un borrador ya
existente y de exportaciones/lecturas puras.

### 1. El flujo real (URLs y dominios)

| Paso | URL / dominio |
|---|---|
| Portal de Clave Fiscal | `https://portalcf.cloud.afip.gob.ar/portal/app/` — buscar "Portal IVA" (ya estaba adherido, no pidió "Agregar Servicio" esta vez) |
| Portal IVA (selección de período) | `https://siapweb.cloud.afip.gob.ar/iva/#/...` (SPA) |
| **Libro IVA real** (donde pasa todo lo importante) | `https://liva.afip.gob.ar/liva/jsp/...` — **dominio nuevo**, distinto de los dos anteriores. La extensión de Chrome pidió permiso aparte para este dominio (esperable: cada dominio nuevo requiere su propio consentimiento). |
| Libro Ventas | `https://liva.afip.gob.ar/liva/jsp/verVentas.do?t=31` |
| Libro Compras | `https://liva.afip.gob.ar/liva/jsp/verCompras.do?t=21` |

`t=31` / `t=21` (y el parámetro `c=31`/`c=21` del endpoint de datos, ver
abajo) son códigos fijos de sección (ventas/compras), no cambian por período.

### 2. Reutilización de borrador — confirmado

Al hacer "Nueva declaración jurada" → período 07/2026 → "Registración y
declaración" → INGRESAR, la app **saltó directo** a
`Libro IVA - Borrador Presentación 07/2026` (estado "Original - Borrador"),
**sin pasar por la pantalla "Datos Iniciales / CON MOVIMIENTOS"** — porque ya
existía un borrador con datos para ese período. Es decir: el paso 5 original
("Datos Iniciales → CON MOVIMIENTOS → CONTINUAR") **solo aparece si no hay
borrador previo**; si ya hay uno, se reabre directo. Esto simplifica
`abrirDdjj()`: no hace falta lógica especial para "detectar y reabrir", el
propio flujo de ARCA ya lo hace — alcanza con seguir los mismos clicks
siempre y manejar ambos casos (con/sin pantalla de Datos Iniciales) como una
rama condicional simple.

### 3. Historial de Importaciones — confirma que esto ya se usa en producción

El borrador ya tenía una importación previa registrada:
`24/07/2026 — Importación desde ARCA — Ejecutada 24/07/2026 19:32 — Procesada`.
O sea, este mecanismo de import ya estaba siendo usado (por el contador o por
quien administra esa cuenta) antes de que nosotros tocáramos nada — es un
flujo real y probado, no una función oscura.

### 4. El hallazgo clave: hay un endpoint JSON interno, no hace falta ni exportar ni leer HTML

La grilla de comprobantes (`Mis Ventas` / `Mis Compras`) es una
**DataTable client-side** (jQuery DataTables 1.10.16) que carga **todos los
registros del período de una sola vez** (no pagina del lado del servidor:
`recordsTotal === dataLength`, ambos 51 en el caso de Compras probado). La
trae con:

```
GET https://liva.afip.gob.ar/liva/jsp/ajax.do?f=listaComprobantesIncluidos&c={21|31}&_={timestamp}
```

(`c=21` → Compras, `c=31` → Ventas; mismo endpoint para los dos libros, el
parámetro `_` es solo cache-busting). Autenticado por **cookie de sesión**
(el fetch se probó con `credentials: 'include'` desde la consola del
navegador, sin headers extra ni token). Responde:

```json
{"estado":"ok","datos":{"consulta":null,"data":[ [...26 columnas...], [...], ... ]}}
```

Esto es **mejor que los escenarios A/B/C** que habíamos previsto en el plan
original — es directamente la fuente de datos de la grilla, sin
intermediarios. El sidecar puede llamarlo con `page.evaluate(() => fetch(...))`
reusando la sesión de Playwright, sin clickear ni un botón de exportar ni
leer una fila de HTML.

**Confirmado también por qué el click en "Excel"/"CSV" no generó ninguna
request de red**: la app carga `jszip.min.js` + `pdfmake.min.js` +
`buttons.html5.min.js` (DataTables Buttons) — los exports son **enteramente
client-side** (arman el .xlsx/.csv/.pdf en el navegador con los datos ya
cargados y disparan la descarga vía Blob). Confirma que los datos completos
del período están 100% disponibles en el cliente sin más llamadas al
servidor — el endpoint `ajax.do?f=listaComprobantesIncluidos` es autosuficiente.

### 5. Mapeo de columnas (26 posiciones, validado cruzando con el modal "Detalles del Comprobante")

| # | Campo | Ejemplo (ficticio) | Nota |
|---|-------|--------------------------|------|
| 0 | Fecha | `01/07/2026` | dd/mm/yyyy |
| 1 | Tipo de comprobante (código) | `1` | `1` = Factura A (ver `001 - FACTURA A` en el modal — el código de UI es más corto que el AFIP `CbteTipo`, mapear con cuidado) |
| 3 | Punto de venta | `2` | |
| 4 | Número | `755847` | Se muestra en UI como `00002-00755847` (PV zero-padded + número zero-padded) |
| 10 | Tipo doc. contraparte | `80` | `80` = CUIT (tabla AFIP estándar de tipos de documento) |
| 11 | CUIT contraparte | `30000000007` | emisor en Compras, receptor en Ventas |
| 12 | Denominación contraparte | `PROVEEDOR EJEMPLO S.A.` | |
| 13 | Código de moneda | `1` | `1` = Pesos argentinos |
| 14 | Símbolo moneda | `$` | |
| 15 | Neto Gravado | `41600.00` | |
| 17 | Importe No Gravado | `457.60` | confirmado contra el modal |
| 19 | Importe Exento | `0.00` | |
| 21 | IVA (total) | `8736.00` | suma de todas las alícuotas del comprobante, no discriminado por tasa en este array |
| 23 | Total | `50793.60` | |
| 25 | ID interno de ARCA | `41000000001` | único por comprobante — **candidato ideal como clave para detectar duplicados** al reimportar |
| 2, 5, 6, 7, 8, 9, 16, 18, 20, 22, 24 | — | `null` / `""` | columnas hermanas ocultas (versión "orden"/formateada de las visibles) o sin uso en estos ejemplos — no identificadas con certeza, no parecen aportar datos de negocio nuevos |

Faltan por confirmar contra un ejemplo real: **percepciones (IVA/IIBB/otros
impuestos nacionales), impuestos municipales/internos, crédito fiscal
computable, y el desglose por alícuota** (el modal "Detalles del Comprobante"
sí los muestra — Monto Neto Gravado / Importe IVA por cada tasa 0%, 2,5%,
5%, 10,5%, 21%, 27% — pero en los comprobantes probados todos esos campos
extra estaban en cero o el comprobante tenía una sola alícuota, así que no se
pudo determinar si viajan en columnas ocultas del mismo array o si hace falta
otra llamada a un endpoint de detalle por comprobante). **Se buscó el
endpoint de detalle** (se escanearon `compras.js`, `campos.js`, `mcmp.js` por
literales `f=algo` y no apareció nada nuevo — o el detalle se arma
client-side a partir de este mismo array, o el nombre de la función se
construye dinámicamente en el código y no aparece como string literal). Esto
queda como **pregunta abierta para la próxima sesión de exploración**: probar
con un comprobante que tenga percepciones y/o más de una alícuota, y volver a
mirar la red al abrir su modal de detalle.

### 6. Impacto en el diseño

- `src/extract/libroVentas.ts` / `libroCompras.ts` dejan de ser "leer DOM" o
  "descargar export" — pasan a ser un `page.evaluate(() => fetch('ajax.do?f=
  listaComprobantesIncluidos&c=NN&_='+Date.now(), {credentials:'include'}))`
  + mapeo de columnas (tabla de arriba). Mucho más simple y robusto de lo
  previsto originalmente.
- El **ID interno de ARCA** (columna 25) es la clave a guardar para no
  reimportar/duplicar comprobantes ya traídos en corridas anteriores del
  sidecar (independiente del PV+tipo+número, que técnicamente alcanza pero es
  más frágil ante NC/ND con la misma numeración de rangos).
- Sigue pendiente el desglose por alícuota/percepciones para comprobantes que
  los tengan — no bloquea el V1 (la mayoría de los comprobantes de este
  período probado son de una sola tasa), pero hay que resolverlo antes de dar
  por completa la extracción para el caso general.

## Qué falta para poder ejecutar la exploración en vivo

Credenciales de un contribuyente **Responsable Inscripto** (no monotributo) —
en gestión por el usuario. Con eso: repetir la sesión de `claude-in-chrome`
pero apuntando a "Portal IVA" en vez de "Mis Comprobantes", y esta vez con
`read_network_requests` abierto desde el arranque para capturar los endpoints
de importación/estado/exportación mientras se navega el wizard a mano.

## Preguntas para el usuario/contador (no técnicas, de política)

1. ¿Es aceptable dejar un borrador de DDJJ "de prueba" (sin presentar) en la
   cuenta de ARCA de un contribuyente real, si no se puede descartar
   limpiamente? ¿O conviene primero probar todo esto contra un contribuyente
   de juguete/homologación antes de tocar una cuenta real de un cliente del
   estudio?
2. Una vez que el import funcione: ¿el flujo debe correr **por comprobante
   consultado por el usuario** (bajo demanda, como pediste al principio —
   "traigo, edito, marco auditado, devuelvo") o **una vez por período** (trae
   todo el mes de un saque, y de ahí el usuario audita en la pantalla de
   revisión)? El wizard de Portal IVA es naturalmente "por período", no por
   comprobante individual — encaja mejor con la segunda opción.
