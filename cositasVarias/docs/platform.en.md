# Eventos, RBAC, entitlements, jobs, health y variables de entorno


## Events

`EventDispatcher` provides a synchronous in-process event bus. Inject it anywhere via the container.

```php
// Subscribe in ServiceProvider::boot()
$dispatcher->listen('usuario.created', function (array $payload): void {
    // send welcome email, log audit trail, etc.
    // $payload = ['id' => 42, 'email' => 'user@example.com']
});

// Dispatch from a Service
$this->dispatcher->dispatch('usuario.created', [
    'id'    => $id,
    'email' => $data['email'],
]);

// Check if anyone is listening
$dispatcher->hasListeners('usuario.created'); // bool
```

Events are synchronous — the caller waits for all listeners to finish. For fire-and-forget behaviour wrap the listener body in a `try/catch`.

---

## RBAC — permission-based access control

Assign permission keys to roles via the `roles_permisos` table (each row links a `rol_id` to a `permiso_id`). Use `PermissionMiddleware` on routes that require a specific permission:

```php
use App\Http\Middleware\PermissionMiddleware;

$router->group([AuthMiddleware::class, TenantMiddleware::class], function ($router) {
    $router->get('/facturas',       [FacturaController::class, 'index']);
    $router->post('/facturas',      [FacturaController::class, 'create'],   [PermissionMiddleware::class . ':facturas.write']);
    $router->delete('/facturas/{id}', [FacturaController::class, 'delete'], [PermissionMiddleware::class . ':facturas.delete']);
});
```

The middleware throws `ForbiddenException` (403) if the authenticated user's role does not have the requested permission. `AdminMiddleware` still covers simple admin-only gates; use `PermissionMiddleware` for fine-grained per-operation control.

---

## Entitlements — tenant feature gating

Entitlements answer "**what does this tenant have?**" — which modules/features, how many
seats, what quotas — independently of who the user is (RBAC) or what a credential may touch
(scopes). They live in `tenant_entitlements` and are read through
`EntitlementResolverInterface` (`App\Support\Entitlements\DbEntitlementResolver`).

Three types: `flag` (has / hasn't), `quota` (numeric limit per cycle), `seat` (seats).
`limit_value` null = unlimited. Features are namespaced (`ia.rag`, `bots.outbound`).

Gate a route with the parametrized middleware (after `TenantMiddleware`):

```php
$router->post('/ia/ask', [IAController::class, 'ask'],
    [AuthMiddleware::class, TenantMiddleware::class,
     EntitlementMiddleware::class . ':ia.rag']);
```

Missing/disabled feature → **402 Payment Required** (an actionable "upgrade your plan"
signal, distinct from 403). In code:

```php
$set = $resolver->for($tenantId);
$set->allows('ia.rag');             // bool (flag / gating)
$set->limit('api.calls');           // ?int (null = unlimited)
$set->remaining('api.calls', $used);// ?int, used passed in (no I/O in the value object)
```

**The base only reads `tenant_entitlements`.** It's populated by the optional billing
module (`source = 'billing:*'`) or by hand (`source = 'manual'`) — so product modules
(e.g. `modux-ia`) never depend on billing.

### Usage metering & quotas

Record usage via `UsageRecorderInterface` (`App\Support\Usage\DbUsageRecorder`, table
`usage_events`). Recording is **explicit** — the consuming code decides the cost per call:

```php
$usage->record($tenantId, 'api.calls', 1, $idempotencyKey);  // idempotency_key dedupes retries
$usage->record($tenantId, 'ia.tokens', $tokensUsed);
```

`QuotaMiddleware:<feature>` enforces the limit (after `TenantMiddleware`). It counts
`usage_events` from the entitlement's `period_start` (or the calendar month start when there's
no billing) and compares against the limit:

```php
$router->post('/ia/ask', [IAController::class, 'ask'],
    [AuthMiddleware::class, TenantMiddleware::class, QuotaMiddleware::class . ':api.calls']);
```

- no entitlement / disabled → **402**, unlimited (`limit_value` null) → passes,
- quota exhausted → **429** with `Retry-After` (seconds until the cycle resets).

Quota cycles are anchored to the subscription's `period_start/period_end` (denormalized into
`tenant_entitlements` by billing). Moving the window resets the quota **without deleting**
`usage_events` (kept for audit/rating). As a safety net for missed renewals:

```bash
php modux entitlements:roll-periods   # advances expired quota cycles by their own span; idempotent
```

See `docs/adr/0001-saas-identity-entitlements-billing.md` for the full design.

---

## Database transactions

`App\Support\DB` wraps operations in a PDO transaction with automatic rollback on any exception:

```php
class FacturaService
{
    public function __construct(
        private FacturaRepository $facturas,
        private LineaRepository   $lineas,
        private DB                $db,
    ) {}

    public function create(array $data): array
    {
        return $this->db->withTransaction(function () use ($data) {
            $factura = $this->facturas->create($data);
            foreach ($data['lineas'] as $linea) {
                $this->lineas->create($factura['id'], $linea);
            }
            return $factura;
        });
    }
}
```

Inject `DB` in any service; the container auto-wires it with the registered PDO singleton.

