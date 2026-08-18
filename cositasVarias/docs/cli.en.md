# CLI — referencia de `php modux`


## CLI — `php modux`

```
php modux make:module <Name> [--with-tenant]   Scaffold a complete module
php modux make:migration <name>                Create a versioned migration file
php modux make:test <Name>                     Generate a unit test stub
php modux migrate                              Run all pending migrations
php modux migrate:rollback                     Roll back the last migration batch
php modux migrate:fresh                        Rollback all + re-run all migrations
php modux routes                               List every registered route
php modux queue:work [--queue= --sleep= --once]  Process jobs from a queue
php modux queue:failed                         List failed jobs
php modux queue:retry <id>                     Retry a failed job
php modux queue:flush                          Delete all failed jobs
php modux entitlements:roll-periods            Advance expired quota cycles (safety net)
```

> Los comandos `queue:*` (worker de la cola de jobs) y `entitlements:roll-periods` (red de
> seguridad de ciclos de cuota) se documentan en detalle en la página **Platform**
> — definición/despacho de jobs, el worker, jobs fallidos y el reseteo de cuotas vencidas.

### `make:module`

```bash
php modux make:module Producto
php modux make:module Factura --with-tenant   # tenant-scoped repository + TenantMiddleware
```

Generates `app/Modules/Producto/` with:

```
Controllers/ProductoController.php
Repositories/ProductoRepository.php   (or tenant-scoped variant)
Services/ProductoService.php
Requests/CreateProductoRequest.php
Requests/UpdateProductoRequest.php
routes.php                            (or with TenantMiddleware)
```

The module is **auto-discovered** — `bootstrap/app.php` globs `app/Modules/*/routes.php` at boot. No manual registration needed. Optionally add `app/Modules/{Name}/ServiceProvider.php` — it will also be auto-discovered.

### `make:migration`

```bash
php modux make:migration create_productos_table
# → migrations/0002_create_productos_table.php
```

Files are numbered sequentially. Each file returns an anonymous class with `up(PDO)` and `down(PDO)`.

### `migrate`

```bash
php modux migrate
```

```
  migrated   0001_create_base_tables.php
  skipped    0002_create_clientes_table.php   ← already ran

  1 migration(s) ran.
```

Tracks ran migrations in a `migrations` table with a `batch` number. Safe to run on every deploy.

### `migrate:rollback` and `migrate:fresh`

```bash
php modux migrate:rollback   # undo last batch
php modux migrate:fresh      # rollback all + re-run all (resets to clean state)
```

### `routes`

```bash
php modux routes
```

```
  METHOD    URI                         HANDLER                        MIDDLEWARES
  ─────────────────────────────────────────────────────────────────────────────────
  POST      /auth/login                 AuthController@login
  POST      /auth/refresh               AuthController@refresh
  POST      /auth/logout                AuthController@logout           AuthMiddleware
  POST      /auth/impersonate           AuthController@impersonate      Auth, Admin, Tenant
  GET       /clientes                   ClienteController@index         Auth, Tenant
  GET       /health                     HealthController@check
```

Does not require a database connection.

---

