# DECISIONS.md

Decisiones técnicas en orden cronológico. Las de diseño "fundacionales" se infieren del
código y del README (marcadas *(inferido)*); las de la sesión del **2026-06-07** constan
con su commit.

---

## Decisiones fundacionales (previas a esta sesión)

### F1 — Modular monolith con auto-discovery *(inferido del código)*
- **Qué**: módulos en `app/Modules/X` con `routes.php` + Controller/Service/Repository/
  Request, descubiertos por glob en `bootstrap/app.php`; sin registro manual.
- **Por qué**: aislar dominios manteniendo un solo deploy/proceso.
- **Tradeoff**: los `routes.php` dependen de un `$router` global inyectado → falso
  positivo en PHPStan (gestionado vía baseline).

### F2 — Raw PDO, sin ORM *(confirmado en README "Why not Laravel?")*
- **Qué**: repositorios con SQL directo sobre un `PDO` singleton.
- **Por qué**: control total de queries, mínimas dependencias, todo el flujo es legible.
- **Tradeoff**: más boilerplate por entidad; sin migraciones/relaciones mágicas.

### F3 — DI por constructor con autowiring, sin facades *(inferido)*
- **Qué**: `Container` resuelve dependencias por reflection; middleware parametrizado vía
  `makeWith` (`Clase:param`).
- **Por qué**: explicitud, testeabilidad, nada de service locator global.

### F4 — Multi-tenancy por `tenant_id` UUID + JWT claim *(inferido/confirmado)*
- **Qué**: `tenant_id CHAR(36)` en tablas de datos; el JWT lleva `tenant_id`;
  `TenantMiddleware` lo valida; los repos filtran por él.
- **Por qué**: aislamiento row-level simple sin esquemas por tenant.

### F5 — JWT con revocación por DB *(confirmado en código)*
- **Qué**: el access token se guarda en `usuarios.token`; `AuthMiddleware`/`JwtGuard`
  verifican que exista (revocación), además del `exp`.
- **Por qué**: poder invalidar tokens (logout) antes de su expiración.
- **Tradeoff**: un `SELECT` por request autenticado.

### F6 — Cola de jobs DB-backed + eventos síncronos *(confirmado)*
- **Qué**: tabla `jobs`, `JobDispatcher`, worker `queue:work`; `EventDispatcher`
  síncrono.
- **Por qué**: async básico sin broker externo (Redis/RabbitMQ).

*(Cronología git previa, sin detalle de razón: `wf` → `package test` → varios `test ci`
→ `fix workflow path` → `tenant clients`/`merge tenants`. Indica que la multi-tenancy de
clientes y el CI se incorporaron justo antes de esta sesión.)*

---

## Sesión 2026-06-07

### D1 — Eliminar el clon embebido `backend/` (commit `66fd2a2`)
- **Qué**: borrar el directorio `backend/` (4159 archivos trackeados: clon viejo del
  propio repo, con su `.git` y `vendor/` commiteado, 3 commits atrasado).
- **Por qué**: duplicado desactualizado que confundía; su `docker-compose`/`composer`
  apuntaban a rutas heredadas (`./backend`, `../../modulos/ia`).
- **Tradeoff/cuidado**: contenía el único `app/Modules/IA` y un `.env` con secreto →
  se rescató el módulo y se anotó el secreto antes de borrar.

### D2 — IA como add-on opcional, fuera del base (commit `66fd2a2`)
- **Qué**: quitar `cynchro/modux-ia` del `require` y el repositorio `path` local; moverlo
  a `suggest`; no incluir `app/Modules/IA` en el base; documentar instalación opcional.
- **Por qué**: Packagist confirma que es una **library independiente** (`composer require
  cynchro/modux-ia`); el framework "viene sin IA por default". Mantiene las ~5 deps.
- **Tradeoff**: el base quedaba antes en estado inconsistente (requería el SDK sin el
  módulo que lo usa); se corrigió.

### D3 — `docker-compose` build desde la raíz (commit `66fd2a2`)
- **Qué**: `context: ./backend` → `context: .`, volumen `.:/var/www/html`.
- **Por qué**: el código real vive en la raíz; el build fallaba apuntando a `./backend`.

### D4 — Resolver 2 conflictos de merge en README manteniendo HEAD (commit `66fd2a2`)
- **Qué**: el README arrastraba marcadores `<<<<<<<` sin resolver desde "merge tenants"
  (sección Job queue y tabla "Why not Laravel?").
- **Por qué**: HEAD era el superset correcto (documenta el Job queue real).

