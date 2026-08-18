# Eventos, RBAC, entitlements, jobs, health y variables de entorno


## Eventos

`EventDispatcher` provee un bus de eventos síncrono in-process. Inyectalo donde sea vía el container.

```php
// Suscribirse en ServiceProvider::boot()
$dispatcher->listen('usuario.created', function (array $payload): void {
    // enviar email de bienvenida, registrar auditoría, etc.
    // $payload = ['id' => 42, 'email' => 'user@example.com']
});

// Despachar desde un Service
$this->dispatcher->dispatch('usuario.created', [
    'id'    => $id,
    'email' => $data['email'],
]);

// Chequear si hay alguien escuchando
$dispatcher->hasListeners('usuario.created'); // bool
```

Los eventos son síncronos — quien dispara espera a que terminen todos los listeners. Para un comportamiento fire-and-forget, envolvé el cuerpo del listener en un `try/catch`.

---

## RBAC — control de acceso por permisos

Asigná claves de permiso a roles vía la tabla `roles_permisos` (cada fila vincula un `rol_id` con un `permiso_id`). Usá `PermissionMiddleware` en las rutas que requieren un permiso específico:

```php
use App\Http\Middleware\PermissionMiddleware;

$router->group([AuthMiddleware::class, TenantMiddleware::class], function ($router) {
    $router->get('/facturas',       [FacturaController::class, 'index']);
    $router->post('/facturas',      [FacturaController::class, 'create'],   [PermissionMiddleware::class . ':facturas.write']);
    $router->delete('/facturas/{id}', [FacturaController::class, 'delete'], [PermissionMiddleware::class . ':facturas.delete']);
});
```

El middleware lanza `ForbiddenException` (403) si el rol del usuario autenticado no tiene el permiso pedido. `AdminMiddleware` sigue cubriendo los gates simples de solo-admin; usá `PermissionMiddleware` para un control fino por operación.

---

## Entitlements — gating de features por tenant

Los entitlements responden "**qué tiene este tenant?**" — qué módulos/features, cuántos
asientos, qué cuotas — independientemente de quién es el usuario (RBAC) o qué puede tocar una
credencial (scopes). Viven en `tenant_entitlements` y se leen a través de
`EntitlementResolverInterface` (`App\Support\Entitlements\DbEntitlementResolver`).

Tres tipos: `flag` (tiene / no tiene), `quota` (límite numérico por ciclo), `seat` (asientos).
`limit_value` null = ilimitado. Las features son namespaced (`ia.rag`, `bots.outbound`).

Protegé una ruta con el middleware parametrizado (después de `TenantMiddleware`):

```php
$router->post('/ia/ask', [IAController::class, 'ask'],
    [AuthMiddleware::class, TenantMiddleware::class,
     EntitlementMiddleware::class . ':ia.rag']);
```

