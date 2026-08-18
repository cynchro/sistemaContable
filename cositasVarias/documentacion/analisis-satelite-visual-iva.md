# Análisis — Propuesta "Satélite Visual IVA" vs. lo ya construido

**Fecha:** 30/07/2026 · **Fuentes analizadas:** `satelite/documento-1 (1).pdf` ("Satélite Visual
IVA → Sistema Contable", propuesta de proceso v2, preparada para Alex/desarrollo Córdoba),
`satelite/documento-2.pdf` (listado real de Visual IVA usado como evidencia), más el código de
`extractor/`, `backend/` y `frontend/` de este mismo repo, y dos documentos previos ya existentes
en `documentacion/` (`Informe_Definitivo_Padron_Proveedores.pdf` del 21/07/2026 y
`pedido-padron-unico-contribuyentes.md` del 22-23/07/2026) que resultan ser el antecedente directo
de la propuesta del satélite.

## 0. Resumen ejecutivo

La propuesta del satélite (documento 1) pide construir tres piezas: **(a)** un Padrón Único de
Proveedores con reglas de imputación contable (por defecto, por punto de venta, con excepción por
contribuyente), **(b)** un motor de clasificación de ventas por punto de venta + tipo de
comprobante, y **(c)** un motor de alertas estadísticas por desvío de tendencia. Todo esto
alimentado por exportaciones manuales de Visual IVA.

Comparado contra lo ya construido:

- **La identidad del proveedor (mitad del padrón)** ya está resuelta y en producción
  (`iva_sujetos`/`iva_sujeto_empresas`, 23/07/2026) — con un modelo prácticamente idéntico al que
  pide el documento.
- **La imputación contable del padrón (la otra mitad, y el motivo real de todo el documento)** NO
  está construida, pero **ya fue diseñada y los datos ya están depurados y esperando**: el informe
  del 21/07/2026 define exactamente el mismo modelo (Padrón + Configuración por Contribuyente) y
  entrega 376.819 filas reales de `{empresa, CUIT, rubro, cuenta}` en
  `Relacion_Contribuyente_Proveedor.xlsx`. Esa tabla quedó **fuera** de la implementación del
  23/07/2026 por una decisión de simplificación (se explica en la sección 2).
- **Las reglas por punto de venta del proveedor y el motor de clasificación de ventas** son
  conceptualmente nuevos, pero el sistema ya tiene un patrón arquitectónico idéntico funcionando
  para otro dominio (actividad NAES del DJ IVA Simple) que es directamente extensible.
- **El motor de alertas estadísticas** no tiene nada construido, y su premisa de reusar un
  "semáforo de Monotributo" no es válida: ese semáforo no existe en el código.
- **Hallazgo no anticipado por ningún documento**: `extractor/` ya resuelve, de forma más rica y
  sin depender de que el estudio exporte bien desde Visual IVA, el tramo "traer comprobantes con
  CUIT+PV+desglose fiscal" — directo de ARCA. Esto no invalida el satélite, pero es un dato que
  cambia la pregunta de diseño (ver sección 3).

## 1. Mapa del pipeline real

Ninguno de los dos proyectos nuevos (`extractor/` y el satélite propuesto) está conectado hoy al
sistema contable (`backend/`/`frontend/`). Cubren tramos distintos del mismo problema:

```
ARCA (Portal IVA)  →  [extractor, YA CONSTRUIDO]  →  Visual IVA (legacy)
                                                            │
                                                            ▼
                                          [satélite, PROPUESTO, no construido]
                                                            │
                                                            ▼
                                         Sistema Contable nuevo (backend/frontend)
```

- **`extractor/`** (`FLUJO.md`, `README.md`): scraper Playwright que se loguea a ARCA con Clave
  Fiscal, entra al Portal IVA (`liva.afip.gob.ar`), abre la DDJJ del período, y descarga el CSV de
  Libro Ventas/Compras con desglose completo (CUIT+denominación de la contraparte, punto de venta,
  neto/IVA por alícuota, percepciones, impuestos). Con eso arma un xlsx con la forma de la
  plantilla Visual IVA (`npm run convertir`). Su propio README es explícito: *"Cómo se sube la
  plantilla generada al nuevo sistema — carga manual vía el importador CSV que ya existe, un
  endpoint de staging nuevo, u otra cosa — se decide con el xlsx real ya generado en la mano, no
  antes."* Es decir, el extractor **ya tiene resuelta la extracción de datos ricos directo de
  ARCA**, sin depender de Visual IVA como intermediario.
- **El satélite propuesto** (documento 1) parte de la premisa inversa: toma exportaciones
  *manuales* de Visual IVA (que tiene "limitaciones importantes en sus categorizaciones") y las
  procesa hacia el sistema nuevo.

**Pregunta que esto plantea** (no es una recomendación cerrada, es un punto a resolver con
Alex/el estudio antes de diseñar el satélite en detalle): ¿conviene construir el satélite
consumiendo exportaciones de Visual IVA como está pensado, o construirlo/complementarlo con la
salida ya más rica del `extractor` (que trae directo de ARCA y evita depender de que el estudio
exporte bien desde el legacy)? El extractor cubre el dato crudo; lo que le falta —y es
exactamente lo que pide el documento 1— es la capa de clasificación contable (padrón +
reglas por punto de venta). Esa capa serviría igual sin importar cuál sea la fuente de datos
(Visual IVA o el extractor).

## 2. El antecedente directo que ya existe: Padrón + Configuración por Contribuyente

Este es el hallazgo más importante para entender el estado real de la propuesta del satélite.
**No es una idea nueva que arranca de cero** — hay una línea de trabajo previa en este mismo
repositorio:

1. **21/07/2026 — `documentacion/Informe_Definitivo_Padron_Proveedores.pdf`**: a partir de la base
   real de Visual IVA (385.317 filas), depuró 8.496 duplicados exactos y definió el modelo
   **"Padrón Único de Proveedores" (maestro, 1 fila por CUIT, 6.481 registros) + "Configuración
   por Contribuyente" (tabla de relación EMPRESA_ID+CUIT, 376.819 filas)**. Fija 6 reglas duras
   antiduplicación (CUIT único, validación de dígito verificador, alta por CUIT nunca por nombre,
   upsert en importaciones, la relación nunca duplica datos del proveedor, auditoría periódica) —
   son, casi textualmente, las mismas reglas que reaparecen en el documento del satélite.
   Confirmado en el propio repo: `documentacion/Padron_Unico_Proveedores.xlsx` (columnas
   `PROVEE_CUIT, NOMBRE_CANONICO, DOMICILIO, LOCALIDAD, CP, CONDICION_ID, ...`) y
   `documentacion/Relacion_Contribuyente_Proveedor.xlsx` (columnas **`EMPRESA_ID, PROVEE_CUIT,
   RUBRO_ID, CUENTA_ID`**, 376.819 filas) son exactamente esas dos tablas, ya depuradas y listas
   para usarse como semilla. También incluye el diseño de un KPI de verificación contra el padrón
   de facturas apócrifas (APOC) de AFIP, que hoy no está conectado (requiere certificado/WSAPOC).

2. **22/07/2026 — pedido de Juan Haddad** (`documentacion/pedido-padron-unico-contribuyentes.md`):
   generaliza el informe anterior — pide que sea un padrón único (no solo proveedores, también
   clientes) y señala el síntoma concreto: *"no quiero 300.000 líneas de proveedores... y algunos
   tienen doble imputación de cuenta"*.

3. **23/07/2026 — implementado, pero solo la mitad**: `iva_sujetos` + `iva_sujeto_empresas`
   (migración `backend/migrations/0048_iva_padron_unico_sujetos.php`) resuelven la **identidad**
   (padrón único por CUIT, por tenant, con upsert automático en `SujetoService::create`) — esto
   cubre bien la mitad "maestro" del modelo del informe del 21/07. Pero la **"Configuración por
   Contribuyente"** (la tabla `{empresa, CUIT} → rubro/cuenta` que el informe define como la
   segunda entidad necesaria, y que es justo la pieza que resolvería la "doble imputación de
   cuenta" que motivó el pedido de Juan) **no se migró**. El propio documento lo explica: los
   campos `cuenta_id`/`rubro_id` de las tablas viejas (`iva_clientes`/`iva_proveedores`) "ya
   estaban muertos" porque nadie los leía — la mayorización por línea (migración `0043`) había
   movido la imputación contable a nivel de línea de comprobante, cargada manualmente por el
   usuario en cada alta. Es una razón válida para no migrar *esos campos puntuales*, pero no
   resuelve el problema de fondo que planteaba el informe: **hoy no existe ninguna tabla que
   diga "para la empresa X, el proveedor con CUIT Y se imputa por defecto a la cuenta Z"** — ni
   habilitada, ni con los 376.819 registros ya depurados cargados.

**En síntesis**: la sección 5 del documento del satélite (Padrón Único de Proveedores con regla
por defecto + excepción por contribuyente) no es una propuesta nueva de diseño — es, en su núcleo,
el mismo modelo del informe del 21/07 que quedó pendiente de conectar al sistema nuevo. La
"excepción por contribuyente" del documento 1 es exactamente la tabla de relación
`{empresa, CUIT} → cuenta/rubro` que el informe ya define y cuyos datos reales ya existen en
`Relacion_Contribuyente_Proveedor.xlsx`. Lo que el documento del satélite **sí agrega como
novedad real** frente al informe del 21/07 es la dimensión de **regla por punto de venta del
proveedor** (caso MUCHAY SRL: un mismo proveedor factura conceptos distintos según el PV que
usa) — eso no estaba contemplado en el informe original.

## 3. Comparación pieza por pieza

### 3.1 Padrón Único de Proveedores (identidad)
- **Pide el documento**: proveedor único por CUIT, compartido por todo el estudio.
- **Ya existe**: `iva_sujetos` (`UNIQUE(tenant_id, cuit)`) + `iva_sujeto_empresas` (activación por
  empresa+rol cliente/proveedor), con upsert automático por CUIT. Cubre este punto casi por
  completo.
- **Gap**: ninguno relevante en la identidad. `App\Support\Cuit` ya valida dígito verificador,
  coincidiendo con la regla 2 del informe del 21/07.

### 3.2 Imputación contable del proveedor (por defecto + excepción por contribuyente)
- **Pide el documento**: regla general de cuenta contable por proveedor + excepción por
  contribuyente si no hay regla más específica.
- **Ya existe**: nada conectado al sistema. La mayorización por línea (`cuenta_id` en
  `venta_discriminaciones`/`compra_discriminaciones`, migración `0043`) es 100% manual, elegida
  por el usuario en cada carga — no hay ninguna consulta que derive `cuenta_id` desde
  `proveedor_id`.
- **Insumo real ya disponible**: 376.819 filas depuradas en `Relacion_Contribuyente_Proveedor.xlsx`
  (`EMPRESA_ID, PROVEE_CUIT, RUBRO_ID, CUENTA_ID`) — ver sección 2.
- **Gap**: falta la tabla en el modelo nuevo (algo como `iva_sujeto_empresa_cuenta` o extender
  `iva_sujeto_empresas` con `cuenta_id`/`rubro_id` opcionales) + la lógica de resolución
  (default del sujeto → override por empresa) + la migración de los datos ya depurados.

### 3.3 Regla por punto de venta del proveedor
- **Pide el documento**: que un mismo proveedor pueda facturar conceptos distintos según el punto
  de venta que usa (caso MUCHAY SRL, más de 100 comprobantes/mes desde varios PV).
- **Ya existe**: nada para proveedores, pero **sí existe el mismo patrón arquitectónico** para otro
  dominio: `actividad_punto_venta` (migración `backend/migrations/0036_actividades_dj_iva.php`),
  tabla `{empresa_id, punto_venta} → actividad_id`, resuelta con precedencia en cascada
  (`DjIvaSimpleRepository`, `COALESCE` override-comprobante → receptor → punto de venta →
  alícuota → default). Es estructuralmente el mismo mecanismo que pide el documento, solo que
  resuelve "actividad NAES" en vez de "cuenta contable", y vive a nivel empresa, no a nivel
  padrón global de proveedor.
- **Gap**: extender/replicar este patrón para `{proveedor (o sujeto), punto de venta} → cuenta`,
  con la empresa como nivel de excepción adicional sobre eso.

### 3.4 Camino de compras — bandeja de pendientes
- **Pide el documento**: un comprobante cuyo proveedor no matchea contra el padrón NO se incluye
  en el archivo definitivo; queda en una bandeja de pendientes para revisión manual.
- **Ya existe**: `CompraController::import` valida `proveedor_id` contra `iva_sujetos`
  (`ReferenceValidator`); si no matchea, la fila falla con 422 y se reporta en `errores[]` de la
  respuesta HTTP — no se persiste en ninguna cola de revisión. En el frontend,
  `ImportarPage.tsx` calcula `Issue[]` (errores/warnings) pero solo viven en el estado local del
  componente durante la sesión de importación; se pierden al cerrar la pantalla. Además, hoy el
  proveedor/cliente se manda como **texto libre** en el importador (no como referencia a un
  `Sujeto` del padrón) — el match real ocurre (o no) recién en el backend al crear el comprobante.
- **Gap**: no hay bandeja de pendientes persistente en ningún lado del sistema hoy.

### 3.5 Camino de ventas — clasificación por punto de venta + tipo de comprobante
- **Pide el documento**: motor que mapea PV+tipo de comprobante → concepto contable,
  configurable por contribuyente, donde la asignación puede variar según el tipo de comprobante
  dentro de un mismo PV (ej. una NC se imputa distinto que una factura).
- **Ya existe**: el mismo patrón de `actividad_punto_venta`/`actividad_receptor` (sección 3.3),
  acotado a resolver actividad NAES para el DJ IVA Simple, no cuenta contable general. No hay hoy
  ningún motor genérico de ventas PV+tipo→cuenta fuera de ese caso puntual.
- **Gap**: construir el equivalente para cuenta contable, con la dimensión adicional de tipo de
  comprobante que el patrón actual no contempla.

### 3.6 Motor de alertas estadísticas (sección 7 del documento — el propio documento lo marca
como "fuera de alcance", no cerrado en su mecánica)
- **Pide el documento**: comparar compras/ventas mensuales de cada contribuyente contra su propio
  promedio histórico y alertar ante desvíos significativos (posible bien de uso), reusando —si
  aplica— la lógica de semáforo (verde/amarillo/naranja/rojo) que el documento dice que ya se usa
  en "causales de exclusión de Monotributo".