### D5 — Actualizar PHPStan 1.12 → 2.x (commit `8b3678c`)
- **Qué**: subir a `^2.0` (2.2.2); 2.x detectó `Response::$rawBody` como propiedad muerta
  (rama inalcanzable en `send()`).
- **Decisión del usuario**: **eliminar el código muerto** (vs. completar la API con un
  método `raw()`). Se quitó la propiedad y su rama.
- **Por qué**: 2.x es más estricto y útil; el código no usaba `rawBody`.
- **Tradeoff**: se descartó la capacidad latente de respuestas no-JSON; baseline
  regenerado a formato 2.x (90 avisos preexistentes documentados).

### D6 — Limpiar `.env.example` (commit `8b3678c`)
- **Qué**: quitar variables `AI_*/LLAMA_*/RAG_*` (dejando una nota).
- **Por qué**: coherente con IA opcional (D2); esas vars solo aplican si se instala el
  módulo IA.

### D7 — Quality gate de doble capa (commit `8b3678c`)
- **Qué**: `.githooks/pre-push` (corre `composer validate/lint/analyse/test`, se activa
  con `git config core.hooksPath .githooks`) + `.github/workflows/ci.yml` (mismos checks +
  build Docker).
- **Por qué**: la base es pública y versionada; no se puede pushear algo roto.
- **Tradeoff**: el hook es config local por clon (no se versiona la activación) →
  documentado en README; bypass de emergencia con `git push --no-verify`.

### D8 — Limpiar el secreto del historial con `git filter-repo` (durante el push)
- **Qué**: GitHub Push Protection bloqueó el push por la Groq API key en `backend/.env`.
  Como los commits **no estaban en `origin`**, se reescribió el historial local para
  eliminar `backend/.env` por completo y luego se pusheó.
- **Decisión del usuario**: **limpiar historial** (vs. usar "allow secret", que dejaría el
  secreto público para siempre).
- **Por qué**: es la opción limpia y sin impacto downstream (commits no publicados).
- **Tradeoff**: reescribe hashes locales (sin efecto en forks/clones porque no se habían
  publicado). **Pendiente**: rotar la key en Groq (estuvo en disco en claro).

### D9 — ADR 0001: arquitectura de la capa SaaS (commit `86fc12f`)
- **Qué**: `docs/adr/0001-saas-identity-entitlements-billing.md`. Define el mapa
  base-vs-módulos y las decisiones D-internas:
  - **Insight central**: `entitlements ≠ billing`. Entitlements = *autorización* → base.
    Billing = *comercial* → módulo. Billing **puebla** `tenant_entitlements`; el base solo
    **lee** (así `modux-ia` no arrastra billing).
  - **D1 (ADR)**: features **namespaced** (`ia.rag`, `bots.outbound`).
  - **D2 (ADR)**: quota **anclada al ciclo de la suscripción**, con `period_start/
    period_end` **denormalizados** en `tenant_entitlements` para que el base no dependa de
    la tabla `subscriptions` del módulo billing. Fallback de mes calendario sin billing +
    comando `entitlements:roll-periods` como red de seguridad.
  - **D3 (ADR)**: `scope` (api key, qué endpoint) y `entitlement` (tenant, qué tiene) son
    **ortogonales** → ambos checks. Pipeline:
    `Auth → Tenant → Scope(403) → Entitlement(402/403) → Quota(429 + Retry-After)`.
- **Por qué**: separar identidad/autorización (chasis liviano) de lo pesado/comercial
  (módulos), evitando acoplar add-ons al billing.
- **Tradeoff**: el contrato de entitlements pasa a ser API pública → SemVer estricto.

### D10 — Fase 1: auth multi-esquema vía guards (commit `7bae294`)
- **Qué**: `GuardInterface`; `AuthMiddleware` refactorizado de `(PDO)` a
  `(JwtGuard, ApiKeyGuard)`, itera guards y produce un `Principal`; `ApiKeyManager`
  (token `mk_<env>_<id>_<secret>`, en DB solo `prefix` + `sha256`); `ScopeMiddleware`;
  migración `0007_create_api_keys_table`; `Request::principal()/setPrincipal()`.
- **Por qué**: habilitar "API para terceros" (API keys + scopes) sin romper el JWT.
- **Cómo se mitigó el riesgo**: `AuthMiddleware` sigue llamando `setUser()` →
  `Tenant`/`PermissionMiddleware` y `$request->user()` intactos (retrocompatibilidad).
  Discriminación de esquema por prefijo `mk_` (JWT vs API key). 24 tests + e2e Docker.
- **Tradeoff**: refactor de un componente crítico de producción (asumido y cubierto).

