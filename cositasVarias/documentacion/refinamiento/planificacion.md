# Plan de acción — respuesta al informe del cliente (10/08/2026)

Complementa a `documentacion/refinamiento/analisis.md` (léase primero: ahí está el detalle
verificado punto por punto). Este documento define **cómo y en qué orden** actuar.

**Regla de oro para esta ronda**: no se programa nada de fondo (Fases 3, 4, 5) hasta cerrar la
Fase 0. El propio informe del cliente es una queja contra el patrón "construir cosas no pedidas" —
repetirlo acá, aunque sea con buena intención, sería el mismo error otra vez.

**Convención de esfuerzo**: 🟢 Bajo (horas, un archivo) · 🟡 Medio (días, un flujo/pantalla) ·
🔴 Alto (requiere insumo externo o diseño nuevo de fondo).

---

## Fase 0 — Preguntas para resolver con el cliente antes de programar

Estas preguntas van en la respuesta formal al cliente. Ninguna fase de código de fondo (3, 4, 5)
arranca sin sus respuestas.

### La pregunta que precede a todas las demás

`analisis.md` §0 identifica con certeza que el "SIGE" es `sistemaCuarto` (Laravel real, en
producción, del propio estudio) y que la duplicación que el cliente reclama no fue un accidente:
`docs/ingenieria-inversa/ecosistema-unificacion.md` (15/06/2026, de este mismo repositorio) define
la estrategia del proyecto como *"un único sistema homogéneo que reemplace 3 aplicaciones"*,
incluyendo explícitamente a sistemaCuarto. El informe del cliente del 10/08 dice lo contrario
("busquemos la forma de unirlo") — pide integrar, no reemplazar.

**Antes de las 12 preguntas puntuales de abajo, hay que confirmar el giro de estrategia en sí**:
¿confirmás que se abandona el plan de "reemplazar" tu SIGE (`ecosistema-unificacion.md`) por uno de
"integrar sin reemplazar"? Si es así, ¿qué hacemos con lo que ya se construyó siguiendo el plan
viejo (módulos Fiscal/Tareas/Honorarios/Contribuyentes, que replican partes de sistemaCuarto)? Las
opciones no son excluyentes y conviene que las vea antes de elegir:
- **(a) Ocultar y congelar**: dejan de mostrarse en el menú, el código queda inactivo por si hace
  falta más adelante (es la Fase 1 de este documento, ya reversible).
- **(b) Reconvertir en integración**: en vez de ser pantallas de carga manual, se convierten en una
  vista de solo lectura que consume datos de sistemaCuarto (aprovechando que ya tiene endpoints de
  sync, ver pregunta 1) — mantiene el valor de tener todo en un solo lugar, sin duplicar la carga.
- **(c) Eliminar**: se borra el código, asumiendo que sistemaCuarto sigue siendo la única fuente de
  esa funcionalidad para siempre.

1. **Sincronización SIGE ↔ empresas — ya no es una pregunta completamente abierta**:
   `sistemaCuarto/routes/api.php:51` ya expone `GET /api/sync/clientes`
   (`SyncController::clientes`), que hoy devuelve `Persona` activas con CUIT y credenciales AFIP —
   pensado para alimentar un bot externo ("HaddyBot"), no para nosotros. La pregunta concreta:
   ¿se puede extender ese mismo endpoint (o agregar uno análogo) para traer los campos completos de
   empresa (razón social, domicilio, forma jurídica, actividad, etc.), y quién lo mantiene del lado
   de sistemaCuarto? *Por qué importa*: sin esto, "una sola carga" (pedido 2a y 3) no se puede
   prometer solo con cambios de este lado — pero ahora sabemos que la pieza técnica de partida ya
   existe, así que la pregunta es de alcance y ownership, no de factibilidad.

2. **Fuente de roles y permisos**: ¿el SIGE puede ser la fuente única de verdad de permisos, o este
   sistema mantiene un control de acceso mínimo interno (aunque sea sin pantalla de administración)?
   *Por qué importa*: define si se puede sacar por completo la pantalla de Roles y permisos o solo
   simplificarla.

3. **Vencimientos**: confirmar que se elimina/oculta por completo del sistema nuevo — no porque
   "esté vacío", sino porque no se quiere esa funcionalidad acá. *Por qué importa*: evita
   reconstruirlo más adelante por inercia.

4. **Actividades**: mostrarle que es un motor de cálculo de IVA interno (no un catálogo de
   actividades económicas como el del SIGE) y validar si, entendiendo eso, todavía quiere que se
   saque del menú de primer nivel. *Por qué importa*: eliminarlo rompe la DJ IVA Simple; hay que
   confirmar que el pedido es sobre la exposición, no sobre el motor.