- **Ya existe**: nada. Un grep exhaustivo en `frontend/` y `backend/` no encontró ningún
  componente de semáforo ni motor de causales de exclusión de Monotributo — la premisa del
  documento de "reusar lógica ya empleada" no tiene dónde apoyarse en el código actual. Esto
  conviene confirmarlo con el usuario antes de asumirlo como insumo real (puede que exista en
  otro sistema del estudio no incluido en este repo, o que sea una idea todavía no implementada
  en ningún lado).
- El único antecedente parcialmente relacionado es el **Panel** de muestra
  (`frontend/src/modules/iva/panel/PanelPage.tsx`), que grafica cantidades/montos del período
  actual único, sin comparación histórica ni promedios — sirve como referencia de estilo visual,
  no de lógica.

## 4. Piezas reutilizables identificadas

El patrón `{dimensión} → valor con precedencia en cascada (COALESCE)` usado en
`actividad_punto_venta` / `actividad_receptor` / `actividad_alicuota` (DJ IVA Simple, migraciones
`0036`/`0037`) es el candidato natural de diseño para construir tanto la regla de imputación
contable por proveedor+PV (compras) como el motor de clasificación de ventas por PV+tipo
comprobante — evita inventar un mecanismo nuevo cuando ya hay uno probado y en producción
resolviendo un problema estructuralmente idéntico.