### D11 — Fase 1.b: módulo `ApiKeys` con CRUD (commit `69ad4aa`)
- **Qué**: `app/Modules/ApiKeys` (`POST/GET/GET{id}/DELETE /api-keys`), protegido por
  `AuthMiddleware + TenantMiddleware + ScopeMiddleware:apikeys.manage`. `ApiKeyRepository`
  nunca expone `hash`. `ApiKeyManager` deja de ser `final` (para mocking en tests).
- **Por qué**: que un tenant gestione sus propias keys desde la app; emisión antes solo
  programática.
- **Decisión de diseño clave**: gating por scope `apikeys.manage` → usuarios de la app
  (scope `'*'`) gestionan transparentemente, pero una API key solo administra otras si se
  le concede el scope explícito → **previene escalada de privilegios**.
- **Tradeoff**: `ApiKeyManager` no-`final` (se aceptó por testeabilidad).

### D12 — Fase 2: `WebhookVerifier` (HMAC + timestamp + anti-replay)
- **Qué**: `WebhookVerifierInterface` + `App\Support\Webhook\WebhookVerifier`. Esquema
  propio sin dependencias: cabecera `X-Signature: t=<ts>,v1=<hmac>`, firma
  `HMAC-SHA256("<ts>.<rawBody>", secret)`, ventana de tiempo, comparación constante
  (`hash_equals`) y **anti-replay por nonce de la firma vía `CacheInterface`** (TTL =
  ventana). `sign()` para webhooks salientes. Binding singleton en `bootstrap/app.php`.
  Se añadió `Request::rawBody()` (el cuerpo crudo se captura una vez en el constructor y
  lo reusa `parseJson()`).
- **Por qué**: cierra "endurecer webhooks" del review y es prerequisito de los webhooks de
  pago (Fase 6). Liviano y transversal → va en el base.
- **Decisión de diseño**: esquema genérico propio de Modux; los adaptadores de pasarela
  (Stripe/MP) podrán reusar `sign()`/`verify()` o aportar su parsing y delegar el HMAC.
  Sin middleware dedicado en esta fase (el secret depende de cada integración → se usa en
  el controller del webhook).
- **Tradeoff**: leer `php://input` siempre en el constructor de `Request` (antes solo si
  `Content-Type: application/json`); irrelevante para GET/multipart, necesario para
  verificar firmas. Validación: 7 tests unitarios con `ArrayCache`/`Request` reales +
  sanity de wiring del container (no requiere DB).

### D13 — Fase 3: motor de entitlements (lectura + gating)
- **Qué**: migración `0008_create_tenant_entitlements_table` (con `period_start/period_end/
  source/expires_at`, `UNIQUE(tenant_id, feature)`); value objects puros `Entitlement` y
  `EntitlementSet` (`allows/limit/remaining/get`); `EntitlementResolverInterface` +
  `DbEntitlementResolver` (solo lectura); `EntitlementMiddleware:<feature>` →
  `PaymentRequiredException` (**402**); binding en `bootstrap/app.php`.
- **Por qué**: es el corazón del gating SaaS y el contrato público clave del base. Vive en
  el chasis porque es autorización (hermano de RBAC), no comercial.
- **Decisiones de diseño**:
  - `EntitlementSet` es un **value object puro sin I/O**: `remaining($feature, $used)`
    recibe `$used` desde afuera (no toca DB/cache). El conteo de uso (Fase 4) lo hará
    `QuotaMiddleware` con `UsageRecorder` + el `periodStart` que expone `get()`. *(Refina
    el ADR, que insinuaba un `usage` dentro del set: se prefirió pureza/testeabilidad.)*
  - `DbEntitlementResolver` **sin cache** por ahora (correctitud sobre optimización; la
    cache exige invalidación al escribir → se evalúa en una fase posterior).
  - **Solo lectura** en el base: la escritura de `tenant_entitlements` es de billing
    (Fase 5) o manual (`source='manual'`). Mantiene a los módulos de producto
    desacoplados de billing.
  - **402** para feature ausente/deshabilitada (vs 403 de scope/permiso).
- **Tradeoff**: el contrato (`EntitlementResolverInterface`, `EntitlementSet`) es API
  pública → SemVer estricto. Validación: 15 tests unitarios + e2e contra MySQL real
  (resolver mapea flag/quota/seat/períodos/`enabled`; middleware decide 200 vs 402).

### D14 — Fase 4: metering (uso + cuotas)
- **Qué**: migración `0009_create_usage_events_table`; `UsageRecorderInterface` +
  `DbUsageRecorder` (`record()` con idempotencia vía `INSERT IGNORE` sobre
  `idempotency_key UNIQUE`; `total()` = `SUM(quantity)` desde una fecha);
  `QuotaMiddleware:<feature>`; `QuotaExceededException` (**429** con `Retry-After`);
  el `Handler` aplica el header `Retry-After`; comando CLI `entitlements:roll-periods`.