5. ~~**Destino real del satélite**~~ — **RESUELTO (16/08/2026)**: el destino es nuestro propio
   sistema (`ecosistema`), no el programa contable externo del cliente ni `sistemaCuarto`.
   Verificado: `sistemaCuarto.compra_ventas` no tiene campo de cuenta contable y ninguna vista la
   muestra — no hay nada ahí para clasificar. La Contabilidad del pedido 6/1 del informe converge
   directo con lo ya construido en Cuentas → Mayorización → Reportes de Mayor (Fase 4/6), sin
   exportación a ningún sistema externo.

6. ~~**Formato del archivo del satélite**~~ — **Ya no aplica**, consecuencia directa de la pregunta
   5: no hay capa de exportación que construir, así que no hace falta ningún formato de archivo.

7. **Dataset real para el satélite/padrón**: ¿se puede usar ya `Relacion_Contribuyente_
   Proveedor.xlsx` (376.819 filas) para poblar `empresas`/`cuentas` de desarrollo con datos reales,
   o hace falta otro dataset? ¿Quién arma el crosswalk `CUENTA_ID` legacy → cuenta del plan nuevo
   (el estudio, con las 30 personas que ofreció, o desarrollo)? *Por qué importa*: bloquea la
   Etapa 1 de la Fase 5c (depuración inicial del padrón) por completo sin esta respuesta.

8. **Concepto contable por defecto obligatorio**: al separar el padrón en proveedores/clientes
   (Fase 3), ¿el concepto contable por defecto pasa a ser obligatorio en el alta de un proveedor
   (bloqueante), o sigue siendo opcional con una advertencia visible? *Por qué importa*: el
   cliente objetó explícitamente que hoy sea "se pide al cargar cada compra" — hay que decidir el
   nivel de fricción aceptable en el alta.

9. **Seguimiento del programa contable**: el cliente se comprometió a mandar en 2 días (desde el
   10/08) la estructura de su programa contable — el mismo insumo que responde también la
   pregunta 5. Si no llega, fijar una fecha de seguimiento explícita. *Por qué importa*: bloquea el
   diseño fino de la conexión Cuentas ↔ Contabilidad (Fase 4) y la definición del destino del
   satélite (Fase 5).

10. **Permisos por contribuyente**: ¿es un requisito de lanzamiento que un operador pueda limitarse
    a un cliente/contribuyente puntual, o alcanza con documentar la limitación actual (permiso
    global al tenant) por ahora y dejarlo en el backlog? *Por qué importa*: define si el scoping de
    permisos entra en esta ronda o queda pendiente.

11. **Concurrencia**: dado el tamaño del equipo que va a operar el sistema, ¿amerita invertir ya en
    algún mecanismo de aviso/lock (aunque sea liviano, tipo "Fulano está editando esto"), o se
    acepta el riesgo *last-write-wins* por ahora? *Por qué importa*: evita construir algo que el
    cliente no necesita todavía, en línea con su propia queja del informe.

12. **HaddyBot vs. `extractor/`**: `sistemaCuarto` ya tiene un bot Python en producción
    ("HaddyBot") que scrapea ARCA y carga comprobantes vía `POST /api/compra-venta/import`. Este
    mismo repositorio tiene, por separado, `extractor/` (Node/Playwright, standalone, sin conectar
    a nada todavía) resolviendo el mismo problema. ¿HaddyBot debería ser la única fuente de
    extracción de ARCA (y `extractor/` se descarta o se reorienta a otra cosa), o hay una razón
    para mantener las dos? *Por qué importa*: evita construir una tercera vez lo que ya se resolvió
    dos veces.

---

## Fase 1 — Ocultar / deprioritizar (🟢 bajo esfuerzo, reversible)

Es la opción **(a) "Ocultar y congelar"** de la pregunta de estrategia de la Fase 0 — el paso
mínimo que se puede dar ya, sin esperar si el cliente prefiere en cambio (b) reconvertir estas
pantallas en integraciones de solo lectura contra sistemaCuarto, o (c) eliminarlas. Se puede hacer
en paralelo a la Fase 0 porque no compromete nada de fondo: es solo navegación, no borra datos ni
funcionalidad de backend. Si el cliente cambia de opinión en algún punto de la Fase 0, se revierte
en minutos.

**Archivo único a tocar**: `frontend/src/layout/nav.ts` (punto único de verdad del menú).

- Sacar/ocultar el grupo **"Estudio"** completo, ítem "Vencimientos y tareas" (línea 162) — resuelto
  por la pregunta 3 de la Fase 0.
