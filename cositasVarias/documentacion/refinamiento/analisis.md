# Análisis del informe del cliente (10/08/2026) vs. estado real del código

**Informe analizado:** `documentacion/INFORME-ALEXIS-SAUCEDO-10-08-2026_(actualizado).docx`,
Juan Pablo Haddad, Estudio Haddad, 10/08/2026.
**Fecha de este análisis:** 11/08/2026.
**Método:** cada punto del informe se contrasta contra el código real hoy (no contra roadmap ni
intenciones), citando `archivo:línea` verificable. No se opina sobre si el reclamo es "justo" —
se separa lo que es un hecho verificado de lo que requiere una decisión de negocio.

**Leyenda de veredictos:**
- 🔴 **CIERTO** — el cliente tiene razón tal cual lo plantea; hay que actuar en esa dirección.
- 🟡 **MATIZADO** — el síntoma que describe es real, pero hay un componente técnico detrás que él
  no ve y que cambia cómo hay que resolverlo (no siempre "borrar").
- 🔵 **REQUIERE DECISIÓN DE NEGOCIO** — no se puede resolver solo con código: falta una definición,
  un dato o un insumo que solo el cliente puede dar.

---

## 0. Hallazgo de fondo — el "SIGE" es `sistemaCuarto`, y la duplicación fue una estrategia deliberada

Este hallazgo cambia el marco de lectura de todo el informe. No es una corrección menor: explica
**por qué** existe la duplicación que el cliente reclama, y muestra que no es un desvío accidental
— fue una decisión de arquitectura tomada y documentada el 15/06/2026, antes de este informe.

### El "SIGE" del cliente está identificado con certeza

`/data/proyectos/cynchro/sistemaContable/sistemaCuarto` es una aplicación **Laravel 12 real, en
producción**, cuyo propio `RESUMEN_SISTEMA.md` (línea 1) se titula: *"Sistema de Gestión — Estudio
Contable (Haddad / 'Synergys' · ContadorIA)"* — el estudio del propio cliente. No es una
suposición: contiene exactamente lo que el informe describe como "ya cargado y funcionando" en el
SIGE:
- **Agenda de Clientes** → módulo `Persona` (CRM de contribuyentes, alta/edición, CUIT, contacto,
  documentación) — `RESUMEN_SISTEMA.md:52-53`.
- **Sistema Registral conectado a ARCA** → módulo `SistemaRegistral` +
  `SistemaRegistralImportController` (`POST /api/sistema-registral/import`) —
  `RESUMEN_SISTEMA.md:65,175`.
- **Vencimientos Fiscales** → módulos `Tributo` + `Vencimiento`/`VencimientoFiscal`, con workflow
  de estados (creado → documentación recibida → cargada → en control → presentado) —
  `RESUMEN_SISTEMA.md:55-57`.
- **Roles y permisos** → `spatie/laravel-permission`, 5 roles (superAdmin/admin, gerente, contador,
  asistente) con permisos granulares por módulo — `RESUMEN_SISTEMA.md:34-47`.
- Además, sistemaCuarto tiene Honorarios, Tareas (workflow completo), Requerimientos, y un módulo
  `CompraVenta` que ya recibe comprobantes de compra/venta importados.

### La duplicación fue decidida por escrito, no es un accidente

`docs/ingenieria-inversa/ecosistema-unificacion.md` (15/06/2026, **de este mismo repositorio**)
define la estrategia del proyecto en su primera línea: *"Objetivo: un **único sistema homogéneo**
que **reemplace 3 aplicaciones**, sin duplicar datos."* Las tres aplicaciones listadas
(línea 7-13) son: IVA (legacy Delphi), Sueldos (legacy Delphi) y **`sistemaCuarto`** — identificado
ahí mismo como *"CRM + obligaciones fiscales + tareas"*, exactamente el SIGE del cliente.

`docs/ingenieria-inversa/sistemacuarto.md` (mismo repo, mismo día) es el plan de ingeniería
inversa que efectivamente se ejecutó: define módulo por módulo qué se portaría de sistemaCuarto
al ecosistema nuevo — **Fiscal** (tributos + vencimientos), **Tareas**, **Honorarios**, **CRM
contribuyente** — y aclara en su línea 27 que hasta Auth/roles se descartó a propósito para
construir uno "nativo" en vez de portar `spatie/laravel-permission`. Es decir: **cada uno de los
puntos que el cliente marca como "ya lo tengo, no lo dupliques" (alta de empresas/CRM,
vencimientos, roles, y en parte actividades) fue construido siguiendo al pie de la letra un plan
que decía explícitamente "reemplazar" esas piezas de su SIGE.**

### Por qué importa para este análisis

Esto cambia la naturaleza de varios veredictos "MATIZADO" de las secciones siguientes: el matiz ya
no es "hay una razón técnica oculta que lo justifica" — es **"hubo una decisión de producto tomada
en junio, que el informe del 10/08 contradice o revierte, y hay que resolverla explícitamente, no
just apagar pantallas."** El cliente en su carta dice *"busquemos la forma de unirlo"* — que es
literalmente lo opuesto a *"reemplace 3 aplicaciones"*. Ambas cosas no pueden ser la estrategia al
mismo tiempo.

### Un dato técnico que cambia el plan: sistemaCuarto ya tiene infraestructura de sincronización

sistemaCuarto no es una isla cerrada — ya expone:
- `GET /api/sync/clientes` (`SyncController::clientes`, `routes/api.php:51`): devuelve los
  contribuyentes activos con CUIT y credenciales AFIP. Hoy está acotado a alimentar un bot externo
  ("HaddyBot", un servicio Python ajeno a este repositorio, que ya scrapea ARCA y empuja
  comprobantes a sistemaCuarto vía `POST /api/compra-venta/import` — ver
  `SyncController.php:1-20`), pero confirma que el **patrón de sincronización ya existe** y es
  extensible.