- **Por qué**: cierra las cuotas del SaaS y el "metering de uso (bots, reportes, API
  calls)" del review.
- **Decisiones de diseño**:
  - **El registro de uso es explícito** (lo hace el código de negocio vía
    `UsageRecorder::record()`), no el middleware: el costo por request varía (1 api.call vs
    N tokens). `QuotaMiddleware` solo **chequea**.
  - `QuotaMiddleware` cuenta `usage_events` desde `entitlement->periodStart` (o inicio de
    mes calendario si no hay billing) y usa el `remaining()` puro de la Fase 3.
  - **402 vs 429**: sin la feature → 402; con la feature pero cuota agotada → 429 +
    `Retry-After` (segundos hasta `periodEnd`).
  - `entitlements:roll-periods` avanza ciclos vencidos **por su propia duración** (en
    segundos) hasta cubrir el presente; idempotente. Es una red de seguridad —billing
    mantiene los períodos vía webhooks—; usar segundos (no meses calendario) es una
    aproximación aceptable para el fallback.
  - Mover la ventana **resetea la cuota sin borrar `usage_events`** (auditoría/rating).
- **Tradeoff**: leer `php://input`/contar uso agrega un `SELECT SUM` por request con
  cuota (aceptable; indexado por `(tenant_id, metric, occurred_at)`). Validación: 9 tests
  unitarios + e2e contra MySQL real (record/total/idempotencia, 200→429 + Retry-After,
  roll-periods avanzó `[ene–feb]` → `[jun–jul]`).

### D15 — Fase 5: `cynchro/modux-billing` (paquete opcional separado)
- **Qué**: primer módulo opcional de la capa comercial, como **paquete independiente** en
  `../modulos/billing` (repo git propio, namespace `Cynchro\Billing`, mismo molde que
  `modux-ia`). Incluye `Schema` (plans/plan_entitlements/subscriptions), models,
  `PaymentGatewayInterface` (driver de pasarela), `EntitlementWriterInterface`,
  `BillingManager` (`subscribe`/`handleEvent`) y `TenantEntitlementWriter`.
- **Por qué**: separar lo comercial/pesado del chasis; el usuario eligió paquete separado
  (no prototipo dentro del repo). El framework base **no** lo requiere.
- **Decisiones de diseño**:
  - **La costura es un único punto**: `TenantEntitlementWriter` escribe
    `tenant_entitlements` (upsert por feature, con `source='billing:<gw>'` y período).
    Billing escribe; el base solo lee. `cancel` hace `clear` por `source`.
  - **Agnóstico de pasarela** (driver `PaymentGatewayInterface`) y **de framework**
    (recibe un `PDO`). Los adaptadores Stripe/MP traducen sus webhooks a un `GatewayEvent`
    normalizado que `BillingManager::handleEvent()` procesa.
  - `EntitlementWriterInterface` desacopla la escritura (testeable; otra app podría
    implementar su propio writer), aunque el default conoce el esquema del base.
  - El módulo de integración HTTP (`app/Modules/Billing`) **no va en el base** (igual que
    `app/Modules/IA`); pertenece a una instancia que instale billing.
- **Tradeoff**: dos proyectos a mantener/versionar. El paquete conoce el esquema de
  `tenant_entitlements` del base (acoplamiento aceptado: esa tabla es el contrato público).
  Validación: 9 tests del paquete + e2e contra MySQL real junto al base (subscribe escribe
  → `DbEntitlementResolver` lee allows/limit → cancel limpia).

### D16 — Fase 6: adaptadores de pasarela (Stripe + Mercado Pago)
- **Qué**: dos paquetes separados, `cynchro/modux-billing-stripe` y
  `cynchro/modux-billing-mercadopago`, que implementan `PaymentGatewayInterface`
  (`createCheckout`/`parseWebhook`/`cancel`) + un `verifyWebhook` propio de cada pasarela.
- **Por qué**: cierra "pasarela de pago (Stripe + Mercado Pago para AR)" del review, como
  add-ons opcionales (no en el base, no en el core de billing).
