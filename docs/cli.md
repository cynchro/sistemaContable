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
> seguridad de ciclos de cuota) se documentan en detalle en la página **Plataforma**
> — definición/despacho de jobs, el worker, jobs fallidos y el reseteo de cuotas vencidas.

### `make:module`

```bash
php modux make:module Producto
php modux make:module Factura --with-tenant   # repositorio con scope de tenant + TenantMiddleware
```

Genera `app/Modules/Producto/` con:

```
Controllers/ProductoController.php
Repositories/ProductoRepository.php   (o la variante con scope de tenant)
Services/ProductoService.php
Requests/CreateProductoRequest.php
Requests/UpdateProductoRequest.php
routes.php                            (o con TenantMiddleware)
```

El módulo se **autodescubre** — `bootstrap/app.php` hace un glob de `app/Modules/*/routes.php` al arrancar. No hace falta registrarlo a mano. Opcionalmente, agregá `app/Modules/{Name}/ServiceProvider.php` — también se autodescubre.

### `make:migration`

```bash
php modux make:migration create_productos_table
# → migrations/0002_create_productos_table.php
```

Los archivos se numeran de forma secuencial. Cada uno devuelve una clase anónima con `up(PDO)` y `down(PDO)`.

### `migrate`

```bash
php modux migrate
```

```
  migrated   0001_create_base_tables.php
  skipped    0002_create_clientes_table.php   ← ya se ejecutó

  1 migration(s) ran.
```

Registra las migraciones aplicadas en una tabla `migrations` con un número de `batch`. Es seguro correrlo en cada deploy.

### `migrate:rollback` y `migrate:fresh`

```bash
php modux migrate:rollback   # deshace el último batch
php modux migrate:fresh      # rollback de todo + re-ejecuta todo (deja el esquema limpio)
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

No requiere conexión a la base de datos.

---