- `POST /api/compra-venta/import`, `POST /api/sistema-registral/import`,
  `POST /api/cuentas-tributarias/import`, `POST /api/facilidades/import`: endpoints de ingesta ya
  en producción, protegidos por `X-API-KEY` (`CV_IMPORT_API_KEY`).

Esto es información nueva y concreta para la pregunta 1 de la Fase 0 de `planificacion.md`: no es
"¿tiene el SIGE alguna forma de exponer datos?" en abstracto — la respuesta parcial ya es **sí**,
y la pregunta real es si se puede extender ese mismo patrón (agregar un endpoint de lectura de
`Persona`/empresa completo, no solo credenciales AFIP) en vez de construir algo desde cero.

También sale a la luz una redundancia adicional, más allá de las pantallas: existe **"HaddyBot"**
(bot Python del cliente, ya en producción, scrapea ARCA y carga a sistemaCuarto) y, por separado,
**`extractor/`** en este mismo repositorio (Node/Playwright, standalone, sin conectar a nada
todavía) — dos automatizaciones distintas resolviendo una porción del mismo problema (traer
comprobantes de ARCA). Vale la pena preguntarle al cliente si HaddyBot debería ser la única fuente,
en vez de terminar `extractor/`.

---

## 1. Resumen ejecutivo

> Leer §0 primero: el "SIGE" del informe está identificado con certeza (`sistemaCuarto`, un
> Laravel real en producción del propio estudio) y la duplicación que reclama el cliente responde a
> una decisión de arquitectura tomada por escrito el 15/06/2026 ("reemplazar 3 aplicaciones"), no a
> un desvío accidental. Eso reencuadra los veredictos "MATIZADO" de más abajo: el matiz no es "hay
> algo que no ves", es "hay una decisión previa que hay que revertir o confirmar explícitamente".

| # | Pedido del cliente | Veredicto | Acción en una frase |
|---|---|---|---|
| 1 | Sistema nuevo = solo IVA + Sueldos + Contabilidad | 🔴 CIERTO | Redefinir el alcance formalmente; hoy incluye gestión general que compite con el SIGE. |
| 2a | Sacar alta de empresas (ya está en el SIGE) | 🟡 MATIZADO | No se puede borrar la tabla `empresas` (es la base de todo IVA/Sueldos), pero sí se puede dejar de pedir que la tipeen — si el SIGE puede sincronizarla. |
| 2b | Sacar Vencimientos (ya está en el SIGE) | 🔴 CIERTO | Ocultar/retirar del sistema nuevo, sin matiz técnico a favor de mantenerlo. |
| 2c | Sacar Roles y permisos (ya está en el SIGE) | 🟡 MATIZADO | Es autorización interna de este sistema, no "gestión de usuarios del estudio" — hay que decidir con qué se reemplaza el control de acceso, no solo apagar la pantalla. |
| 2d | Sacar Actividades (ya está en el SIGE) | 🟡 MATIZADO | No es un catálogo de actividades económicas: es el motor que calcula IVA por comprobante. No se puede sacar sin romper el cálculo — sí se puede dejar de mostrar como si fuera "gestión de empresa". |
| 3 | El CUIT se carga una sola vez | 🔴 CIERTO | Hoy se tipea dos veces, en dos formularios sin relación entre sí. |
| 4 | Padrón único: mostrar que funciona, con datos reales | 🔴 CIERTO (síntoma) | Está vacío. El motor que le da sentido ya existe (ver §9) — falta poblarlo y mostrarlo. |
| 5a | Un solo padrón (no "proveedores" + "padrón único" separados) | 🟡 MATIZADO | El modelo de datos ya está bien separado; lo que confunde es la UI (3 ítems de menú, 1 vista mezclada). |
| 5b | Navegación por contribuyente + banner de contexto + 2 preguntas | 🔴 CIERTO | No existe ni el flujo guiado ni el banner. Las 2 preguntas tienen respuesta hoy mismo (§7). |
| 6 | Cuentas va a Contabilidad, no a IVA | 🟡 MATIZADO | Tiene razón en la ubicación de menú; Cuentas no es descartable — es la base de la Mayorización que él mismo pidió el 07/07. |
| 7 | El satélite, prioridad urgente | 🔴 CIERTO — **el hallazgo más importante de este documento** | Lo construido está bien diseñado pero apunta al lugar equivocado: falta la ingesta de Visual IVA y la exportación al destino real (su programa contable propio). Ver §9. |

**Antes de programar nada**: el punto más urgente de confirmar con el cliente no es un bug ni una
pantalla — es la sección 9 (El satélite), con las dos preguntas puntuales que plantea (destino del
proceso, formato del archivo). Puede cambiar todo el orden de prioridades del resto del plan.

---

## 2. Pedido 1 — "El sistema nuevo tiene que ser únicamente de IVA, sueldos y contabilidad"

**Veredicto: 🔴 CIERTO**, sin matiz técnico en contra. El sistema hoy incluye, además de IVA y
Sueldos: alta y ABM completo de empresas (`frontend/src/modules/empresas/`), Padrón único de
sujetos, Vencimientos/Tareas/Honorarios (módulo `Fiscal` + `frontend/src/modules/gestion/`), y
administración de roles y permisos (módulo `Admin`). Todo eso es, funcionalmente, un sistema de
gestión del estudio — exactamente lo que el cliente dice que ya tiene resuelto por otro lado. Como
se detalla en §0, esto no fue un desvío accidental: es el resultado directo de ejecutar el plan de
`docs/ingenieria-inversa/ecosistema-unificacion.md`, que decidía "reemplazar" ese SIGE. El pedido 1
implica revertir esa estrategia, no solo recortar pantallas.

