# Config, logging, paginación, migraciones y testing


## Config

```php
Config::get('auth.jwt_secret');         // config/auth.php → jwt_secret
Config::get('app.debug', false);        // with default
Config::get('cors.allowed_origins');    // array

Config::all('database');                // entire config/database.php as array
```

Config files live in `config/` and are plain PHP files returning arrays. Values map to env vars via `$_ENV`.

---

## Logger

PSR-3 compliant. Inject via constructor:

```php
public function __construct(
    private ProductoRepository $repository,
    private \App\Support\Logger $logger,
) {}

public function delete(int $id): void
{
    $this->logger->info('Deleting product', ['id' => $id]);
    $this->repository->delete($id);
    $this->logger->error('DB error', ['exception' => $e->getMessage()]);
}
```

Output to `storage/logs/app.log` (structured JSON, one entry per line):

```json
{"timestamp":"2026-04-25T15:30:00+00:00","level":"info","message":"Deleting product","context":{"id":42}}
```

Log levels (in order): `debug`, `info`, `notice`, `warning`, `error`, `critical`, `alert`, `emergency`

The minimum level is controlled by `LOG_LEVEL`. Messages below it are silently dropped.

The destination is controlled by `LOG_CHANNEL`:

- `file` (default) — appends to `storage/logs/app.log`.
- `stderr` — writes to the process error stream. It uses `php://stderr` (not the
  `STDERR` constant), so it works under **any SAPI** — CLI, Apache/mod_php or php-fpm.

If the log file cannot be written, the logger falls back to stderr automatically — no
silent failures.

---

## Pagination

`PaginatorHelper` wraps any SQL query and reads `page` / `perPage` from query parameters automatically.

```php
public function list(?string $tenantId = null): array
{
    $sql    = 'SELECT * FROM productos WHERE activo = 1';
    $params = [];

    if ($tenantId !== null) {
        $sql    .= ' AND tenant_id = ?';
        $params[] = $tenantId;
    }

    return (new PaginatorHelper($this->pdo, $sql, $params))->getPaginatedResults();
}
```

Query parameters accepted:

| Param | Default | Description |
|---|---|---|
| `page` | `1` | Current page (1-indexed) |
| `perPage` | `10` | Items per page |
| `paginate` | `true` | Set to `false` to return all results unpaged |

Response shape (always HTTP 200, even when `results` is empty):

```json
{
  "total": 42,
  "cantidad_por_pagina": 10,
  "pagina": 2,
  "cantidad_total": 42,
  "results": [...]
}
```

LIMIT and OFFSET are bound via PDO prepared statements. `perPage` and `page` are cast to integers.

---

## Migrations

```php
// migrations/0002_create_productos_table.php
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS productos (
                id         INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
                nombre     VARCHAR(255) NOT NULL,
                precio     INT          NOT NULL DEFAULT 0,
                tenant_id  CHAR(36)     NOT NULL,
                created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_tenant (tenant_id),
                CONSTRAINT fk_productos_tenant
                    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS productos');
    }
};
```

---

## Testing

```bash
composer test      # PHPUnit (unit + integración)
composer lint      # phpcs PSR-12
composer analyse   # phpstan level 6 (PHPStan 2.x)
composer audit     # vulnerabilidades en dependencias
```

### Quality gate — don't push a broken base

This is a versioned framework that others depend on, so the same checks run in three places:

- **Local pre-push hook** (`.githooks/pre-push`) — blocks `git push` unless `composer validate`,
  `composer audit`, lint, static analysis and tests all pass. Enable it once per clone:

  ```bash
  git config core.hooksPath .githooks
  ```

  Bypass only in an emergency with `git push --no-verify`.

- **CI** (`.github/workflows/ci.yml`) — runs the same gate (incl. `composer audit`) against a
  real **MySQL 8 service** so the integration tests exercise SQL/migrations, plus a Docker
  image build, on every push and PR to `main`.

### Unit tests — mock repositories, no DB

```php
class ProductoServiceTest extends UnitTestCase
{
    private ProductoRepository $repository;
    private ProductoService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = $this->createMock(ProductoRepository::class);
        $this->service    = new ProductoService($this->repository);
    }

    public function test_throws_not_found_when_product_missing(): void
    {
        $this->repository
            ->method('findById')
            ->willThrowException(new NotFoundException('Producto', 99));

        $this->expectException(NotFoundException::class);
        $this->service->get(99);
    }
}
```

`UnitTestCase` provides:
- `setUp()` — clears superglobals before each test
- `makeRequest(?array $user, ?string $tenantId): Request` — builds a Request with pre-set user/tenant context

### Feature / integration tests — HTTP real, DB real, rollback por test

Despachan la petición por el Router real (los middlewares de ruta —Auth, Tenant, Scope,
Entitlement, Quota— se ejecutan) contra una base **MySQL real**. Cada test corre dentro de
una transacción que se revierte en `tearDown()`, así que no hace falta re-migrar entre tests.

```php
class ClienteFeatureTest extends FeatureTestCase
{
    public function test_create_returns_201(): void
    {
        $ctx = $this->actingAsUser(); // siembra tenant + usuario + JWT

        $res = $this->postJson('/clientes', ['nombre' => 'Acme'], $this->bearer($ctx['token']));

        $this->assertSame(201, $res['status']);
        $this->assertSame('Acme', $res['json']['data']['nombre']);
    }
}
```

`FeatureTestCase` provee:

- `actingAsUser(?string $tenantId = null, int $rol = 1): array` — siembra tenant + usuario,
  genera su JWT (guardado en `usuarios.token`) y devuelve `['tenantId','userId','token']`.
- `getJson` / `postJson` / `putJson` / `deleteJson(...)` — despachan y devuelven
  `['status' => int, 'json' => array, 'headers' => array]`. Las `AppException` se mapean a
  status/headers igual que el `Handler` de producción (p. ej. 402, 429 + `Retry-After`).
- `bearer(string $token): array` — header `Authorization: Bearer …`.
- `seedTenant()`, `grantFlag()`, `grantQuota()`, `recordUsage()` — sembrado de dominio para
  probar entitlements y cuotas.
- `registerRoute(...)` — registra una ruta ad-hoc (p. ej. para ejercitar middlewares de gating
  sin que un módulo del base exponga una ruta protegida).

**Requieren MySQL.** Las env `DB_*` salen de `phpunit.xml` (`monolito_test`, `root`, sin clave);
el esquema se crea una vez por proceso (drop-all + migraciones). Si no hay base alcanzable
(p. ej. en el pre-push local sin DB), los Feature tests **se saltan** en lugar de fallar — el
**CI** los corre contra su service `mysql:8.0`.

---