## 5. Preguntas abiertas

**Ya planteadas por el documento del satélite (su sección 10), siguen sin resolver:**
- Formato exacto del archivo TXT/entrada que consumiría el sistema contable.
- Qué información debe tener la bandeja de pendientes y dónde se revisa.
- Cómo se carga y mantiene el Padrón (pantalla propia vs. base compartida).
- Prioridad entre regla de PV, regla general del proveedor y excepción por contribuyente cuando
  las tres puedan aplicar al mismo comprobante.
- Dónde queda alojada la configuración de liquidación (IVA/IIBB/tasa municipal) por contribuyente.

**Nuevas, surgidas de este análisis:**
- ¿El satélite se construye sobre exportaciones de Visual IVA (como está pensado en el documento),
  o sobre la salida del `extractor` (que ya trae los mismos datos, más ricos, directo de ARCA)?
  Ninguno de los dos caminos está descartado técnicamente; es una decisión de producto/proceso con
  Alex y el estudio.
- ¿Se migran ya los 376.819 registros depurados de `Relacion_Contribuyente_Proveedor.xlsx` como
  semilla de la tabla de imputación por contribuyente, en lugar de "depurar de nuevo" como plantea
  la Etapa 1 de la hoja de ruta del documento? El trabajo de depuración de datos parece ya estar
  hecho (informe del 21/07); lo que falta es la tabla en el sistema nuevo y el código que la
  resuelva.
- ¿Existe en algún otro sistema del estudio (no en este repo) el "semáforo de causales de
  exclusión de Monotributo" que el documento da por sentado como reusable? Conviene confirmarlo
  antes de diseñar el motor de alertas estadísticas asumiendo que existe.
- Los 24 "casos pendientes de catálogo de rubros", los 164 CUIT con nombre variante y los 30 CUIT
  con condición IVA inconsistente que el informe del 21/07 deja para revisión manual siguen sin
  resolver — son un prerrequisito de calidad de datos antes de dar cualquier padrón por cerrado.

## 6. Distancia estimada — "qué tan lejos estamos"

Lectura de esfuerzo por pieza, separando **diseño+datos** (¿ya está pensado/depurado?) de
**construcción** (¿hay que programarlo?):