**Nota de alcance**: "Contabilidad" hoy **no es un módulo propio**. Los módulos del backend son:
`Admin, ApiKeys, Auth, Billing, Compartido, Contribuyentes, Fiscal, Honorarios, Iva, Sueldos,
Tareas, Tenant` (no existe `backend/app/Modules/Contabilidad`). Lo más parecido a contabilidad que
existe hoy vive repartido dentro de `Iva`: el plan de Cuentas y la "Mayorización por línea"
(ver §8). Si el pedido 1 se toma en serio, hay que decidir formalmente si se crea un tercer módulo
`Contabilidad` o si se sigue construyendo sobre lo que ya existe en `Iva`.

---

## 3. Pedido 2 — "Sacar del sistema nuevo lo duplicado"

### 3.1 Alta de empresas

**Veredicto: 🟡 MATIZADO — pero con un camino técnico concreto, no una incógnita abierta.**

Lo que hay: `frontend/src/modules/empresas/EmpresaFormModal.tsx` **sí** tiene un botón "Obtener
datos de AFIP" (líneas 189-198) que, a partir del CUIT, llama `GET /padron/{cuit}/sugerencia`
(`PadronController::sugerencia`, `backend/app/Modules/Iva/routes.php:334-335`) y autocompleta
nombre, domicilio, localidad, provincia, actividad y condición de IVA. No es un alta 100% manual —
pero el problema de fondo no es la UX del formulario, es que **igual duplica el trabajo**: el
cliente ya carga esa misma empresa en el SIGE (`sistemaCuarto`, ver §0), con más datos y mejor
integrados (forma jurídica, fecha de contrato social, mes de cierre, dependencia, inscripciones —
vía su módulo `SistemaRegistral`). Nuestro alta, aunque tiene botón AFIP, sigue siendo una segunda
carga.

Además, el backend (`CreateEmpresaRequest.php:12-34`) valida más campos de los que el modal
expone (`establecimiento`, `nro_libro_iva`, `contacto`, `tipo_persona`, `inscripcion`,
`contabilidad`) — hay una ficha de empresa más grande de lo que el usuario ve, reforzando que esto
se percibe como un mini-CRM de contribuyentes.

**Por qué no se puede simplemente "borrar" esta pantalla**: la tabla `empresas` es la clave foránea
de absolutamente todo en este sistema — períodos, ventas, compras, cuentas, sujetos, actividades.
No es negociable tener un `empresa_id` local. Lo que sí es negociable es **cómo llega ese
registro**: tipeado a mano (hoy) vs. sincronizado desde el SIGE (lo que el cliente pide).

**Ya no es una incógnita completa**: `sistemaCuarto/routes/api.php:51` expone
`GET /api/sync/clientes` (`SyncController::clientes`), que hoy devuelve `Persona` activas con
CUIT, credenciales AFIP y nombre — pensado para alimentar un bot externo ("HaddyBot"), pero
confirma que **el patrón de sincronización por API ya existe y está en producción**. La pregunta ya
no es "¿tiene el SIGE alguna forma de exponer datos?" sino algo más concreto (ver Fase 0 de
`planificacion.md`, pregunta 1): ¿se puede extender ese mismo endpoint (o agregar uno análogo) para
traer los campos completos de empresa, en vez de construir una integración nueva desde cero?

### 3.2 Vencimientos y gestión del estudio

**Veredicto: 🔴 CIERTO**, sin matiz técnico a favor de mantenerlo.

Lo que hay: módulo backend `Fiscal` (`backend/app/Modules/Fiscal/`, controlador
`VencimientoController.php`) + frontend `frontend/src/modules/gestion/VencimientosTab.tsx` (junto
con `TareasTab.tsx` y `HonorariosTab.tsx`), agrupados bajo el ítem de menú "Vencimientos y tareas"
(`frontend/src/layout/nav.ts:162`, dentro del grupo "Estudio"). Es un CRUD completo y funcional —
no está roto, está simplemente vacío porque el cliente nunca lo cargó, y no lo va a cargar: ya
tiene los "Vencimientos Fiscales" del SIGE poblados y en uso. Confirmado con evidencia directa
(§0): `sistemaCuarto` tiene los modelos `Tributo` y `Vencimiento`/`VencimientoFiscal` con el mismo
workflow de estados (creado → documentación recibida → cargada → en control → presentado,
`RESUMEN_SISTEMA.md:55-57`) que se replicó, casi 1:1, en nuestro módulo `Fiscal`
(`docs/ingenieria-inversa/sistemacuarto.md:31-36`, mismo orden canónico de estados). No hay ningún
argumento técnico para mantener esto activo en el sistema nuevo — es una réplica funcional
confirmada de algo que ya existe en producción.

### 3.3 Roles y permisos

**Veredicto: 🟡 MATIZADO — y acá el matiz es explícitamente una decisión ya tomada, no un
hallazgo nuevo.**

Lo que hay: módulo `Admin` con RBAC completo y funcional
(`PermissionMiddleware`/`PermissionChecker`, taxonomía `iva.ventas` / `iva.compras` / etc. con
nivel lectura/escritura por rol, definida en `backend/seeders/PermisosIvaSeeder.php`). El seeder
por defecto solo crea **un** rol ("Administrador" con permiso total); los roles granulares se
arman a mano desde `/admin/roles`.