---

## Job queue

DB-backed async queue. Jobs are stored in a `jobs` table and processed by a worker process. Multiple workers can run in parallel — claiming is done with an atomic UUID `UPDATE`.

### Defining a job

```php
namespace App\Modules\Notificaciones\Jobs;

use App\Support\Container;
use App\Support\Job;

class SendWelcomeEmailJob extends Job
{
    public string $email = '';
    public string $name  = '';
    public string $queue = 'emails';   // override the default queue

    public function handle(Container $container): void
    {
        $container->get(MailService::class)->sendWelcome($this->email, $this->name);
    }
}
```

Public properties (except the framework-reserved `queue`, `maxAttempts`, `delaySeconds`) are serialized as JSON payload in the DB. Service dependencies are resolved from the Container when `handle()` runs.

### Dispatching

```php
// Inject JobDispatcher in any service constructor
public function __construct(private JobDispatcher $dispatcher) {}

$job        = new SendWelcomeEmailJob();
$job->email = $data['email'];
$job->name  = $data['nombre'];
$this->dispatcher->dispatch($job);

// Dispatch with a delay (seconds before the job becomes available)
$job->delaySeconds = 300;
$this->dispatcher->dispatch($job);
```

### Running the worker

```bash
php modux queue:work                           # process 'default' queue, sleep 3s between polls
php modux queue:work --queue=emails            # process a specific queue
php modux queue:work --queue=emails --sleep=5  # custom sleep interval
php modux queue:work --once                    # process one job then exit (useful for cron)
php modux queue:work --timeout=10              # release jobs stuck > 10 minutes
```

SIGINT / SIGTERM (Ctrl-C) triggers a graceful shutdown — the worker finishes the current job before stopping.

For production, manage the worker with **supervisord** or **systemd** so it restarts automatically if it crashes.

### Failed jobs
        
On failure the job is retried up to `maxAttempts` times (default 3) with exponential back-off: `2^attempts` seconds between retries. After the last attempt the job row is marked `status = 'failed'` with the full error message stored.

```bash
php modux queue:failed          # list all failed jobs
php modux queue:retry 42        # reset job #42 to 'pending' so the worker picks it up again
php modux queue:flush           # delete all failed jobs
```

### `jobs` table schema

| Column | Type | Description |
|---|---|---|
| `id` | INT AUTO_INCREMENT | Primary key |
| `queue` | VARCHAR(100) | Queue name |
| `payload` | MEDIUMTEXT | JSON-serialized class + data |
| `attempts` | INT | How many times the worker tried |
| `max_attempts` | INT | Copied from Job at dispatch time |
| `status` | ENUM | `pending`, `running`, `failed` |
| `available_at` | DATETIME | When the job becomes eligible (supports delay) |
| `reserved_at` | DATETIME | When a worker claimed it |
| `reserved_by` | CHAR(36) | UUID of the worker that claimed it (atomic lock) |
| `failed_at` | DATETIME | When the job was finally marked failed |
| `error` | TEXT | Exception message + trace |

---

## Health check

```
GET /health
```

Checks dependencies with different severities: the **DB is critical** (if it fails →
`status: down` + **HTTP 503**, takes the instance out of the load balancer); the **cache** is
a degradation (rate limiting / anti-replay), reported but it does **not** change the status code.

```json
{ "success": true, "data": { "status": "ok", "php": "8.2.0", "checks": { "db": "ok", "cache": "ok" } } }
```

```json
{ "success": true, "data": { "status": "down", "php": "8.2.0", "checks": { "db": "unreachable", "cache": "degraded" } } }
```

Use this endpoint for load balancer health probes, uptime monitors, and deploy scripts.

---

## Environment variables

Copy `.env.example` → `.env`. Required at boot (missing variables throw immediately):

| Variable | Description |
|---|---|
| `JWT_SECRET` | Min 32 chars. Generate: `php -r "echo bin2hex(random_bytes(32));"` |
| `DB_HOST` | Database host |
| `DB_NAME` | Database name |
| `DB_USER` | Database user |
| `DB_PASS` | Database password |

Optional:

| Variable | Default | Description |
|---|---|---|
| `APP_ENV` | `local` | `local` / `production` |
| `APP_DEBUG` | `false` | Expose exception details in JSON responses |
| `JWT_TTL` | `86400` | Access token lifetime in seconds |
| `JWT_REFRESH_TTL` | `604800` | Refresh token lifetime in seconds (7 days) |
| `JWT_ALGO` | `HS256` | JWT signing algorithm |
| `DB_PORT` | `3306` | Database port |
| `DB_PERSISTENT` | `false` | Persistent PDO connections (better latency; tune `max_connections` before enabling) |
| `LOG_CHANNEL` | `file` | `file` or `stderr` |
| `LOG_LEVEL` | `debug` | Minimum log level to write |
| `CORS_ALLOWED_ORIGINS` | _(none)_ | Comma-separated list of allowed origins |
| `MAIL_HOST`, `MAIL_PORT`, `MAIL_USER`, `MAIL_PASS`, `MAIL_FROM` | — | SMTP credentials for `EmailHelper` |

---