- **Decisiones de diseño**:
  - **HTTP liviano (curl), sin los SDKs oficiales** — consistente con el patrón de
    `modux-ia`. El HTTP se abstrae tras un `HttpClientInterface` por paquete (testeable).
  - **Stripe**: webhook "gordo" (trae el objeto) → `parseWebhook` mapea sin HTTP. Firma
    `Stripe-Signature: t=,v1=` = mismo esquema que el `WebhookVerifier` del base.
  - **Mercado Pago**: webhook "delgado" (solo id) → `parseWebhook` hace `GET /preapproval/
    {id}` para el status y lo mapea. Firma sobre el manifest `id;request-id;ts` (esquema
    propio de MP). Suscripciones vía `preapproval`.
  - **Dependencia cross-repo**: cada adaptador declara `cynchro/modux-billing` vía
    repository **VCS (GitHub)** (no `path`), para que su CI la resuelva sin Packagist
    (probado: composer la clona del repo público).
- **Tradeoff**: sin los SDKs oficiales se asumen el formato de payloads/firmas de cada
  pasarela (puede requerir ajustes ante cambios de la API). Validación: 9 tests por paquete
  (`parseWebhook`, `verifyWebhook`, requests con HTTP mock); el ida-y-vuelta real con la
  API necesita credenciales del usuario (no se pudo e2e en esta sesión).

### D17 — Integración HTTP: módulo `app/Modules/Billing` (opcional)
- **Qué**: módulo de integración en el repo del framework que conecta los adaptadores +
  `BillingManager` con endpoints HTTP (`POST /billing/checkout`, `POST /billing/webhook/
  {gateway}`). `GatewayFactory`, `ServiceProvider` (guard `class_exists`), `BillingController`
  (webhook verifica firma por pasarela vía `instanceof` + `verifyWebhook`), `CheckoutRequest`,
  `config/billing.php`.
- **Por qué**: el usuario pidió la integración HTTP de ejemplo/uso real.
- **Decisión (elegida por el usuario): opcional vía `suggest` + `require-dev`**:
  - billing+adaptadores en **`require-dev`** (disponibles para dev/CI/análisis/tests) +
    **`suggest`** (señal a producción) + **`repositories` VCS** (GitHub) para resolverlos.
  - El módulo se **auto-activa con guard `class_exists(BillingManager)`** en `routes.php` y
    `ServiceProvider`; en producción `--no-dev` el chasis no trae billing y el módulo queda
    inactivo. Mantiene el chasis liviano (mismo principio que IA), pero con el módulo
    versionado en el repo (a diferencia de IA, que se sacó del todo).
  - El webhook es **público** (las pasarelas no mandan JWT); la seguridad es la **firma**.
- **Tradeoff**: el CI/quality-gate del chasis ahora instala billing+adaptadores (dev) desde
  GitHub (acopla el CI a esos repos públicos). `verifyWebhook` no está en la interfaz común
  (firmas distintas por pasarela) → el controller usa `instanceof`. Validación: 6 tests
  (GatewayFactory + BillingController firma ok/mal) + e2e contra MySQL real (webhook Stripe
  firmado escribió `tenant_entitlements`; firma inválida 401).

### D18 — Anti-replay de webhooks: fail-closed cuando el cache no es operativo
- **Qué**: el `WebhookVerifier` usaba `CacheInterface` para el anti-replay, pero `ApcuCache`
  hace **no-op silencioso** si APCu no está habilitado (`apcu_enabled() === false`, p. ej.
  `apc.enable_cli=0`, extensión ausente, o multi-instancia donde APCu no se comparte). Con
  un cache inerte, `has()` siempre devuelve `false` → toda firma válida pasaba el chequeo
  de replay **sin protección y sin error**: degradación silenciosa a inseguro.
- **Fix (mínimo, sin nueva dependencia)**:
  - Se añadió `available(): bool` a `CacheInterface` (2 implementadores: `ArrayCache`→`true`,
    `ApcuCache`→`function_exists('apcu_enabled') && apcu_enabled()`).
  - `WebhookVerifier::verify()` ahora **falla cerrado**: si `!cache->available()` retorna
    `false` antes del anti-replay (mejor rechazar que aceptar reenvíos).
  - `bootstrap/app.php` loguea un **warning** al resolver el cache si APCu no está operativo,
    para que el operador lo vea (ruidoso) en vez de solo el rechazo del webhook.
- **Por qué**: era el único hallazgo de la auditoría que es *seguridad*, no prolijidad. "Loud
  failure > silent insecurity".
- **Tradeoff**: en un deploy sin APCu (o sin store compartido), los webhooks se rechazan
  hasta configurar un cache real → comportamiento visible y correcto. En el contenedor APCu
  está habilitado, así que el happy-path (webhook firmado → 200) **no cambia**.
- **Validación**: test nuevo `test_fails_closed_when_replay_store_unavailable` (cache inerte
  anónimo + firma válida → `false`). Batería completa verde: 221 tests / 326 assertions,
  PHPStan 0, PHPCS limpio.