El SIGE (`sistemaCuarto`) ya tiene su propio RBAC real, con `spatie/laravel-permission`: 5 roles
(superAdmin/admin, gerente, contador, asistente) con permisos granulares por módulo
(`RESUMEN_SISTEMA.md:34-47`, `database/seeders/RolesPermisosSeeder.php`). El plan de ingeniería
inversa (`docs/ingenieria-inversa/sistemacuarto.md:27`) decidió **a propósito** no portar ese RBAC
— construir uno "nativo" del framework en su lugar. El cliente tiene razón en que es funcionalidad
duplicada del SIGE **como producto de gestión de usuarios**. Pero hay un matiz real que sigue en
pie: el RBAC de este sistema no es "administración de usuarios del estudio" en sentido amplio — es
la autorización a nivel de API que decide si un operador puede escribir una venta o solo leerla,
**dentro de este sistema**. Sacarlo sin más deja sin resolver la pregunta de quién puede hacer qué
acá adentro.

**🔵 Requiere decisión de negocio**: ¿el SIGE puede ser la fuente única de verdad de permisos (y
este sistema solo autentica/valida contra eso), o alcanza con un control de acceso mínimo interno
(login sin granularidad, o granularidad oculta detrás de escena) para el lanzamiento? La respuesta
condiciona si se puede sacar la pantalla de administración de roles del menú, o si solo se puede
simplificar.

### 3.4 Actividades

**Veredicto: 🟡 MATIZADO — el punto más importante de aclarar de todo el pedido 2.**

Lo que hay: `EmpresaActividadController` (dentro del módulo `Iva`, no un módulo de gestión), con 5
estrategias de resolución (override por comprobante, por receptor, por punto de venta, por
alícuota, por coeficientes fijos). Esto **no es** el mismo catálogo de actividades económicas que
tiene el SIGE — es el motor interno que decide, comprobante por comprobante, a qué actividad/cuenta
imputar cada operación para poder armar la DJ IVA Simple (que abre "por actividad" cuando el
contribuyente tiene más de una). Si se elimina, se rompe el cálculo de un formulario que el cliente
sí quiere que funcione bien.

Confirmado (§0): el SIGE **sí** tiene datos de actividad económica reales, sincronizados de ARCA —
el modelo `SistemaRegistral` de `sistemaCuarto` guarda `datos_actividad_economica` y
`actividades_monotributistas` (`app/Models/SistemaRegistral.php:18,34-35`), como parte del mismo
Sistema Registral que el cliente menciona en el pedido 1. Es la actividad **declarativa/fiscal**
del contribuyente (para AFIP); lo que tenemos nosotros es la actividad **operativa** (para
calcular impuestos por comprobante) — son datos relacionados pero no intercambiables: uno dice
"qué actividad tiene declarada este contribuyente", el otro dice "a qué actividad imputar esta
factura puntual". El nombre compartido ("Actividades") es lo que genera la confusión, no el
contenido.

Lo que sí es cierto: el nombre "Actividades" en el menú (`nav.ts:111`, primer nivel del grupo IVA)
es indistinguible, a simple vista, del catálogo de actividades económicas que el cliente ya
administra en el SIGE — de ahí su confusión y su pedido de sacarlo.

**Acción propuesta** (no eliminar el motor, sí la exposición): reubicar la pantalla de
configuración de Actividades como configuración avanzada dentro de la ficha de cada empresa, en
vez de un ítem de primer nivel del menú, y/o renombrarla para que quede claro que es "reglas de
cálculo de IVA", no "actividades económicas del contribuyente".

---

## 4. Pedido 3 — "El CUIT se carga una sola vez"

**Veredicto: 🔴 CIERTO.**

El CUIT se tipea en `EmpresaFormModal.tsx` (alta de empresa) y **de nuevo**, sin ninguna relación
con el anterior, en `SujetoFormModal.tsx` (alta de cliente/proveedor,
`frontend/src/modules/iva/sujetos/SujetoFormModal.tsx:150-152`, campo etiquetado literalmente
"CUIT * (clave del padrón único)"). Son dos formularios independientes, con botones AFIP
independientes (`EmpresaFormModal.tsx:189-198` y `SujetoFormModal.tsx:153-162`), sin cruce entre
sí. Es el pedido más antiguo y más repetido del cliente (líneas 10 y 77 del informe) y, técnicamente,
sigue sin resolverse a nivel de arquitectura — cada CUIT que ya es una empresa-contribuyente puede
volver a tipearse como sujeto (cliente/proveedor) de otra empresa, sin que el sistema lo relacione.

---

## 5. Pedido 4 — "El Padrón único: mostrame qué hace, con datos reales"

**Veredicto: 🔴 CIERTO en el síntoma.**

`PadronUnicoPage.tsx:106-112` — cuando no hay sujetos, muestra literalmente "Sin sujetos cargados".
El cliente entró, vio la lista vacía, y concluyó (razonablemente) que es "solo un nombre". Consume
`GET /padron-unico` (`PadronUnicoController::index`), que hoy trae poco porque la base de
desarrollo no tiene datos reales cargados.

**Esto conecta directo con el hallazgo del §9**: el motor de reglas que le daría sentido al Padrón
único (imputación contable por defecto, excepción por punto de venta, excepción por empresa) **ya
está construido** — lo que falta es población de datos reales, no programación nueva. Ver §9 para
el detalle completo.

**🔵 Requiere decisión de negocio**: ¿con qué dataset se arma la demo palpable que el cliente pide
("no me interesa el nombre... quiero ver que funciona, qué información brinda")? Ya existe un
dataset real depurado (`Relacion_Contribuyente_Proveedor.xlsx`, 376.819 filas) esperando desde el
21/07 — la pregunta es si se puede usar ya para esa demo o si hace falta otra cosa.

---

## 6. Pedido 5, parte A — "Un solo padrón: no puede convivir 'proveedores' con 'padrón único'"

