# Config, logging, paginación, migraciones y testing


## Config

```php
Config::get('auth.jwt_secret');         // config/auth.php → jwt_secret
Config::get('app.debug', false);        // con default
Config::get('cors.allowed_origins');    // array

Config::all('database');                // todo config/database.php como array
```

Los archivos de config viven en `config/` y son PHP plano que devuelve arrays. Los valores se mapean a variables de entorno vía `$_ENV`.

---

## Logger

Compatible con PSR-3. Se inyecta por constructor:

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

Escribe a `storage/logs/app.log` (JSON estructurado, una entrada por línea):

```json
{"timestamp":"2026-04-25T15:30:00+00:00","level":"info","message":"Deleting product","context":{"id":42}}
```

Niveles de log (en orden): `debug`, `info`, `notice`, `warning`, `error`, `critical`, `alert`, `emergency`

El nivel mínimo se controla con `LOG_LEVEL`. Los mensajes por debajo se descartan en silencio.

El destino se controla con `LOG_CHANNEL`:

- `file` (por defecto) — agrega a `storage/logs/app.log`.
- `stderr` — escribe al stream de error del proceso. Usa `php://stderr` (no la
  constante `STDERR`), así que funciona bajo **cualquier SAPI** — CLI, Apache/mod_php o php-fpm.

Si no se puede escribir el archivo de log, el logger cae a stderr automáticamente — sin
fallos silenciosos.

---

## Paginación

`PaginatorHelper` envuelve cualquier query SQL y lee `page` / `perPage` de los parámetros de query automáticamente.

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

Parámetros de query aceptados:

| Param | Default | Descripción |
|---|---|---|
| `page` | `1` | Página actual (índice 1) |
| `perPage` | `10` | Ítems por página |
| `paginate` | `true` | Poné `false` para devolver todos los resultados sin paginar |

Forma de la respuesta (siempre HTTP 200, incluso cuando `results` está vacío):

```json
{
  "total": 42,
  "cantidad_por_pagina": 10,
  "pagina": 2,
  "cantidad_total": 42,
  "results": [...]
}
```

LIMIT y OFFSET se vinculan con prepared statements de PDO. `perPage` y `page` se castean a enteros.

---

## Migraciones

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

### Quality gate — no subas una base rota

Este es un framework versionado del que otros dependen, así que los mismos chequeos corren en tres lugares:

- **Pre-push hook local** (`.githooks/pre-push`) — bloquea `git push` salvo que `composer validate`,
  `composer audit`, lint, análisis estático y tests pasen todos. Activalo una vez por clon:

  ```bash
  git config core.hooksPath .githooks
  ```

  Saltealo solo en una emergencia con `git push --no-verify`.

- **CI** (`.github/workflows/ci.yml`) — corre el mismo gate (incl. `composer audit`) contra un
  service **MySQL 8** real para que los tests de integración ejerciten SQL/migraciones, más un
  build de la imagen Docker, en cada push y PR a `main`.

### Unit tests — repositorios mockeados, sin DB

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

`UnitTestCase` provee:
- `setUp()` — limpia las superglobales antes de cada test
- `makeRequest(?array $user, ?string $tenantId): Request` — construye un Request con contexto user/tenant preseteado

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