Feature ausente/deshabilitada → **402 Payment Required** (una señal accionable de "mejorá tu
plan", distinta de 403). En código:

```php
$set = $resolver->for($tenantId);
$set->allows('ia.rag');             // bool (flag / gating)
$set->limit('api.calls');           // ?int (null = ilimitado)
$set->remaining('api.calls', $used);// ?int, `used` se pasa (sin I/O en el value object)
```

**El base solo lee `tenant_entitlements`.** Lo puebla el módulo opcional de billing
(`source = 'billing:*'`) o se carga a mano (`source = 'manual'`) — así los módulos de producto
(p. ej. `modux-ia`) nunca dependen de billing.

### Metering de uso y cuotas

Registrá el uso vía `UsageRecorderInterface` (`App\Support\Usage\DbUsageRecorder`, tabla
`usage_events`). El registro es **explícito** — el código que consume decide el costo por llamada:

```php
$usage->record($tenantId, 'api.calls', 1, $idempotencyKey);  // idempotency_key deduplica reintentos
$usage->record($tenantId, 'ia.tokens', $tokensUsed);
```

`QuotaMiddleware:<feature>` aplica el límite (después de `TenantMiddleware`). Cuenta los
`usage_events` desde el `period_start` del entitlement (o el inicio del mes calendario cuando no
hay billing) y compara contra el límite:

```php
$router->post('/ia/ask', [IAController::class, 'ask'],
    [AuthMiddleware::class, TenantMiddleware::class, QuotaMiddleware::class . ':api.calls']);
```

- sin entitlement / deshabilitado → **402**, ilimitado (`limit_value` null) → pasa,
- cuota agotada → **429** con `Retry-After` (segundos hasta que se resetea el ciclo).

Los ciclos de cuota se anclan al `period_start/period_end` de la suscripción (denormalizados en
`tenant_entitlements` por billing). Mover la ventana resetea la cuota **sin borrar** los
`usage_events` (se conservan para auditoría/rating). Como red de seguridad ante renovaciones perdidas:

```bash
php modux entitlements:roll-periods   # avanza los ciclos de cuota vencidos por su propio span; idempotente
```

Ver `docs/adr/0001-saas-identity-entitlements-billing.md` para el diseño completo.

---

## Transacciones de base de datos

`App\Support\DB` envuelve operaciones en una transacción PDO con rollback automático ante cualquier excepción:

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

Inyectá `DB` en cualquier service; el container lo autoconecta con el singleton de PDO registrado.

---

## Cola de jobs

Cola asíncrona respaldada por DB. Los jobs se guardan en una tabla `jobs` y los procesa un proceso worker. Pueden correr varios workers en paralelo — el claim se hace con un `UPDATE` atómico por UUID.

### Definir un job

```php
namespace App\Modules\Notificaciones\Jobs;

use App\Support\Container;
use App\Support\Job;

class SendWelcomeEmailJob extends Job
{
    public string $email = '';
    public string $name  = '';
    public string $queue = 'emails';   // sobrescribe la cola por defecto

    public function handle(Container $container): void
    {
        $container->get(MailService::class)->sendWelcome($this->email, $this->name);
    }
}
```

Las propiedades públicas (excepto las reservadas por el framework `queue`, `maxAttempts`, `delaySeconds`) se serializan como payload JSON en la DB. Las dependencias de servicios se resuelven desde el Container cuando corre `handle()`.

### Despachar

```php
// Inyectá JobDispatcher en el constructor de cualquier service
public function __construct(private JobDispatcher $dispatcher) {}

$job        = new SendWelcomeEmailJob();
$job->email = $data['email'];
$job->name  = $data['nombre'];
$this->dispatcher->dispatch($job);

// Despachar con delay (segundos antes de que el job quede disponible)
$job->delaySeconds = 300;
$this->dispatcher->dispatch($job);
```

### Correr el worker

```bash
php modux queue:work                           # procesa la cola 'default', duerme 3s entre polls
php modux queue:work --queue=emails            # procesa una cola específica
php modux queue:work --queue=emails --sleep=5  # intervalo de sleep custom
php modux queue:work --once                    # procesa un job y sale (útil para cron)
php modux queue:work --timeout=10              # libera jobs trabados > 10 minutos
```

SIGINT / SIGTERM (Ctrl-C) dispara un apagado ordenado — el worker termina el job actual antes de detenerse.

En producción, gestioná el worker con **supervisord** o **systemd** para que se reinicie automáticamente si se cae.

### Jobs fallidos

Al fallar, el job se reintenta hasta `maxAttempts` veces (3 por defecto) con back-off exponencial: `2^attempts` segundos entre reintentos. Tras el último intento, la fila del job se marca `status = 'failed'` con el mensaje de error completo guardado.

```bash
php modux queue:failed          # lista todos los jobs fallidos
php modux queue:retry 42        # resetea el job #42 a 'pending' para que el worker lo retome
php modux queue:flush           # borra todos los jobs fallidos
```

### Esquema de la tabla `jobs`

| Columna | Tipo | Descripción |
|---|---|---|
| `id` | INT AUTO_INCREMENT | Clave primaria |
| `queue` | VARCHAR(100) | Nombre de la cola |
| `payload` | MEDIUMTEXT | Clase + datos serializados en JSON |
| `attempts` | INT | Cuántas veces lo intentó el worker |
| `max_attempts` | INT | Copiado del Job al despachar |
| `status` | ENUM | `pending`, `running`, `failed` |
| `available_at` | DATETIME | Cuándo queda elegible el job (soporta delay) |
| `reserved_at` | DATETIME | Cuándo lo reclamó un worker |
| `reserved_by` | CHAR(36) | UUID del worker que lo reclamó (lock atómico) |
| `failed_at` | DATETIME | Cuándo se marcó finalmente como fallido |
| `error` | TEXT | Mensaje de la excepción + trace |

---

## Health check

```
GET /health
```

Chequea las dependencias con distinta severidad: la **DB es crítica** (si falla → `status:
down` + **HTTP 503**, saca la instancia del balanceador); el **cache** es una degradación
(rate limiting / anti-replay), se reporta pero **no cambia** el código de estado.

```json
{ "success": true, "data": { "status": "ok", "php": "8.2.0", "checks": { "db": "ok", "cache": "ok" } } }
```

```json
{ "success": true, "data": { "status": "down", "php": "8.2.0", "checks": { "db": "unreachable", "cache": "degraded" } } }
```

Usá este endpoint para los health probes del load balancer, monitores de uptime y scripts de deploy.

---

## Variables de entorno

Copiá `.env.example` → `.env`. Requeridas al arrancar (las variables faltantes lanzan de inmediato):

| Variable | Descripción |
|---|---|
| `JWT_SECRET` | Mín. 32 chars. Generá: `php -r "echo bin2hex(random_bytes(32));"` |
| `DB_HOST` | Host de la base de datos |
| `DB_NAME` | Nombre de la base de datos |
| `DB_USER` | Usuario de la base de datos |
| `DB_PASS` | Contraseña de la base de datos |

Opcionales:

| Variable | Default | Descripción |
|---|---|---|
| `APP_ENV` | `local` | `local` / `production` |
| `APP_DEBUG` | `false` | Expone el detalle de excepciones en las respuestas JSON |
| `JWT_TTL` | `86400` | Vida del access token en segundos |
| `JWT_REFRESH_TTL` | `604800` | Vida del refresh token en segundos (7 días) |
| `JWT_ALGO` | `HS256` | Algoritmo de firma del JWT |
| `DB_PORT` | `3306` | Puerto de la base de datos |
| `DB_PERSISTENT` | `false` | Conexiones PDO persistentes (mejor latencia; ajustá `max_connections` antes de activar) |
| `LOG_CHANNEL` | `file` | `file` o `stderr` |
| `LOG_LEVEL` | `debug` | Nivel mínimo de log a escribir |
| `CORS_ALLOWED_ORIGINS` | _(ninguno)_ | Lista de orígenes permitidos separados por coma |
| `MAIL_HOST`, `MAIL_PORT`, `MAIL_USER`, `MAIL_PASS`, `MAIL_FROM` | — | Credenciales SMTP para `EmailHelper` |

---