**Veredicto: 🟡 MATIZADO — el modelo de datos es más correcto de lo que el cliente cree.**

El modelo real (migración `backend/migrations/0048_iva_padron_unico_sujetos.php`) es:

- `iva_sujetos` (líneas 26-46): identidad única por `(tenant_id, cuit)` — `UNIQUE KEY
  uq_sujeto_tenant_cuit (tenant_id, cuit)` en la línea 44. **No tiene campo `rol`.**
- `iva_sujeto_empresas` (líneas 48-57): tabla de activación, con `rol ENUM('cliente','proveedor')`
  en la línea 52, `UNIQUE KEY uq_sujemp (empresa_id, sujeto_id, rol)` en la línea 56.

Es decir: la identidad (nombre, domicilio, condición de IVA) **no está mezclada** con el rol
cliente/proveedor — el rol vive en una tabla de vínculo aparte, por empresa. La separación técnica
"proveedor por un lado, cliente por otro" que el cliente pide **ya existe en el modelo**.

Lo que sí es cierto y genera la confusión real:
- El **menú** (`frontend/src/layout/nav.ts:88,95,101`) tiene **3 ítems** para lo que
  conceptualmente son 2 padrones: "Clientes" (`/empresas/{id}/clientes`), "Proveedores"
  (`/empresas/{id}/proveedores`), y "Padrón único" (`/padron-unico`, global).
- La **página** "Padrón único" (`PadronUnicoPage.tsx:45-49`) presenta explícitamente "todos los
  proveedores y clientes del estudio... en una sola vista" — mezclando ambos roles en la misma
  tabla, con un badge por fila que indica el rol. Es una vista de solo lectura/búsqueda (el propio
  comentario del código, líneas 24-29, lo confirma: "la edición sigue viviendo en la pantalla de
  cada empresa").

**Acción correcta**: separar la vista en dos (Padrón único de proveedores / Padrón único de
clientes), no separar el modelo de datos (ya está bien separado). Esto también reduce el ruido de
navegación de 3 ítems a algo más claro.

---

## 7. Pedido 5, parte B — "Clientes: navegación por contribuyente + banner + 2 preguntas"

### Navegación

**Veredicto: 🔴 CIERTO.**

El ítem "Clientes" del menú (`nav.ts:88-93`) apunta a `/empresas/{id}/clientes` — es decir, a los
clientes **de la empresa activa** (para cargar ventas), no a un directorio raíz de "mis
contribuyentes" como el cliente lo interpreta en su informe ("entiendo que serían los clientes de
los contribuyentes... elijo, por ejemplo, 'Grupo AC SRL'... me lleva a sus períodos y a sus
ventas"). No existe hoy ningún flujo guiado tipo wizard "elijo contribuyente → veo sus períodos →
veo sus ventas": el usuario tiene que usar el par de dropdowns del header
(`ActiveSelector.tsx`) para fijar empresa y período, y después navegar el sidebar a mano hasta
Ventas/Compras/Libro IVA.

### Banner de contexto persistente

**Veredicto: 🔴 CIERTO.**

`ActiveSelector.tsx` (líneas 53-116) es un par de `CDropdown` montados en el header
(`AppHeader.tsx`), que muestran "Empresa: **{nombre}**" y "Período: **{nombre}** [badge
Abierto/Cerrado]". No hay ningún banner replicado dentro de las pantallas de trabajo: por ejemplo
`VentasList.tsx` no muestra el nombre de la empresa ni del período en ningún lado de su UI, solo
un link "← Empresas" para volver atrás — confirmado también en `PeriodosList.tsx:68-70`, mismo
patrón. El pedido del cliente ("Contribuyente tal · IVA · Período tal", visible siempre) no existe
hoy en ninguna forma.

### Respuesta a sus dos preguntas explícitas

**1. "¿Qué limitaciones tiene?"**

El permiso de un usuario (`iva.ventas`, `iva.compras`, etc., vía `PermissionMiddleware`) es
**global al tenant**, no está scoped por empresa/contribuyente. No hay ninguna tabla ni columna
que ate un permiso a una empresa puntual (`PermissionChecker.php` solo filtra por rol/key). Hoy
**no se puede** limitar a un operador a "solo ventas del cliente X" — un usuario con permiso
`iva.ventas` puede operar ventas de cualquier empresa visible del tenant. Esta es una limitación
real del sistema tal como está hoy.

**2. "¿Se puede trabajar el mismo cliente en paralelo — un operador en ventas, otro en compras?
¿Hay bloqueos?"**

Confirmado por revisión exhaustiva del código (backend + frontend, migraciones incluidas): **no
existe ningún mecanismo de lock**, ni optimista (sin columna `version`, sin `updated_at`/ETag) ni
pesimista, para la edición concurrente de comprobantes, períodos o empresas. El único lock que
existe en todo el sistema es un *advisory lock* de MySQL (`App\Support\DB::withLock()`,
`backend/app/Support/DB.php:58-84`), usado exclusivamente para serializar la numeración de
comprobantes al pedir CAE a AFIP (`FacturaElectronicaService::autorizar()`) — no protege ediciones
generales.

En la práctica, **sí** se puede trabajar en paralelo (un operador en ventas, otro en compras — son
tablas distintas, sin conflicto). El riesgo aparece si **dos operadores editan el mismo
comprobante** al mismo tiempo: hoy es *last-write-wins* sin ningún aviso — gana el último que
guarda, sin que el sistema avise al primero que su cambio se perdió.

---

## 8. Pedido 6 — "Cuentas va a Contabilidad, no a IVA"

**Veredicto: 🟡 MATIZADO — tiene razón en la ubicación, no en el fondo.**

