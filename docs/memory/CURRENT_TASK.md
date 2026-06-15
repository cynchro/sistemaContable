# CURRENT_TASK.md

## Última sesión (2026-06-09) — hardening anti-replay de webhooks

Auditoría liviana del framework a pedido del usuario ("qué mejorar sin complejizar").
Veredicto: la base está sólida y minimalista; se acordó **no** agregar arquitectura.
Lista de remates identificada (ver abajo). Se ejecutó el **#1 (seguridad)**: el
anti-replay degradaba en silencio a inseguro si el cache (APCu) no era operativo. Fix en
`D18` (DECISIONS.md): `CacheInterface::available()`, `WebhookVerifier` falla cerrado,
warning al bootear. 221 tests verdes / PHPStan 0 / PHPCS limpio. **Sin commitear aún.**

También se ejecutó **#2** (DRY de `Container` → `build()`, `D19`) y **#3** (módulo `Cliente`:
de stub roto a ejemplo de scaffolding funcional con columna demo `nombre`, `D20`; validado
e2e contra MySQL real).

**#4** (vaciar baseline PHPStan 84→0: regla `missingType.iterableValue` off + `$router`
con `@var` + `Response` final + comparación muerta de `ApiKeyRepository`; `D21`) y **#5**
(partir README 48 KB→8 KB en `docs/`, `D22`).