| Pieza | Diseño / dato | Construcción | Distancia |
|---|---|---|---|
| Identidad del proveedor (padrón único por CUIT) | Hecho | Hecho y en producción | **Ninguna** — `iva_sujetos`/`iva_sujeto_empresas` ya cubre esto. |
| Imputación contable por proveedor (default + excepción por contribuyente) — el corazón del documento 1 | **Hecho** (informe 21/07) y **datos ya depurados** (376.819 filas reales en `Relacion_Contribuyente_Proveedor.xlsx`) | No existe: falta la tabla en el modelo nuevo + el resolver + migrar esos datos | **Corta**. No hay que re-diseñar ni re-depurar — es agregar una tabla, un `COALESCE` de resolución (mismo patrón de §4) y una migración de datos que ya están limpios. |
| Regla por punto de venta del proveedor | No diseñado para proveedores, pero el patrón genérico ya existe (`actividad_punto_venta`) | No existe para este caso | **Media**. Es extender un patrón probado, no inventarlo — pero es una pieza nueva de código. |
| Bandeja de pendientes (compras sin match) | No diseñado | No existe ni en backend ni en frontend (el importador solo reporta errores efímeros) | **Media**. Requiere tabla/estado + pantalla nueva; el detectar el error por fila ya existe. |
| Motor de clasificación de ventas PV+tipo comprobante → cuenta | No diseñado para cuenta contable (solo existe el análogo de actividad NAES) | No existe | **Media**. Mismo patrón reusable, falta la dimensión "tipo de comprobante" y el mapeo a cuenta en sí. |
| Motor de alertas estadísticas | El propio documento 1 lo deja "no cerrado en su mecánica"; el semáforo que asume reusable no existe en este repo | No existe nada, ni parcial | **Larga**. Necesita definición de producto (umbral, mecánica) antes de escribir una línea de código — es la única pieza que no tiene ni diseño ni antecedente de código. |

**Veredicto global**: la pieza que motivó todo el documento —unificar el criterio de imputación
contable de proveedores— está sorprendentemente **cerca**, porque el trabajo pesado (modelo +
depuración de 376.819 filas reales) ya se hizo el 21/07 y quedó parcialmente sin conectar cuando
se implementó el padrón el 23/07. Las piezas de "reglas por punto de venta" y "clasificación de
ventas" están a distancia media: no hay que inventar la arquitectura (ya existe y funciona para
actividad NAES), pero sí hay que construir las tablas/resolvers específicos. La bandeja de
pendientes es una feature de UI+backend acotada, sin antecedente directo. El motor de alertas
estadísticas es lo único genuinamente lejos, porque ni su mecánica está definida todavía — no es
una cuestión de código faltante sino de decisión de producto pendiente.

Lo que **no** está ni cerca ni lejos porque es una decisión previa a cualquier estimación: si el
satélite se construye sobre exportaciones de Visual IVA (como plantea el documento) o sobre la
salida del `extractor` (que ya trae los mismos datos, más completos, directo de ARCA). Esa
decisión cambia qué "entrada" hay que parsear, pero no cambia el tamaño de las piezas de
clasificación/padrón descriptas arriba, que son el verdadero cuerpo del trabajo.

## 7. Diseño de las modificaciones — extender el módulo Iva, no construir una app aparte

Decisión de enfoque: en lugar de construir el "satélite" como aplicación puente separada (como
plantea literalmente el documento 1), lo que pide se puede resolver **extendiendo el módulo Iva
ya existente** (`backend/app/Modules/Iva/`, `frontend/src/modules/iva/`), reusando sus propias
convenciones (Repository/Service/Controller, patrón de reglas con precedencia). El sistema nuevo
ya *es*, en la práctica, el reemplazo de Visual IVA — no necesita un puente hacia sí mismo.

### 7.1 Modelo de datos nuevo

1. **Imputación contable del sujeto** — cierra lo que quedó pendiente de la migración `0048`
   respecto del modelo del informe del 21/07 (Padrón + Configuración por Contribuyente):
   - `cuenta_id`/`rubro_id` nullable en `iva_sujetos` = regla **global** del proveedor (default
     para todo el estudio) — documento §5.4, "regla por defecto del proveedor".
   - `cuenta_id`/`rubro_id` nullable en `iva_sujeto_empresas` = **excepción por contribuyente**
     (§5.2) — si está seteada, pisa la regla global para esa empresa puntual.
2. **Regla por punto de venta del proveedor** (§5.4, caso MUCHAY SRL) — dos tablas nuevas, mismo
   patrón que `actividad_punto_venta`/`actividad_receptor` (migraciones `0036`/`0037`):
   - `iva_sujeto_punto_venta` (`sujeto_id, punto_venta, cuenta_id, rubro_id`,
     `UNIQUE(sujeto_id, punto_venta)`) = regla global del proveedor para ese PV.
   - `iva_sujeto_punto_venta_empresa` (`empresa_id, sujeto_id, punto_venta, cuenta_id, rubro_id`)
     = excepción por contribuyente de esa regla de PV.
   - Resolución, mismo patrón `LEFT JOIN`+`COALESCE` que ya usa `DjIvaSimpleRepository`: override
     manual de línea (ya existe, `compra_discriminaciones.cuenta_id`) → excepción
     `{empresa,sujeto,PV}` → regla global `{sujeto,PV}` → excepción `{empresa,sujeto}` → default
     global del sujeto → **sin regla → bandeja de pendientes**.
3. **Bandeja de pendientes** (§3) — tabla nueva (ej. `iva_comprobantes_pendientes`): comprobante
   crudo + motivo (`proveedor_no_encontrado` / `cuit_invalido` / `sin_regla_imputacion`) +
   empresa/período + estado. Endpoints para listar y resolver (asignar/crear sujeto o cuenta y
   recién ahí crear el comprobante definitivo).
4. **Motor de clasificación de ventas por PV+tipo comprobante** (§4) — tabla nueva
   `iva_venta_punto_venta_cuenta` (`empresa_id, punto_venta, tipo_comprobante_id NULLABLE (NULL =
   default del PV), cuenta_id`), mismo patrón de resolución.

### 7.2 Hallazgo nuevo sobre el importador actual (más profundo de lo que parecía)