Lo que hay: el ítem "Cuentas" cuelga del grupo de menú "IVA" (`nav.ts:104-109`) y del módulo
backend `Compartido` (no existe módulo `Contabilidad`). En ese sentido, el cliente tiene razón: la
navegación sugiere que "Cuentas" es una funcionalidad de IVA, cuando conceptualmente es un insumo
contable.

Pero Cuentas **no es código viejo prescindible**. Desde el 07/07/2026, la migración
`backend/migrations/0043_add_cuenta_linea_discriminacion.php` (comentario explícito en el código:
*"Mayorización por línea (respuesta R1 del contador, 07/07)"*) usa el plan de Cuentas para imputar
**cada línea** de venta/compra a una cuenta contable, alimentando los "Reportes de Mayor" — un
embrión real de contabilidad de partida doble, **pedido explícitamente por el propio cliente** en
una ronda de feedback anterior (07/07/2026). Sacarlo del sistema "porque es de IVA que sobra"
rompería una funcionalidad que él mismo encargó y que hoy es insumo directo del cálculo de IVA por
comprobante.

**Acción correcta**: mover la ubicación en el menú (de "IVA" a un futuro grupo "Contabilidad"), no
eliminar ni recortar la funcionalidad de Cuentas/Mayorización.

**🔵 Requiere insumo externo**: el cliente se comprometió, en su propio informe, a mandar en un
máximo de 2 días (desde el 10/08) "la estructura y la información del programa contable" que él
mismo armó, como base para diseñar la conexión real. Sin ese insumo, el diseño fino de cómo Cuentas
se conecta con "su" contabilidad no se puede cerrar — el reordenamiento de menú sí se puede hacer
sin esperar eso.

---

## 9. El satélite — hallazgo crítico, corregido contra la fuente primaria