- Reubicar el ítem **"Actividades"** (líneas 111-116) fuera del nivel superior del grupo IVA —
  moverlo a configuración avanzada dentro de la ficha de empresa. Depende de la pregunta 4.
- Evaluar ocultar/simplificar **"Roles y permisos"** (grupo "Administración", línea 171) según la
  respuesta a la pregunta 2.
- **No se toca backend en esta fase** — Fiscal y Admin siguen funcionando por debajo, solo se
  oculta la navegación. Esto es intencional: permite revertir rápido y evita romper por accidente
  algo de lo que otra parte del sistema todavía dependa indirectamente (ej. `empresa_id` como FK).

---

## Fase 2 — Bug de Períodos: diagnóstico en vivo antes de arreglar a ciegas

Por análisis estático de código (`frontend/src/modules/periodos/PeriodosList.tsx` y
`PeriodoFormModal.tsx`, `backend/app/Modules/Compartido/Controllers/PeriodoController.php`), el
listado y el endpoint `GET /empresas/{id}/periodos` **están alineados** — no hay una discrepancia
de campos ni de ruta que explique el error. El mensaje "No se pudieron cargar los períodos"
(`PeriodosList.tsx:84`) es el catch-all genérico de React Query ante *cualquier* fallo HTTP
(401/403/404/500/red/CORS) — **la causa raíz real no se pudo confirmar sin reproducir en vivo**.

**Pasos de diagnóstico, en este orden**:

1. Reproducir en el navegador (con las herramientas de red de Chrome/DevTools) el request real a
   `GET /empresas/{id}/periodos` desde la cuenta y empresa donde el cliente vio el error. Capturar
   el código de respuesta exacto.
2. Revisar los logs del backend en el momento del fallo (`backend/app/Modules/Compartido/`).
3. Probar el guardado (`POST /empresas/{id}/periodos`) por separado: capturar el payload enviado y
   la respuesta — determinar si es un error de validación silencioso (`CreatePeriodoRequest.php`
   exige `fecha_ini`/`fecha_fin` en formato `Y-m-d`, ambos obligatorios), un error 500, o si el
   modal ni siquiera llega a disparar el request.
4. Descartar que sea un problema de datos de la cuenta de prueba (tenant/empresa sin período
   previo, permiso faltante) antes de asumir que es un bug de código.

**Recién con la causa confirmada**, definir el fix. Se separa intencionalmente de la Fase 3 (el
rediseño del campo `nombre` a un selector parametrizado es un cambio de UX aparte del bug de
guardado/carga, y no conviene mezclar ambos en el mismo commit para poder aislar causas).

---

## Fase 3 — Rediseño real (🟡 esfuerzo medio, no depende de insumo externo)

Se puede empezar apenas cierre la Fase 0 (usa sus respuestas para el nivel de fricción en cada
caso, pero el trabajo de código en sí no depende de un dato externo del cliente).

- **Selector de período parametrizado**: reemplazar el `CFormInput` de texto libre en
  `PeriodoFormModal.tsx:55` por un selector de mes/año (o equivalente) que autocompleta
  `fecha_ini`/`fecha_fin` y arma el `nombre` de forma estandarizada (ej. "JULIO 2026"). Agregar
  validación de unicidad por empresa+período en el backend
  (`backend/app/Modules/Compartido/Services/PeriodoService.php`) para que no puedan coexistir dos
  períodos con el mismo nombre en la misma empresa.

- **Banner de contexto persistente**: nuevo componente que muestre "Contribuyente X · IVA ·
  Período Y" de forma fija en las pantallas de trabajo (Ventas, Compras, Libro IVA, etc.), leyendo
  del mismo `ActiveContext` (`frontend/src/layout/ActiveContext.tsx`) que ya usa
  `ActiveSelector.tsx` — no rehacer el estado, solo agregar la vista persistente donde hoy no hay
  ningún indicador (confirmado en `VentasList.tsx`, sin indicador de contexto).

- **Separar Padrón único en dos vistas**: dividir `PadronUnicoPage.tsx` en "Padrón único de
  proveedores" y "Padrón único de clientes" (filtrando por `rol` en el join con
  `iva_sujeto_empresas`, vía `SujetoService::listGlobal`). Evaluar si conviene fusionar
  visualmente con los ítems "Clientes"/"Proveedores" existentes del menú, para pasar de 3 entradas
  a 2 (una por rol, cada una con su vista global + acceso a la vista por empresa).