Verificado directamente en el código (no solo por resumen de agente): `ImportarPage.tsx` manda
`proveedor_nombre`/`cliente_nombre` como **texto libre**, nunca `proveedor_id`/`cliente_id`.
Combinado con que `CompraService::assertReferencias` solo valida `proveedor_id` cuando viene
informado, esto significa que **hoy el importador CSV nunca intenta matchear contra el padrón**:
todo comprobante importado entra como "sujeto ocasional", sin tocar `iva_sujetos` en absoluto. No
es que falle con 422 al no matchear (eso es lo que pasa en el alta manual con `proveedor_id`
explícito) — en el importador simplemente no se lo intenta. Hay que agregar esa resolución
CUIT→padrón al import, que hoy no existe.

### 7.3 Cambios en servicios/controllers existentes

- `CompraController::import`/`VentaController::import` (y el alta manual): agregar la resolución
  CUIT→`iva_sujetos` (7.2) y aplicar el resolver de cuenta (7.1) para **pre-llenar**
  `cuenta_id`/`cuenta_debe_id`, dejando override manual disponible (mismo patrón UX que ya usa el
  sistema para el override de importe de IVA: precarga con lo calculado, el usuario puede pisarlo).
- Si no hay proveedor identificable ni regla de cuenta resuelta: no crear el comprobante, insertar
  en la bandeja de pendientes en vez de fallar en silencio o crear un "sujeto ocasional".
- Reusar `SujetoEmpresaRepository::activar` (ya existe, se dispara en `assertReferencias`) para
  seguir activando automáticamente el vínculo empresa+sujeto al resolver una compra/venta.

### 7.4 Frontend

- `SujetoFormModal.tsx`: sección opcional "Imputación contable por defecto" (cuenta/rubro) a nivel
  padrón, y override por empresa.
- Nueva pantalla de reglas por punto de venta del proveedor — mismo patrón visual que
  `ActividadesPage.tsx` (form simple + tabla + precedencia documentada).
- Nueva página "Pendientes de importación" (bajo IVA o Utilidades) para revisar/resolver la
  bandeja.
- `ImportarPage.tsx`: dejar de mandar `proveedor_nombre`/`cliente_nombre` como texto libre por
  defecto — intentar resolver contra el padrón por CUIT (reusar `SujetoTypeahead` ya existente) y
  mostrar en el preview si matchea antes de importar.
- Nueva pantalla de reglas de clasificación de ventas por PV+tipo comprobante — mismo patrón que
  `ActividadesPage.tsx`.

### 7.5 Qué reutilizar de `extractor/`

- `ComprobanteScrapeado` (`extractor/src/types.ts`) mapea casi 1:1 a lo que ya consume el
  importador del sistema nuevo (CUIT, denominación, punto de venta, tipo de comprobante, desglose
  por alícuota, percepciones). Si se decide usar el extractor como fuente en vez de exportaciones
  de Visual IVA, su CSV/xlsx puede alimentar el importador ya existente con un perfil de mapeo
  preconfigurado — sin pasar por el paso `convertir` del extractor (que reconstruye el formato
  Visual IVA, innecesario si el destino final es el sistema nuevo directamente).
- El scraping/sesión de Playwright (`extractor/src/auth/`, `extractor/src/flows/`) no tiene
  análogo reusable en `backend/`/`frontend/` — sigue como infraestructura propia del extractor,
  sin cambios.
- El parser CSV robusto de encoding/zip (`extractor/src/extract/csv.ts`) es una referencia útil si
  el importador de frontend necesita ese nivel de robustez más adelante — no es prioritario ahora.

### 7.6 Qué NO construir

- No hace falta una app "satélite" aparte: todo lo de 7.1-7.4 encaja como extensión natural del
  módulo Iva, con las mismas convenciones que ya usa `DjIvaSimpleRepository`.
- No hace falta re-diseñar el modelo de Padrón + Configuración por Contribuyente — ya está
  diseñado (informe 21/07) y sus datos reales depurados (`Relacion_Contribuyente_Proveedor.xlsx`,
  376.819 filas) están listos para sembrar en cuanto exista la tabla de 7.1.1.
- El motor de alertas estadísticas (§7 del documento) queda fuera de este diseño — su mecánica no
  está definida todavía (el propio documento lo dice) y no depende de nada de lo anterior.

### 7.7 Secuencia recomendada

1. ✅ **HECHO (30/07/2026)**: migraciones + resolver de imputación por proveedor (7.1.1/7.1.2).
   Migración `0049_iva_sujeto_imputacion_contable.php` (`iva_sujeto_empresas.cuenta_id` +
   tabla `iva_sujeto_punto_venta`) + `ImputacionContableRepository::resolverCuenta` (precedencia
   PV → default → sin regla) + `SujetoEmpresaRepository::setCuenta`. Test `ImputacionContableTest`
   (588 tests verdes, PHPStan/PHPCS OK). Todavía **sin** endpoints HTTP ni UI — se ejercita
   instanciando los repositorios directo en el test, a la espera del paso 2.
2. ✅ **HECHO (30/07/2026)**: conectado el resolver a compras (import + alta manual) + arreglado
   el match CUIT→padrón, para compras y ventas. El fix terminó siendo **puramente backend, sin
   tocar `ImportarPage.tsx`**: tanto `compras` como `ventas` ya tenían una columna `cuit` de
   cabecera independiente de `proveedor_id`/`cliente_id` (la que usa el "sujeto ocasional"), y el
   importador ya la manda — bastó con que `CompraService`/`VentaService` busquen ese CUIT en
   `iva_sujetos` (`resolverProveedorPorCuit`/`resolverClientePorCuit`, al principio de
   `create()`/`update()`, antes de validar referencias) y completen el id si matchea, sin fallar
   si no hay match (sigue creándose como sujeto ocasional, mismo comportamiento de antes). Un
   `proveedor_id`/`cliente_id` explícito siempre gana sobre el CUIT. Para compras, además,
   `CompraService::preparar()` ahora resuelve la cuenta contable por defecto (vía
   `ImputacionContableRepository::resolverCuenta`, usando el `proveedor_id` ya resuelto + el punto
   de venta de cabecera) y la precarga en las líneas de `discriminaciones` que no traigan
   `cuenta_id` propio — una línea con cuenta manual (caso resumen bancario multi-cuenta) nunca se
   pisa. Tests `ResolverSujetoPorCuitTest` (match compras/ventas, sin match, explícito no se pisa)
   + 2 tests nuevos en `ImputacionContableTest` (default end-to-end, override no se pisa). **594
   tests verdes**, PHPStan/PHPCS OK. Sigue sin haber endpoints HTTP/UI para cargar las reglas de
   imputación (`cuenta_id` default, `iva_sujeto_punto_venta`) — eso es el paso 6.