Esta es la sección más importante del análisis, porque el cliente la marca como su prioridad
número uno ("necesito eso urgente... evalúa este informe punto por punto... y, sobre todo, qué
necesitás para el satélite").

> **Corrección de versión**: la primera versión de este documento (11/08, antes de leer los PDF
> originales) planteaba acá una hipótesis de "migración de datos históricos". Al leer
> directamente `satelite/documento-1 (1).pdf` y `satelite/documento-2.pdf`, esa hipótesis **no se
> sostiene** — el documento fuente describe otra cosa, más precisa y más accionable. Queda
> corregido abajo.

### Lo que el documento fuente define — literal, no inferido

`satelite/documento-1 (1).pdf` ("Satélite Visual IVA → Sistema Contable — Módulo de compras,
ventas y Padrón Único de Proveedores", preparado para "Alex (desarrollo, Córdoba)", proyecto SIGE)
es la propuesta de proceso que define qué es "el satélite" en las propias palabras del cliente. No
describe una migración masiva de una sola vez — describe un **puente recurrente y acotado**:

1. **Entrada**: exportaciones manuales de Visual IVA (comprobantes de compras y ventas de cada
   contribuyente). `satelite/documento-2.pdf` es la evidencia real usada para diseñarlo: un listado
   real de Visual IVA ("LISTADO DE COMPROBANTES AGRUPADO POR CUENTAS — PERIODO: 202606 Junio 2026")
   que muestra, con nombres y CUIT reales, el problema que motivó el diseño: el mismo proveedor
   imputado en cuentas distintas según quién cargó el comprobante (ej. `CATAMARCA COMBUSTIBLES`
   mezclado dentro de "Compras" en vez de "Combustibles y Lubricantes"; `TRAILINGSAT S.A.` y
   `CAR-GAS S.R.L.` cayendo en "Gastos Generales"; `MUCHAY SRL` facturando conceptos distintos
   según el punto de venta que usa — 0003, 0004, 0012, 0013).
2. **Proceso de compras**: por cada comprobante, identificar al proveedor por CUIT, buscarlo en el
   Padrón Único de Proveedores (global, un proveedor = una carga para todo el estudio); si hay
   match, avanza; **si no hay match, el comprobante NO se incluye en el archivo — queda en una
   bandeja de pendientes para revisión manual** (documento-1 §3.2: se prioriza la integridad del
   archivo por sobre la completitud automática).
3. **Proceso de ventas**: motor de clasificación por punto de venta + tipo de comprobante, con
   configuración por contribuyente (qué concepto contable corresponde a cada punto de venta, con
   posibilidad de variar según el tipo de comprobante).
4. **Salida**: un **archivo TXT definitivo**, listo para importar.
5. **Destino del archivo — el dato central que cambia todo**: el propio documento lo dice de forma
   explícita y repetida: *"un archivo listo para cargar en el sistema contable desarrollado por
   juan pablo haddad"* y, en el diagrama del proceso, *"Sistema contable ya implementado por juan
   pablo en funcionamiento: destino final de ambos caminos"*. Es decir: **el destino no es nuestro
   backend/frontend** — es un programa contable propio, externo, que el cliente ya construyó y usa
   hoy. Esto coincide exactamente con lo que el cliente dice en su informe del 10/08 (pedido 6):
   *"me comprometo a pasarte... la estructura y la información del programa contable que yo
   armé... es la base sobre la que estoy trabajando hoy, cargando y sacando información"*.

El documento también define una hoja de ruta incremental de 7 etapas (§9 del PDF): Etapa 0 (caso
chico end-to-end de prueba), Etapa 1 (depuración inicial del padrón con datos reales), Etapa 2
(compras: ingesta de Visual IVA + validación + TXT definitivo + bandeja de pendientes), Etapa 3
(ventas: motor de clasificación), Etapa 4 (mantenimiento del padrón: alta/edición/consulta/reglas
por PV/excepciones por contribuyente, "para que el estudio lo mantenga sin depender de Alex"),
Etapa 5 (escalado a todos los contribuyentes), Etapa 6 (alertas estadísticas).

### Lo que ya existe con el nombre "satélite" en este repositorio — y dónde se desvió

`documentacion/analisis-satelite-visual-iva.md` (30-31/07/2026) analizó esta misma propuesta y, a
partir de ahí, **sí se construyó** un motor de reglas real y funcional, documentado como HECHO en
`CLAUDE.md`:

- **Motor de imputación contable del padrón**: cuenta por defecto del proveedor → excepción por
  punto de venta → excepción por empresa → mapeo de conceptos, jerarquía de 5 niveles
  (`ImputacionContableRepository::resolverCuenta`, migraciones `0049` y `0051`) — equivalente
  funcional de la Etapa 4 del PDF.
- **Motor de clasificación de ventas** por punto de venta + tipo de comprobante
  (`VentaClasificacionRepository`, migración `0050`) — equivalente funcional de la Etapa 3.
- **Motor de alertas estadísticas** (`AlertaEstadisticaCalculator`) — equivalente funcional de la
  Etapa 6.
- **Bandeja de pendientes** (`GET .../compras/pendientes`, `GET .../ventas/pendientes`, Parte 3) —
  parcialmente equivalente al concepto de la Etapa 2.

**Pero hay un desvío de fondo, no solo un detalle**: todo esto se construyó como **funcionalidad
interna del módulo `Iva`**, operando sobre comprobantes que **ya están cargados en nuestro propio
sistema** (a mano o vía nuestro propio importador CSV, `ImportarPage.tsx`), y mostrando el
resultado en **nuestras propias pantallas** (`ProveedorImputacionPage.tsx`, `ActividadesPage.tsx`).
Nunca se construyó:
- La **ingesta** de un archivo exportado de Visual IVA (Etapas 0-2 del PDF).
- La **generación de un TXT definitivo** con un formato pensado para importarse en un sistema
  externo (el corazón del proceso, según el propio diagrama del PDF).
- Nada dirigido al **destino real que especifica el documento**: el programa contable propio del
  cliente. Todo lo construido asume, implícitamente, que el destino es nuestro propio sistema.

Incluso nuestra propia documentación interna asumió mal este punto: el diagrama de
`documentacion/analisis-satelite-visual-iva.md` §1 dibuja el pipeline como *"ARCA → extractor →
Visual IVA → [satélite] → Sistema Contable nuevo (backend/frontend)"* — dando por sentado que el
destino final es **nuestro** sistema. El documento fuente dice lo contrario de forma explícita. Ese
error de origen es, muy probablemente, la razón real por la que el cliente dice que "el satélite ni
arrancó": lo que se construyó es lógica de negocio sólida y reutilizable, pero apuntada hacia el
lugar equivocado — hacia adentro de nuestro sistema, no hacia el puente que él pidió.

### Lo que sigue sin construirse, sin importar cuál sea el destino final

Además del desvío de destino, las Etapas 0, 1 y 5 del PDF (probar con un caso real, depurar el
padrón con datos reales, escalar a todos los contribuyentes) tampoco están hechas — coincide con lo
que `documentacion/analisis-satelite-visual-iva.md` registra como **"Parte 4" bloqueada** el
30/07/2026: `empresas` en desarrollo tiene 16 filas de prueba (no las ~351 reales), `cuentas` está
vacía, y falta el crosswalk `CUENTA_ID` legacy → cuenta real. El dataset depurado ya existe
(`Relacion_Contribuyente_Proveedor.xlsx`, 376.819 filas) y sigue esperando desde el 21/07.

### Conclusión — RESUELTA (16/08/2026)

> Esta sección quedó respondida. Se deja el planteo original (11/08) tachado conceptualmente abajo
> como registro, y la resolución real a continuación.

~~1. ¿El destino del archivo/proceso sigue siendo tu programa contable propio... o el destino pasa
a ser nuestro propio sistema?~~ — **Resuelto: el destino es nuestro propio sistema (`ecosistema`),
no el programa contable externo del cliente ni `sistemaCuarto`.** Verificado contra el código:
`sistemaCuarto.compra_ventas` (alimentada por HaddyBot) no tiene ningún campo de cuenta contable y
**ninguna vista la muestra** — no hay nada ahí que consuma una clasificación. La Contabilidad del
pedido 1 del informe es un módulo propio de `ecosistema`, y ya funciona sin cambios adicionales:
`CompraService::preparar()` resuelve la cuenta contable vía el motor de imputación al ingestar cada
comprobante, alimentando directo la Mayorización y los Reportes de Mayor (R1/R2, 07/07).

~~2. ¿Cuál es el formato exacto del TXT?~~ — **Ya no aplica.** No hace falta ningún archivo de
exportación: no hay una capa de "entregar un TXT a otro sistema", porque el destino es el propio
sistema donde ya se clasifica.

**Lo que sí sigue pendiente, sin ambigüedad de diseño, solo de ejecución**: la ingesta real de
Visual IVA (hoy solo existe el importador CSV genérico propio) y las Etapas 0/1/5 de la hoja de
ruta (caso chico de prueba, depuración del padrón con los 376.819 registros reales, escalado). El
núcleo de reglas ya construido (padrón, motor de imputación con jerarquía de 5 niveles,
clasificación de ventas por PV+tipo, bandeja de pendientes) es exactamente lo que hace falta — no
hay que rehacer lógica de negocio, ni construir una capa de exportación que ya no es necesaria.

---

## 10. Matriz resumen final

| Pedido | Veredicto | Acción inmediata posible | Bloqueado por | Fase en `planificacion.md` |
|---|---|---|---|---|
| 1. Alcance solo IVA/Sueldos/Contabilidad | 🔴 CIERTO | Redefinir alcance formal | — | Fase 0 |
| 2a. Alta de empresas | 🟡 MATIZADO | Ninguna sin definir sincronización | ¿SIGE tiene API/export? | Fase 0 → Fase 4 |
| 2b. Vencimientos | 🔴 CIERTO | Ocultar del menú | — | Fase 1 |
| 2c. Roles y permisos | 🟡 MATIZADO | Simplificar exposición | ¿SIGE como fuente de permisos? | Fase 0 → Fase 1 |
| 2d. Actividades | 🟡 MATIZADO | Reubicar/renombrar (no eliminar motor) | Validar entendimiento del cliente | Fase 0 → Fase 1 |
| 3. CUIT una sola vez | 🔴 CIERTO | Depende de 2a | ¿SIGE tiene API/export? | Fase 4 |
| 4. Padrón único con datos reales | 🔴 CIERTO | Demo del motor ya construido | Dataset a usar | Fase 5a |
| 5a. Separar padrón proveedores/clientes | 🟡 MATIZADO | Separar 2 vistas ya | — | Fase 3 |
| 5b. Navegación + banner + 2 preguntas | 🔴 CIERTO | Construir banner + wizard | — | Fase 3 (código) / este documento (preguntas) |
| 6. Cuentas → Contabilidad | 🟡 MATIZADO | Mover ítem de menú ya | Estructura del programa contable del cliente | Fase 3 (menú) → Fase 4 (diseño fino) |
| 7. El satélite | 🔴 CIERTO | Confirmar destino (§9) + demo del motor ya construido | Confirmar destino del proceso + formato del TXT | Fase 5 |

---

## Anexo — Inventario de archivos citados

**El SIGE (`/data/proyectos/cynchro/sistemaContable/sistemaCuarto`), citado en §0 y en §3.1-3.4:**
- `sistemaCuarto/RESUMEN_SISTEMA.md` (línea 1: identidad; líneas 34-47: roles; 52-68: módulos;
  104-181: integraciones e ingesta)
- `sistemaCuarto/routes/api.php` (líneas 47-62: endpoints de import/sync)
- `sistemaCuarto/app/Http/Controllers/Api/SyncController.php` (completo — `GET /sync/clientes`,
  `GET /sync/ultima-fecha`, `POST /sync/log`, autenticación `X-API-KEY`)
- `sistemaCuarto/app/Models/SistemaRegistral.php` (líneas 18, 34-35 — campos de actividad
  económica)
- `docs/ingenieria-inversa/ecosistema-unificacion.md` (documento completo, 15/06/2026 — decisión de
  "reemplazar 3 aplicaciones" incluyendo sistemaCuarto)
- `docs/ingenieria-inversa/sistemacuarto.md` (documento completo, 15/06/2026 — plan de módulos
  portados: Fiscal, Tareas, Honorarios, CRM contribuyente; línea 27: descarte deliberado de
  spatie/laravel-permission)

**Resto del sistema propio (`ecosistema/`):**
- `frontend/src/modules/empresas/EmpresaFormModal.tsx` (líneas 176-198, 160-166)
- `backend/app/Modules/Compartido/Requests/CreateEmpresaRequest.php` (líneas 12-34)
- `backend/app/Modules/Iva/routes.php` (líneas 334-335)
- `frontend/src/modules/periodos/PeriodoFormModal.tsx` (completo, campo `nombre` línea 19/55)
- `frontend/src/modules/periodos/PeriodosList.tsx` (completo)
- `backend/app/Modules/Compartido/Requests/CreatePeriodoRequest.php` (líneas 13-15)
- `backend/migrations/0012_create_compartido_tables.php` (líneas 117, 125-131)
- `backend/migrations/0043_add_cuenta_linea_discriminacion.php` (línea 4)
- `frontend/src/modules/iva/sujetos/SujetoFormModal.tsx` (líneas 140-249)
- `backend/migrations/0048_iva_padron_unico_sujetos.php` (líneas 26-46, 44, 48-57, 52, 56)
- `frontend/src/modules/iva/padron/PadronUnicoPage.tsx` (completo)
- `frontend/src/layout/nav.ts` (líneas 57-178, especialmente 65, 88, 95, 101, 104-109, 111-116,
  156-164, 166-174)
- `frontend/src/components/ActiveSelector.tsx` (completo)
- `frontend/src/layout/ActiveContext.tsx`
- `backend/app/Support/DB.php` (líneas 58-84, `withLock`)
- `backend/app/Modules/Iva/Services/FacturaElectronicaService.php` (líneas 63-67)
- `backend/seeders/PermisosIvaSeeder.php` (líneas 33-43)
- `backend/seeders/RolesUsersSeeder.php` (líneas 53-60)
- `backend/app/Modules/Fiscal/routes.php` (líneas 19-23)
- `backend/app/Modules/Admin/routes.php` (líneas 11-22)
- `backend/app/Modules/Iva/Controllers/EmpresaActividadController.php`
- `documentacion/analisis-satelite-visual-iva.md` (documento completo, citado y corregido en §9)
- `satelite/documento-1 (1).pdf` (fuente primaria: propuesta original del "Satélite Visual IVA",
  leída completa para §9 — define el proceso, el destino externo y la hoja de ruta de 7 etapas)
- `satelite/documento-2.pdf` (fuente primaria: listado real de Visual IVA, junio 2026, evidencia de
  la discordancia de imputación que motivó el diseño del padrón — leído completo para §9)
- `documentacion/Relacion_Contribuyente_Proveedor.xlsx` (376.819 filas, dataset real pendiente de
  sembrar)
- `softContable/migracion/README.md` (plan de migración general, sin arrancar)