- **Pendiente documental**: el ADR 0001 debería anotar que el anti-replay requiere un store
  **compartido** (Redis/DB) en multi-instancia; APCu es por-proceso.

### D19 — DRY del `Container`: `makeWith` + `autowire` → `build()` privado
- **Qué**: `makeWith()` y `autowire()` eran ~idénticos (autowiring por reflexión); el primero
  solo añadía inyección posicional de escalares. Se unificaron en un único `build(string
  $class, array $scalars = [])`; `make()`/`makeWith()`/`get()` delegan en él. −35 líneas, un
  solo punto de resolución.
- **Por qué**: era duplicación con riesgo de divergencia futura; el #2 de la auditoría
  (limpieza que *baja* complejidad, no la sube).
- **Tradeoff/nota**: se cambió `isset($scalars[$i])` → `array_key_exists()` (acepta escalares
  `null` explícitos; los callers actuales solo pasan strings, sin cambio observable). Sin
  cambios de API pública. Batería completa verde (221 tests / PHPStan 0 / PHPCS).

### D20 — Módulo `Cliente`: de stub roto a ejemplo de scaffolding funcional
- **Qué**: `Cliente` es el ejemplo de "CRUD multi-tenant" del framework, pero estaba a
  medias: tabla `clientes` con solo `id`+`tenant_id`, validación vacía y `create()/update()`
  lanzando `RuntimeException('not implemented yet')` (500 en producción para un módulo vivo
  y ruteado).