3. ✅ **HECHO (30/07/2026), variante liviana**: bandeja de pendientes (7.1.3). El documento pide que
   un comprobante sin match **no se incluya** en el resultado — pero eso hubiera roto el flujo de
   "sujeto ocasional" que el importador ya usa en producción. Antes de implementar se le preguntó
   al usuario el alcance (bloquear vs. no bloquear) y **eligió no tocar el comportamiento de
   creación/import** (sigue creando "sujeto ocasional" igual que en la Parte 2). Se agregó solo un
   endpoint de **lectura**: `GET .../compras/pendientes` y `GET .../ventas/pendientes`
   (`CompraRepository`/`VentaRepository::findPendientes`, `WHERE periodo_id = ? AND
   proveedor_id/cliente_id IS NULL`) — listan, por período, los comprobantes que quedaron sin
   sujeto del padrón. No hay tabla nueva, ni motivo/estado persistido, ni endpoint de "resolver"
   dedicado: se resuelven con el `PUT` de compra/venta que ya existe (que desde la Parte 2 ya
   matchea por CUIT al editar, o acepta `proveedor_id`/`cliente_id` explícito). ⚠️ Detalle de
   ruteo: `GET .../compras/pendientes` tuvo que registrarse **antes** de `GET .../compras/{id}` en
   `routes.php` — el router matchea patrones en orden de registro y `{id}` (`[^/]+`) capturaría el
   literal "pendientes" si se declarara después. Test `PendientesComprobantesTest` (compras, ventas,
   y que el `PUT` existente saca a un comprobante de la bandeja). **597 tests verdes**, PHPStan/PHPCS
   OK. Sigue sin haber UI (paso 6).
4. ⏸️ **BLOQUEADO/POSPUESTO (30/07/2026), decisión del usuario**: migrar los 376.819 registros ya
   depurados como semilla. Verificado en esta sesión que hoy no hay contra qué engancharlos:
   `empresas` en desarrollo tiene 16 filas de prueba (no las 351 reales), `cuentas` está **vacía**
   (0 filas), y la migración general de datos reales (`softContable/migracion/README.md`) todavía
   no arrancó (sigue en "Fase A — Reconocimiento", nada tildado). Tampoco existe ningún archivo que
   traduzca el `CUENTA_ID` numérico de Visual IVA a un nombre de cuenta real. El usuario eligió
   saltear este paso y seguir con el 5; se retoma cuando la migración general esté más avanzada.
5. ✅ **HECHO (30/07/2026)**: motor de clasificación de ventas por PV+tipo de comprobante (7.1.4).
   Migración `0050_iva_venta_punto_venta_cuenta.php`: `iva_venta_punto_venta` (`empresa_id,
   punto_venta, cuenta_id`, regla general del PV) + `iva_venta_punto_venta_tipo` (`empresa_id,
   punto_venta, tipo_comprobante_id, cuenta_id`, excepción — caso NC vs. Factura del documento).
   Dos tablas en vez de una con `tipo_comprobante_id` nullable, mismo motivo que en la Parte 1: el
   `UNIQUE` de MySQL no bloquea duplicados cuando una columna es NULL.
   `VentaClasificacionRepository::resolverCuenta` resuelve con precedencia tipo específico →
   regla general del PV → sin regla. A diferencia de compras, no depende de resolver un
   cliente — el punto de venta es del propio contribuyente. Conectado a `VentaService::preparar()`
   con el mismo criterio que compras en la Parte 2 (override manual de línea siempre gana). Test
   `VentaClasificacionTest`. **602 tests verdes**, PHPStan/PHPCS OK.
6. UI de administración de todas las reglas — sigue pendiente. Con las Partes 1, 3 y 5 hechas, ya
   hay tres motores de reglas sin ninguna pantalla propia (imputación de compras, bandeja de
   pendientes, clasificación de ventas) — buen momento para priorizar este paso antes de seguir
   agregando motores.

## 8. Panorama de pantallas (Parte 6)

Inventario de las pantallas que hacen falta para las tres piezas de backend ya construidas
(Partes 1, 3 y 5), verificado contra el frontend real (`frontend/src/layout/nav.ts` y
`frontend/src/modules/iva/actividades/ActividadesPage.tsx`, el patrón ya usado para exactamente
este tipo de reglas: secciones apiladas, cada una con tabla + form chico, sin tabs).

**A. Compras — cuenta por defecto del proveedor** (`iva_sujeto_empresas.cuenta_id`): campo select
nuevo dentro de `SujetoFormModal.tsx` (solo al editar un proveedor existente, requiere
`sujeto_id`). El cambio más chico de los cuatro — un campo en un modal que ya existe.
✅ **HECHO (31/07/2026)**: no había endpoint HTTP para esto todavía (Parte 1 solo dejó el
repositorio) — se agregó `cuenta_id` a `UpdateSujetoRequest` (no al alta: la cuenta vive en
`iva_sujeto_empresas`, que recién existe una vez activado el sujeto en la empresa) +
`SujetoService::update`/`get` (valida que la cuenta pertenezca al plan de esa empresa vía
`ReferenceValidator`, persiste con `SujetoEmpresaRepository::setCuenta`/`cuentaDe`). Frontend:
select nuevo en `SujetoFormModal.tsx` (visible solo `esProveedor && sujeto`, fetch de
`listCuentas(empresaId)`), `SujetoFormModal` pasa a recibir `empresaId`. Test
`SujetoCuentaPorDefectoTest` (persiste y se refleja en show/listado, cuenta de otra empresa da
422, `null` borra la regla). **605 tests verdes**, `tsc`/`oxlint`/`vite build` OK. Verificado E2E
en navegador real. **Segunda instancia del mismo bug preexistente de la Pantalla C** (esta vez en
`SujetoFormModal.tsx`, no en Compra/VentaFormModal): `fecha_cai: ''` en vez de `null` rompía el
alta/edición de cualquier proveedor con "Vto. CAI" vacío (`SQLSTATE[22007]`, columna DATE). Se
corrigió en el origen (frontend, no parche de backend): `SujetoFormModal` ahora normaliza con el
mismo patrón `strOrNull` que ya usan `Compra`/`VentaFormModal`.

