# ADR 0001 — Capa SaaS: identidad de terceros, entitlements y billing

- **Estado:** Aceptado (diseño; sin implementar)
- **Fecha:** 2026-06-07
- **Contexto del repo:** `cynchro/modux` — framework PHP, modular monolith, multi-tenant.

## Contexto

Una revisión externa observó que Modux "trae el chasis" pero le falta la capa
comercial del SaaS y la apertura a terceros. En concreto:

1. **API para terceros** — hoy el auth es JWT para la app propia. Para devs
   externos faltan API keys / OAuth2 con scopes y endurecer webhooks.
2. **Billing / entitlements / packs** — no existe (cero referencias a `plan`
   en el código). Falta: planes, entitlements (qué módulos / cuántos seats /
   qué add-ons tiene cada tenant), feature flags, metering de uso y pasarela
   de pago (Stripe + Mercado Pago para AR).

El framework ya provee, en el base: `AuthMiddleware` (JWT con revocación por
tabla), `PermissionMiddleware` (RBAC), `TenantMiddleware` (`tenant_id`), un
`EventDispatcher` y `CacheInterface`. El patrón de add-on opcional ya está
validado con `cynchro/modux-ia` (SDK en Packagist + `app/Modules/IA` que lo
consume).

## Decisión

La línea divisoria **no** es "auth → base / billing → opcional". Es:

- **Base (chasis de identidad y autorización):** todo lo transversal y liviano
  (sin SDKs externos): auth multi-esquema, API keys, hardening de webhooks, el
  **motor de entitlements/feature-flags** y el **registro** de metering.
- **Módulos opcionales (estilo `modux-ia`):** todo lo pesado, reemplazable o
  comercial: planes, suscripciones, pasarelas de pago, el **rating/cobro** del
  uso y, si se necesita como producto, un *authorization server* OAuth2.

**Insight central:** `entitlements ≠ billing`. Entitlements es *autorización*
(pertenece al chasis, junto a RBAC y tenant). Billing es *comercial* (módulos).
El base define el **contrato** de entitlements; billing lo **puebla**.

### Mapa de responsabilidades

| Pieza | Dónde | Por qué |
|---|---|---|
| Auth multi-esquema (guards) | base | transversal |
| API keys + scopes | base | liviano, server-to-server |
| Webhook hardening (HMAC/replay) | base | transversal, sin deps |
| Contrato + motor de entitlements | base | es autorización, hermano de RBAC |
| Metering: *registro* de uso | base | event sink liviano |
| Resource-server OAuth (validar token) | base | otro guard |
| Planes, suscripciones, pasarela | `cynchro/modux-billing` (+ `-stripe`, `-mercadopago`) | pesado, AR-específico |
| Metering: *rating/cobro* | `modux-billing` | comercial |
| OAuth *authorization server* | `cynchro/modux-oauth` (opcional) | proyecto en sí mismo |

---

## Parte 1 — Base (`cynchro/modux`)

### 1.1 Auth multi-esquema: guards + Principal

Se generaliza `AuthMiddleware` para iterar **guards** que devuelven un
`Principal` unificado (usuario *o* api-key), preservando `tenant_id` y
agregando `scopes`. El resto del chasis (`TenantMiddleware`,
`PermissionMiddleware`) no cambia: lee `tenant_id` del `Principal`.

```php
// app/Support/Contracts/GuardInterface.php
interface GuardInterface
{
    /** Principal si este guard reconoce la credencial; null si no aplica. */
    public function authenticate(Request $request): ?Principal;
}
```

```php
// app/Support/Auth/Principal.php
final class Principal
{
    public function __construct(
        public readonly string $type,      // 'user' | 'api_key'
        public readonly string $tenantId,
        public readonly ?int   $userId,    // null para api_key
        public readonly array  $scopes,    // ['clientes:read', 'ia:rag']
        public readonly ?int   $rol = null
    ) {}

    public function hasScope(string $scope): bool
    {
        return in_array('*', $this->scopes, true)
            || in_array($scope, $this->scopes, true);
    }
}
```

### 1.2 API keys