- **Mover "Cuentas" fuera del grupo IVA del menú**: en `nav.ts`, sacar el ítem "Cuentas" (líneas
  104-109) del array `items` del grupo "IVA" y crear un grupo nuevo "Contabilidad" (aunque el
  backend siga viviendo en el módulo `Compartido` por ahora — separar navegación de arquitectura de
  datos primero; mover el módulo backend en sí puede ser un paso posterior, coordinado con la
  Fase 4).

- **Navegación por contribuyente**: nueva pantalla raíz que liste las empresas/contribuyentes y, al
  hacer click, lleve directo a sus períodos — equivalente en función a lo que hoy hacen los
  dropdowns de `ActiveSelector.tsx`, pero como flujo guiado en el cuerpo de la pantalla en vez de
  un selector en el header, cumpliendo el pedido textual del cliente ("elijo, por ejemplo, 'Grupo
  AC SRL', aprieto ahí, y me lleva a sus períodos y a sus ventas").

---

## Fase 4 — Requiere insumo externo del cliente (no se puede avanzar de fondo sin esto)

- **Estructura del programa contable propio** (comprometida a 2 días desde el 10/08) → condiciona
  el diseño fino de la conexión Cuentas ↔ Contabilidad, y también responde la pregunta 5 de la
  Fase 0 (destino real del satélite). Sin esto, la Fase 3 solo puede mover el ítem de menú, no
  diseñar la integración real.
- **Confirmación del destino y formato del satélite** (respuesta a las preguntas 5 y 6 de la
  Fase 0): si el destino sigue siendo el programa contable propio del cliente, falta el formato
  exacto del TXT (columnas, delimitador, encabezados) — sin eso no se puede construir la capa de
  exportación.
- **Dataset real para el padrón** (pregunta 7 de la Fase 0): negociar el dataset real
  (`Relacion_Contribuyente_Proveedor.xlsx` u otro) y quién arma el crosswalk `CUENTA_ID` legacy →
  cuenta real.
- **Extensión del endpoint de sync de sistemaCuarto** (pregunta 1 de la Fase 0): ya sabemos que
  `GET /api/sync/clientes` existe y funciona en producción — falta que alguien con acceso a
  sistemaCuarto (el cliente, o quien lo mantenga) agregue los campos de empresa que faltan (razón
  social completa, domicilio, forma jurídica, actividad) o habilite un endpoint de lectura nuevo.
  Sin ese trabajo del lado de sistemaCuarto, la Fase 1 (ocultar alta de empresas) queda en un
  estado intermedio: se oculta la pantalla, pero no hay de dónde sincronizar todavía.
- **Confirmación de la fuente de roles** (pregunta 2 de la Fase 0) — condiciona si se puede
  simplificar la pantalla de Admin o si queda como está.

---

## Fase 5 — El satélite (prioridad número uno del cliente)

**Corrección de versión (11/08)**: la primera versión de esta fase asumía que "el satélite" era una
migración de datos históricos. Al leer `satelite/documento-1 (1).pdf` (la propuesta original) esa
hipótesis se descartó: el documento define un **puente recurrente** (Visual IVA → validación
contra el Padrón Único de Proveedores → TXT definitivo), con un destino explícito según el
documento original — el programa contable propio del cliente.

**Segunda corrección (16/08)**: ese destino tampoco se sostuvo al verificarlo contra el código real
(ver 5a) — el destino termina siendo nuestro propio sistema, no un tercero. Con eso, "el puente
recurrente" se simplifica: no hay un archivo que entregar a nadie, solo ingesta + clasificación
interna, que en gran parte ya funciona. Ver `analisis.md` §9 para el detalle completo. Esta fase
queda reescrita en tres sub-fases.

### 5a. Confirmar el destino y el formato — RESUELTO (16/08/2026)

El destino es nuestro propio sistema (`ecosistema`) — no el programa contable externo del cliente,
ni `sistemaCuarto` (verificado: su tabla `compra_ventas` no tiene cuenta contable y ninguna vista
la muestra). No hace falta capa de exportación de ningún tipo: el "destino" son directamente
nuestras propias tablas, y la clasificación ya ocurre al ingestar (`CompraService::preparar()` vía
el motor de imputación). Esto simplifica 5c: desaparece por completo la etapa de "exportación al
destino confirmado" — solo queda la ingesta.

### 5b. Demostración del motor de reglas ya construido

Independientemente de la respuesta de 5a, armar una demo (con los datos ficticios/actuales
disponibles hoy) mostrando funcionando de punta a punta lo que ya existe y es reutilizable:
- El motor de imputación contable del padrón (cuenta por defecto → excepción por punto de venta →
  excepción por empresa → mapeo de conceptos).
- El motor de clasificación de ventas por punto de venta + tipo de comprobante.
- La bandeja de pendientes (comprobantes sin proveedor/cliente identificado).
- El motor de alertas estadísticas.

Criterio de aceptación: usar las propias palabras del cliente de su informe ("prueba palpable, con
datos reales... qué función tiene, qué información brinda, cómo se trabaja") como checklist antes
de presentársela. Sirve para que el cliente vea que el núcleo de reglas que pidió sí está resuelto,
aunque falten las capas de ingesta/exportación de 5c.

### 5c. Lo que falta construir — solo ingesta, ya no hay exportación

Con 5a resuelto, esta etapa se achica: no hay "capa de exportación al destino" que construir, solo
ingesta. Según la hoja de ruta del documento original (`documento-1 (1).pdf` §9), replicar su
enfoque incremental:
- **Etapa 0 (caso chico end-to-end)**: un contribuyente de prueba, 3-4 compras (con y sin match de
  proveedor) y 2-3 ventas, para validar el flujo completo antes de escalar.
- **Etapa 1 (depuración inicial del padrón)**: puede avanzar en paralelo, es carga de datos, no
  desarrollo — depende del dataset real (pregunta 7 de la Fase 0, ver también Fase 4).
- **Etapa 2 (compras)**: construir la ingesta de un archivo exportado de Visual IVA (hoy no existe;
  lo que existe es nuestro propio importador CSV, que asume comprobantes ya en formato propio). Es
  una **migración histórica puntual**, no un pipeline permanente — el propio cliente dijo que
  Visual IVA queda "solo de respaldo" una vez andando el sistema nuevo; la carga de comprobantes
  nuevos hacia adelante ya la cubre el importador CSV existente.
- **Etapa 3 (ventas)**: el motor de clasificación ya existe (5b); falta la misma capa de ingesta
  histórica que en compras.
- **Etapa 5 (escalado)**: incorporar el resto de los contribuyentes, una vez probado con el caso
  chico.

El cliente ofreció poner 30 personas "a encuadrar" datos en cuanto vea el motor funcionando (5b) —
la tarea humana concreta que se les puede asignar es la depuración del padrón (Etapa 1) y la
revisión del dataset antes de sembrarlo, no el desarrollo en sí.

---

## Fase 6 — Respuestas de negocio sobre "Clientes" (entregable de texto, no código)

El cliente pidió explícitamente respuesta a dos preguntas en su informe. Van como entregable de
texto en la respuesta formal (el detalle técnico completo está en `analisis.md` §7):

1. **"¿Qué limitaciones tiene?"** — Hoy, el permiso de un usuario es global al tenant: no hay forma
   de restringir a un operador a un contribuyente puntual. Un usuario con permiso de ventas puede
   operar ventas de cualquier empresa del estudio. Es una limitación real y documentada, no un
   bug — queda pendiente de la pregunta 9 de la Fase 0 si se resuelve en esta ronda o en el
   backlog.

2. **"¿Se puede trabajar el mismo cliente en paralelo, con qué bloqueos?"** — Sí se puede (por
   ejemplo, un operador en ventas y otro en compras del mismo contribuyente, sin conflicto, porque
   son tablas distintas). Lo que no existe hoy es protección si **dos operadores editan el mismo
   comprobante** a la vez: no hay ningún lock, ni optimista ni pesimista — es *last-write-wins* sin
   aviso. Opciones a futuro: un aviso liviano ("Fulano está editando esto ahora") o un lock
   optimista real; cuál conviene depende de la respuesta a la pregunta 11 de la Fase 0.

---

## Fase 7 — Checklist de cierre y seguimiento

- [ ] Enviar al cliente la respuesta formal, referenciando `analisis.md` (comparación punto por
      punto) y este documento resumido (plan de acción).
- [ ] Dejar explícita la pregunta de estrategia (¿reemplazar o integrar sistemaCuarto?) y las 12
      preguntas puntuales de la Fase 0 como lo primero a responder, antes de anunciar cualquier
      fecha de entrega de las fases siguientes.
- [ ] Registrar qué queda pendiente del lado del cliente: estructura del programa contable (2 días
      desde el 10/08), confirmación del destino y formato del satélite (preguntas 5 y 6), dataset
      real del padrón (pregunta 7), y el resto de las respuestas de la Fase 0.
- [ ] Fijar una fecha de checkpoint concreta (no "cuando el cliente responda") para revisar el
      estado de las preguntas pendientes y destrabar las fases bloqueadas.
- [ ] Una vez cerrada la Fase 0, actualizar este documento con fechas y owners reales por fase —
      hoy es un plan de secuencia, no un cronograma.