**B. Compras — reglas por punto de venta del proveedor** (`iva_sujeto_punto_venta`): necesita
tabla+form propios (varias filas por proveedor), no entra en el modal chico de alta. Dos opciones
a decidir al implementar: (B1) sección nueva en una vista de detalle del proveedor que hoy no
existe (solo hay lista+modal), o (B2) página nueva `/empresas/:id/proveedores/:provId/imputacion`.
Recomendado B2 salvo que se justifique crear la vista de detalle por otras razones.
✅ **HECHO (31/07/2026)**, decisión del usuario: **B2**. Tampoco había endpoint HTTP (la Parte 1
solo dejó el repositorio) — `ImputacionContableService`/`ImputacionContableController` nuevos
(mismo patrón que `VentaClasificacionService`/`Controller` de la Pantalla D: valida
empresa→tenant + que el proveedor esté activo en esa empresa vía `SujetoEmpresaRepository::
existeActivo`, y que la cuenta pertenezca al plan de esa empresa) + rutas
`{$base}/proveedores/{proveedorId}/imputacion` (GET/POST/DELETE, permiso `iva.proveedores`,
anidada un nivel más que `/proveedores/{id}` — sin colisión de router). Frontend:
`api/imputacionContable.ts` nuevo + página `ProveedorImputacionPage.tsx` (mismo layout que la
sección "Regla general por punto de venta" de `ActividadesPage.tsx`: form PV+cuenta y tabla con
borrar), con el nombre/CUIT del proveedor resuelto por `listSujetos` (no hay GET-by-id de sujeto
en el frontend). Botón "Imputación" agregado al listado de proveedores (`SujetosList.tsx`, solo
`esProveedor`). Test `ImputacionContableHttpTest` (crea/lista/borra + cuenta de otra empresa da
422 + proveedor inexistente/no activo da 404). **611 tests verdes**, PHPStan/PHPCS/tsc/oxlint/
vite build OK. Verificado E2E en navegador real: regla PV 0005 → 5001 Combustibles y Lubricantes
se crea, lista y borra correctamente, sin errores de consola. **Cierra las 4 pantallas del
panorama.**

**C. Bandeja de pendientes** (`GET .../compras/pendientes` y `.../ventas/pendientes`):
período-scoped, igual que las listas de Ventas/Compras que ya existen. Recomendado no crear
página aparte — agregar un filtro/toggle "Solo pendientes" a esas mismas listas (reusan la tabla
existente) + un badge con el conteo. La de menor esfuerzo de las cuatro.
✅ **HECHO (31/07/2026)**: `listComprasPendientes`/`listVentasPendientes` en `api/compras.ts`/
`api/ventas.ts` + alerta con conteo y tabla colapsable "Ver pendientes" en `ComprasList.tsx`/
`VentasList.tsx`. El botón "Asignar proveedor/cliente" reusa el modal de edición existente (sin
modal nuevo); al guardar, la query de pendientes se invalida y el badge se actualiza. Verificado
E2E en navegador: compra sin match aparece en la bandeja, compra con proveedor del padrón no
entra, y asignar el proveedor desde la bandeja la saca (badge 2→1), sin errores de consola.
`tsc`/`oxlint`/`vite build` verdes. **Bug preexistente encontrado y arreglado en el camino**: el
modal manda `null` en los importes vacíos (`neto_no_grav`/`exento`/`imp_interno`, columnas NOT
NULL con DEFAULT) y el INSERT/UPDATE explotaba con 500 — `CompraService`/`VentaService` ahora los
normalizan a 0 (`normalizarImportesOpcionales`), 602 tests verdes.