**Auditoría liviana COMPLETA (#1–#5)** + pendientes cerrados (**D23**): inmutabilidad de
migraciones (revert `0004` + nueva `0010` para la columna `nombre`, e2e verde) y caveat
operativo del anti-replay multi-instancia en el ADR 0001 §1.3.

**Red de integración (D24)** — el mayor diferencial de solidez: `FeatureTestCase` completo
(DB real + rollback por test), 15 Feature tests (auth/CRUD/aislamiento/gating), MySQL service
en CI, skip elegante sin DB. host: 221 pass + 15 skip; con DB: 236 pass / 361 assertions.

Todo en rama `mejoras/auditoria-liviana` → **mergeado a main y taggeado `v2.0.0`**.

**Post-v2.0.0 — benchmark + hardening (D26, rama `fix/seguridad-post-v2`, apunta a v2.0.1):**
benchmark de perf (~3.5k req/s en `/`, tier micro-framework rápido) + auditoría de seguridad.
3 fixes: bug del `Logger` bajo SAPI web (`STDERR`→`php://stderr`, + `LoggerTest`), bump
`firebase/php-jwt` 6→7.0.5 (CVE-2025-45769), `composer audit` bloqueante en CI + pre-push.
243 tests host (19 skip) + 19 Feature contra DB real verdes. PHPStan 0, PHPCS limpio, audit
limpio. Falta commitear/pushear esta rama.

Notas previas (ya superadas):
#4 baseline PHPStan de 90 → un `ignoreErrors` para el `$router` global, #5 partir README
(48 KB). Explícitamente NO: ORM, broker de colas, routing por atributos, DI compilado.

---

## En qué estoy trabajando ahora

**Expansión de la capa SaaS del framework**, guiada por
`docs/adr/0001-saas-identity-entitlements-billing.md`. Origen: una observación externa
de que "Modux trae el chasis pero falta la capa comercial del SaaS y la apertura a
terceros".

División acordada:
- **API para terceros** (API keys / scopes / webhooks endurecidos) → en el **framework base**.
- **Billing / entitlements / packs** → como **módulos opcionales instalables** estilo
  `modux-ia` (`cynchro/modux-billing`, `-stripe`, `-mercadopago`), salvo el **motor de
  entitlements**, que va en el base (es autorización, no comercial).

### Estado por fase (del ADR 0001)
- ✅ **Fase 1** — auth multi-esquema (`GuardInterface`, `Principal`, `JwtGuard`,
  `ApiKeyGuard`, `ApiKeyManager`), `ScopeMiddleware`, migración `0007_create_api_keys_table`.
  Commit `7bae294`.
- ✅ **Fase 1.b** — módulo `ApiKeys` con CRUD (`/api-keys`), scope `apikeys.manage`.
  Commit `69ad4aa`.
- ✅ **Fase 2** — `App\Support\Webhook\WebhookVerifier` (`WebhookVerifierInterface`):
  HMAC-SHA256 sobre `<ts>.<rawBody>`, ventana de timestamp y anti-replay vía
  `CacheInterface`. Añadido `Request::rawBody()`. Binding en `bootstrap/app.php`.
  Cierra "endurecer webhooks" del review.
- ✅ **Fase 3** — motor de entitlements (LECTURA + gating): migración
  `0008_create_tenant_entitlements_table`; `Entitlement` + `EntitlementSet` (value objects
  puros); `EntitlementResolverInterface` + `DbEntitlementResolver`; `EntitlementMiddleware:
  <feature>` (402); `PaymentRequiredException`. La **escritura** de `tenant_entitlements`
  llegará con billing (Fase 5) o se hace a mano (`source='manual'`).
- ✅ **Fase 4** — metering: migración `0009_create_usage_events_table`;
  `UsageRecorderInterface` + `DbUsageRecorder` (record con idempotencia vía INSERT IGNORE,
  total = SUM); `QuotaMiddleware:<feature>` (cuenta `usage_events` desde `periodStart`,
  usa `remaining()`; 402 si no tiene feature, 429 + `Retry-After` si agotada);
  `QuotaExceededException`; `Handler` aplica el header `Retry-After`; comando CLI
  `entitlements:roll-periods` (red de seguridad para ciclos vencidos).
- ✅ **Fase 5** — primer **módulo opcional**: `cynchro/modux-billing` (core agnóstico de
  pasarela), **paquete separado en `../modulos/billing`** (repo propio, commit `f6fb4bb`,
  namespace `Cynchro\Billing`). `Schema` (plans/plan_entitlements/subscriptions); models
  Plan/PlanEntitlement/Subscription; `PaymentGatewayInterface` + `EntitlementWriterInterface`;
  `BillingManager` (`subscribe()`, `handleEvent()` renew/cancel/past_due);
  `TenantEntitlementWriter` (única costura billing→base: escribe `tenant_entitlements`).
  9 tests + e2e contra MySQL real junto al base (subscribe escribe → base lee → cancel
  limpia). El framework base **no** lo requiere (opcional, como `modux-ia`).
- ✅ **Fase 6** — adaptadores de pasarela como **paquetes separados** (repos propios):
  - `cynchro/modux-billing-stripe` (`StripeGateway`, namespace `Cynchro\Billing\Stripe`):
    Checkout Session, `parseWebhook` (customer.subscription.* / invoice.*), `cancel`,
    `verifyWebhook` (Stripe-Signature `t=,v1=`). HTTP liviano (curl, sin SDK). Pusheado.
  - `cynchro/modux-billing-mercadopago` (`MercadoPagoGateway`): preapproval, `parseWebhook`
    (notificación delgada → GET /preapproval/{id} → status), `cancel` (PUT cancelled),
    `verifyWebhook` (manifest `id;request-id;ts`). Commit local `9819ac7`, **falta remoto**.
  - Ambos: dependencia `modux-billing` vía repository VCS (GitHub), 9 tests c/u, CI+release.
  - Validación: `parseWebhook`/`verifyWebhook`/construcción de requests con HTTP mock.
    El ida-y-vuelta real (createCheckout/cancel/GET) **requiere credenciales del usuario**.
- ⬜ **Fase 7 (opcional)** — `cynchro/modux-oauth` (authorization server). El review NO lo
  pedía explícitamente como bloqueante; queda como mejora futura.

### Integración HTTP — ✅ implementada (`app/Modules/Billing`)
Módulo en el repo del framework, **opcional**: billing+adaptadores en `require-dev` +
`suggest` + `repositories` VCS; el módulo se auto-activa con guard `class_exists` (en
producción `--no-dev` el chasis no trae billing). Componentes: `GatewayFactory` (arma el
adaptador desde `config/billing.php`), `ServiceProvider` (registra BillingManager/repos/
factory, con guard), `BillingController` (`checkout` + `webhook` con verificación de firma
por pasarela), `CheckoutRequest`, `routes.php` (guard). Endpoints: `POST /billing/checkout`
(auth+tenant), `POST /billing/webhook/{gateway}` (público, verificado por firma).
Validado e2e contra MySQL real: webhook Stripe firmado → 200 → escribió `tenant_entitlements`
(source=billing:stripe); firma inválida → 401.

**Seeder de planes** — ✅ `seeders/BillingPlansSeeder.php` (idempotente): corre `Schema::up` +
crea/actualiza planes (starter/pro de ejemplo, editables) con sus entitlements. Validado e2e
(created → updated sin duplicar). No lo cubren phpcs/phpstan (seeders/ fuera de scope, como
AdminSeeder). **Pendiente menor restante**: e2e de `checkout` (requiere API real de la pasarela).

### Nota de integración (pendiente, cuando se quiera una app que use billing)
El ADR mencionaba un `app/Modules/Billing` que consume el SDK. Siguiendo el patrón de
`modux-ia` (que se sacó del base), ese módulo de integración HTTP (endpoints de checkout
y de webhook) **no va en el framework base** — pertenece a una instancia que instale
billing. El paquete core ya está listo para consumirse.

## Problemas actuales

- **Ninguno bloqueante.** La base está 100% verde: **214 tests / 319 assertions**,
  **PHPStan 2.x 0 errores**, **PHPCS limpio**. Validado e2e contra MySQL real (Docker):
  migraciones, login JWT, API keys (auth + CRUD), entitlements (gating 200 vs 402),
  metering (record/total/idempotencia), quota (200 vs 429 + Retry-After) y el comando
  `entitlements:roll-periods`. El `WebhookVerifier` (sin DB) se validó con tests + sanity
  de wiring del container.

## Qué ya intenté / cómo se llegó acá (esta sesión, 2026-06-07)

1. Detecté y eliminé el clon embebido `backend/` (duplicado desactualizado, trackeado en
   el repo). Rescaté el módulo IA antes de borrar.
2. Confirmé (Packagist) que `cynchro/modux-ia` es library independiente → lo dejé como
   add-on **opcional** (sacado del `require`, movido a `suggest`; `app/Modules/IA` fuera
   del base).
3. Actualicé PHPStan 1.12 → 2.x; corregí el único hallazgo real (`Response::$rawBody`
   muerto, eliminado); regeneré baseline.
4. Monté el quality gate (`.githooks/pre-push` + `.github/workflows/ci.yml`).
5. Escribí el ADR 0001 y cerré sus decisiones D1/D2/D3 con el usuario.
6. Implementé Fase 1 y Fase 1.b (cada una con tests unitarios + validación e2e Docker).

Metodología establecida y esperada para cada fase: implementar siguiendo los patrones
existentes → tests unitarios → **batería completa** (`composer test/analyse/lint`) →
**validación e2e con Docker + MySQL real** → actualizar README + ADR → commit con mensaje
detallado. El pre-push hook bloquea push si algo falla.

## Bloqueos / pendientes

- **Acción del usuario — rotar la Groq API key**: estuvo commiteada en `backend/.env`
  (y una vez en este archivo). Se purgó del historial con `git filter-repo` y **nunca
  llegó a GitHub** → conviene rotarla en el panel de Groq. El valor real no se anota en git.
- **Todo pusheado**: `origin/main` al día (Fases 1–4 + ADR + memoria). 0 commits pendientes.
- **Deuda preexistente RESUELTA (2026-06-09, D20)**: `Cliente::create()`/`update()` ya no
  son stubs que tiran 500 — son un ejemplo de scaffolding funcional (columna demo `nombre`,
  CRUD e2e verde contra MySQL real).

## Decisiones abiertas pendientes para fases futuras (del ADR)

- Naming de features namespaced (`ia.rag`, `bots.outbound`) — **cerrada: sí**.
- Quota anclada al `current_period_end` con `period_start/period_end` denormalizados en
  `tenant_entitlements` — **cerrada: sí**.
- `scope` + `entitlement` ambos checks, ortogonales — **cerrada: sí**.
- Status codes del pipeline: 402 (entitlement faltante que se compra), 403 (scope),
  429 + `Retry-After` (cuota) — definidos en el ADR, a implementar en Fases 3-4.