- **Decisión del usuario (#3 de la auditoría, opción "ejemplo completo")**: convertirlo en
  un CRUD que corre end-to-end, no quitarlo del base. Cambios:
  - Columna demo `nombre VARCHAR(255) NOT NULL` (a reemplazar por columnas de dominio).
    *(Nota: inicialmente se editó la migración `0004` en sitio; corregido en D23 vía una
    migración nueva `0010` para respetar la inmutabilidad de migraciones publicadas.)*
  - `CreateClienteRequest`/`UpdateClienteRequest`: regla `'nombre' =>
    'required|string|min:2|max:255'` (PUT = reemplazo).
  - `ClienteRepository::create()` → `INSERT nombre, tenant_id` + `findById(lastInsertId)`;
    `update()` → `UPDATE nombre WHERE id AND tenant_id` (return `rowCount() > 0`).
- **Por qué**: un framework-plantilla debe traer un ejemplo *que funcione* (request →
  validación → service → repo → DB), que el adoptante renombra/extiende, en vez de un módulo
  que tira 500. Cierra la deuda preexistente de scaffolding documentada en CURRENT_TASK.
- **Validación**: batería completa verde (221 tests / PHPStan 0 / PHPCS) + **e2e contra
  MySQL 8 real** (contenedor descartable aislado, sin tocar el stack del usuario): migración
  crea la tabla, `create`→`findById` OK, `update` con tenant correcto afecta 1 fila, con
  tenant ajeno 0 filas (aislamiento row-level verificado). `ClienteServiceTest` mockea el
  repo → intacto.
### D23 — Inmutabilidad de migraciones: revertir `0004`, añadir `0010` (anti-replay + caveat ADR)
- **Qué**: D20 había editado la migración **ya publicada** `0004_add_tenant_to_clientes`
  para meter la columna `nombre`, lo que rompe la inmutabilidad de migraciones (una DB ya
  migrada no la re-ejecuta → necesitaría `ALTER` manual). Corregido:
  - `0004` revertida a su forma original publicada.
  - Nueva migración `0010_add_nombre_to_clientes` (`ALTER TABLE clientes ADD/DROP COLUMN
    nombre`, idempotente vía `information_schema`, `down()` reversible). El runner trackea
    migraciones por filename en la tabla `migrations` → corre una sola vez en nueva o
    existente; ambas convergen al mismo esquema.
- **Por qué**: profesionalismo/solidez — las migraciones publicadas no se editan, se
  suceden. Cierra el pendiente "ALTER manual" de D20.
- **Validación**: e2e contra MySQL 8 real (descartable): 0004 sin `nombre` → 0010 lo agrega
  → guard idempotente → CRUD + aislamiento por tenant → `down()` revierte. Batería verde
  (221 tests / PHPStan 0 / PHPCS).
- **Doc**: ADR 0001 §1.3 ampliado con el caveat operativo de D18 — el anti-replay exige un
  **store compartido** (APCu es por-proceso); en multi-instancia, vincular `CacheInterface`
  a Redis/DB. El verifier ya falla cerrado si el cache no opera.

### D21 — Vaciar el baseline de PHPStan (84 → 0)
- **Qué**: el `phpstan-baseline.neon` tenía 84 entradas que escondían cualquier regresión
  nueva. Composición real: ~77 `missingType.iterableValue` (faltan generics de array), 7
  `variable.undefined` del `$router` global en `routes.php`, 3 `new static()` unsafe en
  `Response`, 1 comparación estricta muerta en `ApiKeyRepository`.
- **Decisión del usuario (#4 de la auditoría)**: vaciar el baseline. Para los 77 generics,
  **desactivar la regla** `missingType.iterableValue` a nivel proyecto (1 línea en
  `phpstan.neon`) en vez de tipar 77 sitios — coherente con F2 (PDO crudo, filas como arrays
  de `mixed`; el generic no aporta seguridad real). Los genuinos se arreglaron en código:
  - `$router`: `/** @var \App\Support\Router $router */` en los 7 `routes.php` (elimina el
    falso positivo de raíz y da autocompletado al IDE).
  - `Response`: marcada `final` → `new static()` deja de ser unsafe (nada la extiende).
  - `ApiKeyRepository::present()`: `isset(...) && $row['scopes'] !== null` → `isset(...)`
    (el `!== null` era siempre true; `isset` ya excluye null). Cleanup real.
  - Se borró `phpstan-baseline.neon` y su `includes` en `phpstan.neon`.
- **Por qué**: un baseline gordo oculta regresiones; ahora cualquier aviso nuevo de nivel 6
  (variables sin definir, código muerto, errores de tipo) salta de inmediato.
- **Tradeoff**: código nuevo con arrays sin tipar tampoco se marca (se asume como stance del
  framework). El resto del nivel 6 sigue activo.
- **Validación**: `composer analyse` 0 errores **sin baseline**; 221 tests / PHPStan 0 /
  PHPCS limpio. Sin referencias colgadas al baseline en CI/hooks/README.

### D22 — Partir el README (48 KB → 8 KB) en `docs/`
- **Qué**: el `README.md` era un manual de 1450 líneas / 48 KB, incoherente con un framework
  que se vende como liviano. Se dejó como **landing/quickstart** (intro, at-a-glance,
  requisitos, instalación, quick start, estructura, índice de docs, "Why not Laravel?",
  licencia) y se movió el manual a `docs/` en 7 archivos temáticos contiguos:
  `cli.md`, `modules.md`, `http.md`, `auth-and-tenancy.md`, `infrastructure.md`,
  `platform.md`, `optional-modules.md`. Cada uno con título + back-link al README.
- **Cómo**: extracción por **rangos de línea exactos** desde un backup (sin retipear), para
  mover contenido byte-fiel. Verificado: 1266 líneas movidas == rango original 163–1428
  (cero pérdida); sin enlaces de ancla internos rotos (no había ninguno).
- **Por qué**: la landing ahora se lee en segundos y el detalle queda navegable por tema.
  Puro cosmético/DX, sin tocar código (#5 de la auditoría, el de menor prioridad).
- **Resultado**: README 1450→197 líneas (48578→8246 bytes). Sin impacto en CI/tests/lint
  (markdown). Cierra la auditoría liviana (#1–#5).

### D24 — Red de Feature/integration tests contra MySQL real + CI
- **Qué**: el framework tenía `FeatureTestCase` pero **0 Feature tests** y el CI no levantaba
  DB → ninguna ruta de SQL/migraciones/pipeline/auth/gating estaba cubierta automáticamente
  (toda la validación era e2e manual con Docker). Se construyó la red completa:
  - **Harness** (`FeatureTestCase`): PDO compartido entre test y app (mismo singleton del
    container); esquema creado una vez por proceso (drop-all + todas las migraciones);
    **transacción por test con rollback** en tearDown (aislamiento sin re-migrar); bindings
    de servicios (DB/Cache=ArrayCache/Entitlement/Usage/Webhook/EventDispatcher/Router);
    `request()` despacha por el Router real (corren los middlewares de ruta) y mapea
    `AppException`→status/headers como el Handler. Helpers: `actingAsUser` (tenant+usuario+
    JWT guardado en `usuarios.token`), `seedTenant`, `grantFlag/grantQuota/recordUsage`,
    `registerRoute` (rutas ad-hoc para gating).
  - **Tests** (19): `AuthFlowTest` (login ok/credenciales inválidas/token revocado/sin token),
    `ClienteCrudTest` (CRUD completo + validación 422 + 401), `TenantIsolationTest` (A no ve
    ni alcanza datos de B), `GatingTest` (entitlement 402/200, quota 200 vs 429+Retry-After,
    quota sin entitlement 402), `ApiKeysTest` (emisión/listado/revocación + key sin scope
    `apikeys.manage` → 403, prevención de escalada). Fixture `PingController`.
  - **CI**: service `mysql:8.0` (db `monolito_test`, root sin clave, health-check) en el job
    `quality`; `composer test` ahora corre Unit+Feature contra DB real.
- **Skip elegante**: si MySQL no está disponible (sin `pdo_mysql` o sin DB — p. ej. el
  pre-push local del host), los Feature tests **se saltan** en vez de fallar (`markTestSkipped`
  en setUp ante `PDOException`). El CI es el punto de enforcement de integración.
- **Por qué**: es el mayor diferencial de "estable y sólido" — convierte la validación manual
  en red de regresión automática. Pedido explícito del usuario.
- **Validación**: host sin DB → 221 pass + 15 skip (verde, pre-push ok); con MySQL real
  (php:8.3-cli + pdo_mysql, red Docker aislada) → **236 tests / 361 assertions** verdes
  (221 unit + 15 feature). PHPStan 0, PHPCS limpio (158 archivos, incluye los tests nuevos).

### D25 — CHANGELOG + versionado SemVer (release pendiente)
- **Qué**: se creó `CHANGELOG.md` (Keep a Changelog) con la sección `[Unreleased]` que
  documenta toda la tanda (D18–D24). El proyecto ya estaba en `v1.2.0` (tags de git), no en
  0.x.
- **Decisión SemVer (a confirmar el usuario antes de taggear)**: agregar `available()` a
  `CacheInterface` es una adición a una interfaz pública → **breaking para implementadores**
  propios → la release que lo incluya debería ser **major `v2.0.0`**. Marcado como BREAKING
  en el CHANGELOG.
- **Resuelto**: el usuario eligió **v2.0.0**. El CHANGELOG se cerró en la rama
  (`[Unreleased]` → `[2.0.0] - 2026-06-09`). **Falta el tag** (`git tag -a v2.0.0`), que se
  crea sobre `main` **tras el merge del PR** — no sobre la rama.

### D26 — Hardening post-v2.0.0 (benchmark + auditoría): Logger, JWT, composer audit
- **Contexto**: tras la v2.0.0 se corrió un benchmark (performance + seguridad) comparando
  Modux con la familia Slim/Mezzio. Perf (imagen de prod real, Apache mod_php + MySQL):
  `/` ~3.523 req/s (overhead ~0,28 ms/req), `/health` ~1.914 req/s, 0 fallos → tier
  micro-framework rápido. La auditoría arrojó 3 accionables, todos resueltos:
- **Bug real `Logger` bajo SAPI web (descubierto en el benchmark)**: el driver `stderr` y el
  fallback de `writeFile` usaban la constante `STDERR`, que **solo existe en CLI** →
  `LOG_CHANNEL=stderr` bajo Apache/php-fpm tumbaba cada request con 500. Fix: `php://stderr`
  (portable) vía `writeStderr()`/`stderrStream()` (protegido, sobrescribible en tests).
  `LoggerTest` cubre stderr-portable, file y filtrado por nivel.
- **`firebase/php-jwt` 6.11.1 → 7.0.5**: cierra `CVE-2025-45769` (weak encryption, baja). API
  (`JWT::encode`/`decode`+`Key`) sin cambios; validado con roundtrip + las 19 Feature tests de
  auth contra DB real.
- **`composer audit` bloqueante** en el CI (job `quality`) y en el pre-push hook → cualquier
  CVE nuevo en deps frena build/push.
- **Versionado**: son patch (bugfix + seguridad, sin API breaking) → **v2.0.1** (CHANGELOG
  `[Unreleased]`). En rama `fix/seguridad-post-v2`.
- **Postura de seguridad (OWASP API Top 10)**: fuerte en BOLA (aislamiento por tenant), auth
  (JWT+revocación+bcrypt12), authz multidimensional (RBAC+scopes+entitlements), webhooks
  (HMAC+anti-replay+fail-closed), headers (CSP/HSTS/etc.). Gaps: sin cifrado de campos, sin
  OAuth2 server (planeado `modux-oauth`).

---

## Convención de trabajo adoptada (meta-decisión)

Cada fase se entrega con: implementación siguiendo los patrones existentes → tests
unitarios → batería completa (`composer test/analyse/lint` en verde) → **validación e2e
con Docker + MySQL real** → actualización de README y del ADR → commit descriptivo. El
pre-push hook (D7) garantiza que nada roto llegue a `origin`.