**D. Ventas — clasificación por punto de venta + tipo de comprobante**
(`iva_venta_punto_venta`/`iva_venta_punto_venta_tipo`): mismo patrón exacto que ya usa
`ActividadesPage.tsx` para `actividad_punto_venta`. Recomendado agregar dos secciones nuevas a esa
misma página (`/empresas/:id/actividades`) en vez de una página aparte — mismo dueño (empresa),
mismo patrón de UI, evita fragmentar el sidebar.
✅ **HECHO (31/07/2026)**: tampoco había endpoint HTTP (la Parte 5 solo dejó el repositorio) —
`VentaClasificacionService`/`VentaClasificacionController` nuevos (mismo patrón que
`EmpresaActividadService`/`Controller`: valida empresa→tenant, y que la cuenta pertenezca al plan
de esa empresa) + rutas `{$base}/ventas-punto-venta` y `{$base}/ventas-punto-venta-tipo`
(GET/POST/DELETE, permiso `iva.ventas`). Frontend: `api/ventaClasificacion.ts` nuevo + dos
secciones agregadas a `ActividadesPage.tsx` ("Regla general por punto de venta" y "Excepción por
tipo de comprobante"), reusando `listCuentas` (ya usado en la Pantalla A) y el catálogo
`tipos-comprobante`. Test `VentaClasificacionHttpTest`. **608 tests verdes**,
PHPStan/PHPCS/tsc/oxlint/vite build OK. Verificado E2E en navegador real: regla por PV (0003 →
Combustibles y Lubricantes) y excepción por tipo (0003 + Nota de Crédito A,B,C,E,M → otra cuenta)
persisten, se listan y se borran correctamente, sin errores de consola.

**Orden recomendado**: C (menor esfuerzo, da visibilidad inmediata) → A (campo en modal existente,
bajo riesgo) → D (extiende una página que ya sabe hacer este patrón) → B (la más nueva
estructuralmente, requiere decidir B1 vs. B2).

## 10. Estado final de las 4 pantallas (31/07/2026)

**Las 4 pantallas (C, A, D y B) implementadas y verificadas E2E.** B (reglas por punto de venta
del proveedor) se resolvió con la decisión del usuario "B2": página aparte
`/empresas/:id/proveedores/:provId/imputacion` en vez de construir una vista de detalle de
proveedor nueva. No queda ninguna pantalla del panorama pendiente.

La **Parte 4** (sembrar los 376.819 registros ya depurados de
`Relacion_Contribuyente_Proveedor.xlsx`) sigue bloqueada: sin empresas reales, sin `cuentas`
cargadas, sin la migración general de datos (`softContable/migracion/`) arrancada, y sin un
archivo que traduzca `CUENTA_ID`/`RUBRO_ID` de Visual IVA a algo utilizable. Se retoma cuando esos
insumos existan.

## 9. Alcance de este documento

Este es un análisis comparativo/diagnóstico y un diseño técnico, no una implementación. El propio
documento del satélite es explícito en que es una propuesta de *proceso* pendiente de acuerdo
antes de pasar a diseño técnico — la sección 7 de este documento avanza esa etapa de diseño, pero
no se escribió código todavía: no se modificó `backend/`, `frontend/` ni `extractor/`.

## 11. Cierre de las 3 brechas restantes (31/07/2026)

Tras completar las 4 pantallas (§10), se re-analizó `satelite/documento-1 (1).pdf` contra el
código y aparecieron 3 brechas reales, todas cerradas en esta ronda con decisiones explícitas del
usuario:

- [x] **Vista global del padrón** (doc. §10, Etapa 4: "consulta del padrón global"). Antes,
  `SujetoRepository`/`SujetoService` solo listaban sujetos por empresa — no existía ninguna
  pantalla "todos los proveedores/clientes del estudio". `SujetoRepository::listAllByTenant` +
  `SujetoEmpresaRepository::empresasActivasDe` + `SujetoService::listGlobal` +
  `PadronUnicoController` (nombre distinto de `PadronController`, que es la consulta al padrón de
  AFIP, no relacionada) → `GET /padron-unico` (tenant-wide). Frontend: `PadronUnicoPage.tsx`
  (`/padron-unico`), con cada fila linkeando a `/empresas/{id}/proveedores?q=CUIT` — se agregó
  soporte de `?q=` en `SujetosList.tsx` para el deep-link. Verificado E2E: CUIT compartido entre
  dos empresas aparece una sola vez con sus dos activaciones; el link navega y precarga la
  búsqueda.
- [x] **Regla de punto de venta realmente global** (doc. §5.4, caso MUCHAY SRL). Antes, la regla
  de PV (Pantalla B) estaba scopeada por `(empresa_id, sujeto_id, punto_venta)` porque `cuentas`
  es un catálogo por-empresa — cargar la misma regla para 5 empresas exigía repetirla 5 veces. El
  usuario eligió la opción de fondo (no la de menor esfuerzo): una capa de **"concepto"**
  (`iva_conceptos`, tenant-level) + mapeo `empresa_concepto_cuenta` (cada empresa traduce el
  concepto a su propia cuenta). Migración `0051_iva_conceptos_imputacion.php`: agrega
  `concepto_default_id` a `iva_sujetos` (default global), `concepto_id` a `iva_sujeto_empresas`
  (excepción del default por empresa), rediseña `iva_sujeto_punto_venta` sin `empresa_id` (regla
  global de PV) y agrega `iva_sujeto_punto_venta_empresa` (excepción de PV por empresa) — cadena
  de resolución de 5 niveles en `ImputacionContableRepository::resolverCuenta`, con el mismo
  contrato externo que ya usaba `CompraService` (sin tocarlo). `ConceptoRepository/Service/
  Controller` (CRUD del catálogo, `/iva/conceptos`). `ImputacionContableService/Controller`
  ampliados a 4 secciones (regla global, excepción de PV por empresa, excepción de concepto por
  defecto, mapeo concepto→cuenta). Frontend: `SujetoFormModal.tsx` (concepto default, tenant-wide,
  ya no depende de `empresaId`), `ProveedorImputacionPage.tsx` reestructurada en 3 secciones,
  pestaña **Conceptos** en Utilidades, sección **"Mapeo de conceptos → cuentas"** en
  `ActividadesPage.tsx`. Verificado E2E completo: concepto creado en Utilidades → mapeado a una
  cuenta en Actividades de una empresa → regla global de PV cargada desde Proveedores →
  Imputación → la cuenta se resuelve correctamente para esa empresa → borrado. Reemplazo limpio
  del modelo de la migración 0049 (sin migrar datos: no había datos reales de producción en este
  frente todavía).
- [x] **Motor de alertas estadísticas v1** (doc. §7). El documento pide definir el umbral y si
  reusa un "semáforo" de Monotributo antes de programar; se confirmó por grep que ese semáforo no
  existe en el código. Se construyó una v1 igual, con el supuesto documentado como pregunta abierta
  (`preguntas.md` §E, mismo patrón que el resto de las decisiones de dominio del proyecto) en vez
  de esperar. `AlertaEstadisticaCalculator` (puro, `UMBRAL_DESVIO=30%`,
  `MIN_PERIODOS_HISTORIAL=3`) + `AlertaEstadisticaService` (compara el último período de cada
  empresa del tenant contra el promedio de los anteriores, para compras y ventas — reusa
  `LibroIvaRepository`/`LibroIvaCalculator`, sin tablas ni columnas nuevas, calculado al vuelo
  igual que el resto de los totales del sistema) + `GET /alertas` (tenant-wide, sin permiso
  granular nuevo). Frontend: `AlertasPage.tsx` (`/alertas`), toggle "solo alertas". Tests:
  `AlertaEstadisticaCalculatorTest` (unit) + `AlertaEstadisticaTest` (feature, con comprobantes
  reales — detecta un salto de 1000→3000 en el 4° período contra un historial estable de 1210).

**Explícitamente fuera de esta ronda** (decisión del usuario, coherente con el propio documento):
la configuración general de liquidación IVA/IIBB/tasa municipal (doc. §8, "Fuera de alcance" de la
propuesta del satélite).

**630 tests verdes** (+19 sobre v0.3.17), PHPStan 6 OK, PHPCS limpio, `tsc`/`oxlint`/`vite build`
verdes. Con esto, no queda ninguna brecha accionable del documento — solo la Parte 4 (sembrar los
376.819 registros reales) sigue bloqueada por falta de datos, y el motor de alertas queda con un
supuesto de umbral pendiente de confirmar con el contador.