```sql
-- migrations/0007_create_api_keys_table.php
CREATE TABLE IF NOT EXISTS api_keys (
    id           CHAR(36)     PRIMARY KEY DEFAULT (UUID()),
    tenant_id    CHAR(36)     NOT NULL,
    name         VARCHAR(255) NOT NULL,
    prefix       VARCHAR(16)  NOT NULL,            -- visible: 'mk_live_a1b2c3'
    hash         CHAR(64)     NOT NULL,            -- sha256(secreto completo)
    scopes       JSON         NULL,               -- ["clientes:read","ia:rag"]
    last_used_at DATETIME     NULL,
    expires_at   DATETIME     NULL,
    revoked_at   DATETIME     NULL,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_prefix (prefix),
    KEY idx_tenant (tenant_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

- Se entrega `mk_live_<prefix>_<secreto>` **una sola vez**; en DB solo `prefix`
  (lookup) + `hash`.
- `ApiKeyMiddleware` (guard): lee `Authorization: Bearer mk_live_...` o
  `X-Api-Key`, busca por `prefix`, compara `hash_equals(sha256(secreto), hash)`,
  valida `revoked_at`/`expires_at`, actualiza `last_used_at`, devuelve
  `Principal(type:'api_key', scopes)`.
- `ScopeMiddleware($scope)` (clon de `PermissionMiddleware`) → 403 si
  `!$principal->hasScope($scope)`.

### 1.3 Webhook hardening

```php
// app/Support/Contracts/WebhookVerifierInterface.php
interface WebhookVerifierInterface
{
    /** HMAC + ventana de timestamp + anti-replay (nonce vía CacheInterface). */
    public function verify(Request $request, string $secret, int $toleranceSeconds = 300): bool;
}
```

Header `X-Signature: t=<ts>,v1=<hmac>`; recomputa
`HMAC-SHA256(ts . '.' . rawBody, secret)`; rechaza si `|now - ts| > tolerance`;
guarda el nonce en `CacheInterface` (ya existe) para descartar reenvíos. Sin
tablas nuevas.

> **Operación — el anti-replay exige un store compartido (D18).** El nonce vive
> en `CacheInterface`, cuyo binding por defecto es `ApcuCache`. APCu es **por
> proceso/host**: no se comparte entre instancias ni entre contenedores. En un
> deploy horizontalmente escalado, un reenvío podría caer en otra instancia y no
> ser detectado. Por eso el `WebhookVerifier` **falla cerrado** si el cache no es
> operativo (`CacheInterface::available() === false`, p. ej. APCu deshabilitado
> con `apc.enable_cli=0`): rechaza la firma en vez de aceptarla sin protección, y
> `bootstrap/app.php` loguea un warning. **Para multi-instancia, vinculá
> `CacheInterface` a un store compartido** (Redis/Memcached/DB) implementando la
> interfaz; el esquema HMAC no cambia.

### 1.4 Entitlements (corazón del diseño)

Tabla de entitlements **efectivos** por tenant (lo que el tenant tiene *ahora*,
sin importar de dónde vino). Tres tipos cubren todos los casos:
`flag` (tiene/no tiene), `quota` (límite numérico por ciclo), `seat` (asientos).

```sql
-- migrations/0008_create_tenant_entitlements_table.php
CREATE TABLE IF NOT EXISTS tenant_entitlements (
    id           BIGINT       NOT NULL AUTO_INCREMENT PRIMARY KEY,
    tenant_id    CHAR(36)     NOT NULL,
    feature      VARCHAR(120) NOT NULL,             -- 'ia.rag' | 'bots' | 'seats' | 'api.calls'
    type         ENUM('flag','quota','seat') NOT NULL,
    limit_value  BIGINT       NULL,                 -- NULL = ilimitado (quota/seat); ignorado en flag
    enabled      TINYINT(1)   NOT NULL DEFAULT 1,
    source       VARCHAR(60)  NOT NULL DEFAULT 'manual', -- 'manual' | 'trial' | 'billing:stripe'
    period_start DATETIME     NULL,                 -- inicio del ciclo vigente (type='quota')
    period_end   DATETIME     NULL,                 -- espejo de subscriptions.current_period_end
    expires_at   DATETIME     NULL,
    updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_tenant_feature (tenant_id, feature),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Contratos (API pública del framework → **SemVer estricto**):

```php
// app/Support/Contracts/EntitlementResolverInterface.php
interface EntitlementResolverInterface
{
    public function for(string $tenantId): EntitlementSet;   // cacheable
}

// app/Support/Entitlements/EntitlementSet.php
final class EntitlementSet
{
    public function allows(string $feature): bool;                 // flag activo y no expirado
    public function limit(string $feature): ?int;                  // null = ilimitado
    public function remaining(string $feature, ?int $used = null): ?int;
}

// app/Support/Contracts/UsageRecorderInterface.php  (metering: SOLO registro)
interface UsageRecorderInterface
{
    public function record(string $tenantId, string $metric, int $qty = 1,
                           ?string $idempotencyKey = null, array $meta = []): void;
    public function total(string $tenantId, string $metric, \DateTimeInterface $since): int;
}
```

```sql
-- migrations/0009_create_usage_events_table.php
CREATE TABLE IF NOT EXISTS usage_events (
    id              BIGINT       NOT NULL AUTO_INCREMENT PRIMARY KEY,
    tenant_id       CHAR(36)     NOT NULL,
    metric          VARCHAR(120) NOT NULL,         -- 'api.calls' | 'ia.tokens' | 'bots.messages'
    quantity        BIGINT       NOT NULL DEFAULT 1,
    idempotency_key VARCHAR(120) NULL,
    meta            JSON         NULL,
    occurred_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_idem (idempotency_key),
    KEY idx_tenant_metric_time (tenant_id, metric, occurred_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Enforcement (clonando el patrón de `PermissionMiddleware`):

```php
// new EntitlementMiddleware($resolver, 'ia.rag')                       → 402/403 si !allows
// new QuotaMiddleware($resolver, $usage, 'api.calls')                  → 429 si remaining<=0
```

Gate para uso dentro de servicios (sin pasar por ruta):

```php
Entitlements::for($tenantId)->allows('bots');
Entitlements::for($tenantId)->remaining('api.calls'); // calcula uso del ciclo
```

---

## Parte 2 — Módulos opcionales

### 2.1 `cynchro/modux-billing` (core, agnóstico de pasarela)

Mismo patrón que `modux-ia`: SDK en Packagist + `app/Modules/Billing` que lo
consume. Tablas **en el módulo**, no en el chasis:

```sql
plans (
    id CHAR(36) PK, `key` VARCHAR(60) UNIQUE,        -- 'pro', 'starter'
    nombre VARCHAR(120), precio DECIMAL(12,2),
    moneda CHAR(3), intervalo ENUM('month','year'),
    gateway VARCHAR(40), gateway_price_id VARCHAR(120)
);

plan_entitlements (                                  -- qué otorga cada plan
    id BIGINT PK, plan_id CHAR(36) FK,
    feature VARCHAR(120), type ENUM('flag','quota','seat'), limit_value BIGINT NULL
);

subscriptions (
    id CHAR(36) PK, tenant_id CHAR(36),              -- FK lógica al tenants del base
    plan_id CHAR(36), status ENUM('trialing','active','past_due','canceled'),
    gateway VARCHAR(40), external_id VARCHAR(120),
    current_period_end DATETIME, cancel_at DATETIME NULL
);

-- invoices / payments ( external_id, status, amount, ... )
```

Contrato de pasarela (driver pattern, como los drivers cloud/local de
`modux-ia`):

```php
// PhpModuxBilling\Contracts\PaymentGatewayInterface
interface PaymentGatewayInterface
{
    public function createCheckout(string $tenantId, Plan $plan): CheckoutSession;
    public function parseWebhook(Request $request): GatewayEvent;   // usa el WebhookVerifier del base
    public function cancel(string $externalSubscriptionId): void;
}
```

### 2.2 Adaptadores

- `cynchro/modux-billing-stripe` → `StripeGateway implements PaymentGatewayInterface`
- `cynchro/modux-billing-mercadopago` → `MercadoPagoGateway implements PaymentGatewayInterface`

### 2.3 `cynchro/modux-oauth` (opcional)

Authorization server completo (client registration, consent, authorization code
+ PKCE, refresh). Solo si se necesita como producto. El *resource server*
(validar un access token con scopes) va en el base como otro guard.

---

## Parte 3 — La costura base ↔ billing

```
1. Tenant compra plan 'pro'        → Billing/Stripe checkout
2. Stripe → webhook                → WebhookVerifier (BASE) valida firma
3. modux-billing activa subscription, lee plan_entitlements('pro')
4. ── ESCRIBE en tenant_entitlements (tabla del BASE), source='billing:stripe',
      period_start/period_end = ciclo de la suscripción ──
5. modux-ia / bots / reportes      → EntitlementMiddleware('ia.rag') del BASE
                                      (NO conocen billing; solo preguntan al gate)
6. modux-ia registra uso           → UsageRecorder->record() (BASE)
7. modux-billing lee usage_events  → rating / overage / próxima factura
```

**Regla de oro:** billing es el **único** que escribe `tenant_entitlements`
(incl. `period_start/period_end`); el chasis y los módulos de producto solo
**leen**. Quitás billing y los entitlements siguen ahí (poblables a mano con
`source='manual'`). Eso mantiene a `modux-ia` sin dependencia de billing.

---

## Decisiones cerradas

### D1 — Naming de features: namespaced por módulo

`ia.rag`, `bots.outbound`, `api.calls`. Evita colisiones entre módulos y hace
legible el catálogo.

### D2 — Período de quota: anclado al ciclo de la suscripción

Cada tenant paga en distinta fecha; su cuota corre desde *su* alta, no desde un
mes calendario global. Para no acoplar el base a la tabla `subscriptions` del
módulo billing, el período se **denormaliza** en `tenant_entitlements`
(`period_start`, `period_end`). El base lee su propia tabla; billing los
mantiene.

`remaining()` queda autónomo (cero conocimiento de billing):

```php
public function remaining(string $feature, ?int $used = null): ?int
{
    $e = $this->byFeature[$feature];
    if ($e->limit === null) return null;                 // ilimitado

    $used ??= $this->usage->total(
        $this->tenantId,
        $feature,                                         // feature de quota == metric (1:1)
        since: $e->periodStart ?? $this->calendarMonthStart()  // fallback sin billing
    );
    return max(0, $e->limit - $used);
}
```

Renovación del ciclo (sin borrar datos): al recibir `invoice.paid`, billing
actualiza `subscriptions.current_period_end` y **reescribe**
`period_start/period_end` en `tenant_entitlements`. Como `remaining()` cuenta
`usage_events` con `occurred_at >= period_start`, mover la ventana "resetea" el
contador pero conserva el histórico para auditoría/rating.

Fallback standalone: si `period_start` es `NULL` (entitlement `manual`, sin
billing), el resolver usa ancla de **mes calendario**. Red de seguridad:
comando idempotente `php modux entitlements:roll-periods` (cron) que avanza
ciclos vencidos por si un webhook se pierde.

### D3 — `scope` (api key) y `entitlement` (tenant) son ortogonales: ambos checks

`scope` dice *qué endpoint* puede tocar una credencial; `entitlement` dice *qué
tiene el tenant*. Una key con scope `ia:rag` igual choca contra el entitlement
`ia.rag` del tenant. Orden en el pipeline de middlewares:

```
AuthMiddleware (guard: JWT | ApiKey)   → ¿quién sos? (Principal + tenant + scopes)
  → TenantMiddleware                   → ¿tenant válido?
  → ScopeMiddleware('ia:rag')          → ¿la credencial puede tocar este endpoint? (403)
  → EntitlementMiddleware('ia.rag')    → ¿el tenant tiene la feature?            (402/403)
  → QuotaMiddleware('api.calls')       → ¿le queda cuota este ciclo?             (429)
```

Status codes fijados:
- entitlement ausente que **se compra** → `402 Payment Required` (señal al front
  "actualizá tu plan").
- scope insuficiente → `403 Forbidden`.
- cuota agotada → `429 Too Many Requests` + header `Retry-After: <period_end>`.

---

## Orden de implementación (dependencias primero)

| Fase | Entregable | Capa | Desbloquea | Estado |
|---|---|---|---|---|
| 1 | Guards + `Principal` + `ApiKeyManager`/`ApiKeyGuard` + `ScopeMiddleware` | base | API para terceros | ✅ implementada |
| 1.b | Módulo `ApiKeys` — CRUD (`/api-keys`) protegido por scope `apikeys.manage` | base | gestión de keys por el tenant | ✅ implementada |
| 2 | `WebhookVerifier` (HMAC + timestamp + anti-replay) | base | pagos seguros, integraciones | ✅ implementada |
| 3 | `tenant_entitlements` + `EntitlementResolver`/`Set` + `EntitlementMiddleware` | base | gating del SaaS | ✅ implementada (lectura+gating; escritura → Fase 5) |
| 4 | `usage_events` + `UsageRecorder` + `QuotaMiddleware` + `entitlements:roll-periods` | base | metering | ✅ implementada |
| 5 | `cynchro/modux-billing` core + `plan_entitlements` → escribe entitlements | módulo | planes | ✅ implementada (en `../modulos/billing`) |
| 6 | `-stripe` / `-mercadopago` | módulos | cobro real | ✅ implementada (paquetes separados) |
| 7 | `cynchro/modux-oauth` (opcional) | módulo | OAuth server | pendiente |

Fases 1–4 son el **base** y prerequisito; 5–7 son módulos. Cada fase del base
entra con migración versionada + tests y debe pasar el quality gate
(`.githooks/pre-push` + CI).

## Consecuencias

- **Positivas:** el chasis gana identidad/autorización de SaaS sin SDKs
  externos; billing y add-ons quedan desacoplados vía `tenant_entitlements`;
  `modux-ia` no arrastra billing; el framework sigue usable sin cobrar.
- **Costos / riesgos:** el contrato de entitlements es API pública → romperlo
  rompe módulos de terceros (mitigación: SemVer estricto, diseño cuidado desde
  fase 3). La denormalización de períodos exige que billing mantenga
  `period_start/period_end` consistentes (mitigación: comando
  `entitlements:roll-periods` como red de seguridad).
- **Abierto a futuro:** agregado de `usage_events` a `usage_counters` si el
  volumen lo exige; segundo guard de OAuth resource-server cuando se publique
  `modux-oauth`.
