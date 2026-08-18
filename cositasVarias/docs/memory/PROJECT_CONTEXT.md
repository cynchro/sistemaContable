# PROJECT_CONTEXT.md

## Objetivo del proyecto

`cynchro/modux` — framework PHP liviano, **dependency-injection-first**, organizado
como **modular monolith multi-tenant**. Se distribuye públicamente (repo
`git@github.com:cynchro/modux.git`, paquete Packagist `cynchro/modux`), lo descargan
terceros y **va escalando en versionado** → no se puede romper la base al publicar.

Sirve como base para construir SaaS. Actualmente en expansión hacia una **capa SaaS**
(identidad de terceros, entitlements, billing) descrita en
`docs/adr/0001-saas-identity-entitlements-billing.md`.

Filosofía declarada (README "Why not Laravel?"): ~5 dependencias runtime, ciclo de
request en pocos archivos, DI explícito sin facades/magia, raw PDO sin ORM, "leé el
código y entendé todo".

## Stack completo

- **PHP** `^8.2` (local CLI probado en 8.3.6; contenedor Docker en `php:8.2-apache`).
- **MySQL** 8.0 (raw PDO, sin ORM).
- **Apache** (Docker, DocumentRoot → `public/`, `a2enmod rewrite`).
- **Composer** 2.x.
- **Dependencias runtime**: `firebase/php-jwt ^6.10`, `phpmailer/phpmailer ^6.9`,
  `psr/container ^2.0`, `psr/log ^3.0`, `vlucas/phpdotenv ^5.6`.
- **Dev**: `phpunit/phpunit ^11`, `squizlabs/php_codesniffer ^3.9` (PSR-12),
  `phpstan/phpstan ^2.0` (nivel 6, con baseline).
- **Add-on opcional** (`suggest`, NO en el base): `cynchro/modux-ia` — SDK IA (LLM+RAG,
  namespace `PhpAI`), vive en `../modulos/ia`, publicado en Packagist
  (`cynchro/modux-ia`, v0.0.1).
- **Cache / rate limiting**: APCu (`App\Support\Cache\ApcuCache`; fallback `ArrayCache`).
- **Docker**: `docker-compose.yml` con servicios `modux-backend` (build desde `.`,
  `docker/Dockerfile` instala `pdo_mysql mysqli gd zip mbstring`) y `moduxdb`
  (`mysql:8.0`). Puerto app 8080, DB 3306.
- **CLI propio**: `php modux` — `make:module`, `make:migration`, `make:test`,
  `migrate`, `migrate:rollback`, `migrate:fresh`, `routes`, `queue:work`,
  `queue:failed`, `queue:retry`, `queue:flush`.
- **Quality gate**: `.githooks/pre-push` (activar con `git config core.hooksPath .githooks`)
  + `.github/workflows/ci.yml`. Ambos corren `composer validate/lint/analyse/test`;
  CI además buildea la imagen Docker.

## Arquitectura real

### Entry point y bootstrap
- `public/index.php` → requiere `bootstrap/app.php`, que devuelve el `Container` (`$app`),
  obtiene el `Kernel` y llama `handle()`.
- `bootstrap/app.php` por etapas: (1) env vía dotenv `safeLoad`, exige
  `JWT_SECRET, DB_HOST, DB_NAME, DB_USER, DB_PASS`; falla si no hay `.env`. (2) `Config`
  apunta a `config/`. (3) error display off. (4) `Container`. (5) `Logger` + registro
  del `Handler` de excepciones. (6) `PDO` singleton (DSN mysql) + `DB` wrapper. (7)
  `JobDispatcher`, `CacheInterface → ApcuCache`, `Router`, `Kernel`, `EventDispatcher`.
  (7.5) **ServiceProviders de módulos auto-discovered** (glob
  `app/Modules/*/ServiceProvider.php` → `register()` + `boot()`). (8) **rutas de módulos
  auto-discovered** (glob `app/Modules/*/routes.php`, ejecutadas con `$router` global en
  scope). (9) rutas de infraestructura: `/`, `/health`, `/admin/logs*`.

### DI Container (`app/Support/Container.php`)
`bind/singleton/instance/get/make/makeWith`. **Autowiring por reflection**: dependencias
no-builtin se resuelven vía `get()`; `makeWith($class, ...$scalars)` inyecta scalars en
los parámetros builtin en orden (esto habilita el patrón de middleware parametrizado).
`PDO` es singleton compartido por toda la app.

### Router (`app/Support/Router.php`)
- `get/post/put/patch/delete($uri, $action, $protected = false)` donde `$protected` es
  `bool|list<string>` de middlewares; `group($middlewares, $prefix?, $callback)`.
- Middlewares son **class-strings**. Patrón parametrizado `"Clase:param"` → el router usa
  `container->makeWith($class, $param)` (así `PermissionMiddleware:usuarios.delete` y
  `ScopeMiddleware:apikeys.manage`).
- `dispatch()` compila `{param}` a named groups regex, arma el `Pipeline` (global +
  por-ruta), resuelve el controller del container e inyecta args por reflection
  (`Request`, subclases de `FormRequest`, o servicios del container).
- Devuelve 405 si el path existe para otro método, 404 si no.

### Pipeline de middlewares
- **Global** (`app/Support/Kernel.php`, en orden): `CorsMiddleware` →
  `RequestSizeLimitMiddleware` → `SecurityHeadersMiddleware` → `RequestLoggerMiddleware`.
- **Por ruta** (orden típico): `AuthMiddleware` → `TenantMiddleware` →
  (`PermissionMiddleware:key` RBAC | `ScopeMiddleware:scope` API-key) → `AdminMiddleware`.

### Autenticación multi-esquema (Fase 1, implementada)
- `AuthMiddleware` ya **no** depende de `PDO` directo: itera guards
  `[JwtGuard, ApiKeyGuard]`, produce un `Principal` unificado y, por
  retrocompatibilidad, sigue llamando `$request->setUser($principal->claims)` (lo que
  leen `TenantMiddleware` y `PermissionMiddleware`). También `$request->setPrincipal()`.
- `GuardInterface` (`App\Support\Contracts`): `authenticate(Request): ?Principal`
  (null = no es su esquema → siguiente guard; throw = su esquema pero inválido).
- `App\Support\Auth\JwtGuard`: JWT firebase (`JWTConfig::decodeToken`) + **revocación por
  columna `usuarios.token`**. Scopes `['*']` (la app propia; el RBAC sigue gobernando).
- `App\Support\Auth\ApiKeyGuard`: credencial en `Authorization: Bearer mk_...` o header
  `X-Api-Key`, verificada por `ApiKeyManager`.
- `App\Support\Auth\Principal`: `type ('user'|'api_key')`, `tenantId`, `userId`, `scopes`,
  `rol`, `claims`. `hasScope()` honra `'*'`.
- `App\Support\Auth\ApiKeyManager`: emite/verifica/revoca. Token =
  `mk_<env>_<id>_<secret>`; en DB solo `prefix` (`mk_<env>_<id>`, indexado) + `hash`
  (`sha256(secret)`). `verify()` compara en tiempo constante (`hash_equals`), respeta
  `revoked_at`/`expires_at`. (NO es `final` — para mocking en tests).
- `App\Http\Middleware\ScopeMiddleware:<scope>`: exige scope del Principal. Ortogonal a
  RBAC (rol de usuario) y a entitlements (futuro, del tenant).

### Multi-tenancy
- `tenant_id CHAR(36)` (UUID v4 **generado en PHP**, no en DB) en `tenants`, `usuarios`,
  `clientes`, `api_keys`. El JWT lleva el claim `tenant_id`. `TenantMiddleware` valida que
  exista y setea `$request->tenantId()`. Los repositorios **filtran por `tenant_id`** en
  cada query.

### Capas por módulo
`Controller` (HTTP, devuelve `Response`) → `Service` (lógica) → `Repository` (PDO/SQL).
`FormRequest` valida en el constructor vía `Validator` (16 reglas: required, email, min,
max, integer, boolean, in, numeric, confirmed, string, array, url, date, regex, uuid,
trim, nullable). Excepciones → HTTP por `App\Exceptions\Handler` (base `AppException` con
`getHttpStatusCode()` + `toArray()` JSON).

### Otros subsistemas del chasis (`app/Support`)
`Config`, `DB`, `EventDispatcher` (síncrono), `JobDispatcher` + `Job` (cola DB-backed,
tabla `jobs`, worker `queue:work`), `Logger` + `LogReader` (file/stderr), `RateLimiter`
(APCu, 5 intentos/5 min en login → `RateLimitException` 429), `Roles`, `UUIDGenerator`,
`Pipeline`, `Kernel`, `ServiceProvider` base.

## Módulos existentes (`app/Modules`)

| Módulo | Endpoints (prefijo) | Middlewares | Notas |
|---|---|---|---|
| **Admin** | `/admin/roles*`, `/admin/permisos*`, `/admin/users`, `/admin/impersonate` | Auth + Admin (+Tenant en algunos) | gestión RBAC |
| **ApiKeys** *(Fase 1.b, nuevo)* | `/api-keys` CRUD | Auth + Tenant + `Scope:apikeys.manage` | gestión de API keys por tenant |
| **Auth** | `/auth/register|login|refresh|logout|me|permisos/{key}|impersonate` | mixto | JWT + refresh tokens |
| **Cliente** | `/clientes` CRUD | Auth + Tenant | **`create()`/`update()` son stubs** (`RuntimeException`, TODO sin implementar) |
| **Tenant** | `/tenants` CRUD | Auth + Admin | gestión de tenants |
| **Usuario** | usuarios CRUD | (Auth) | — |
| ~~IA~~ | — | — | **removido del base**; ahora add-on opcional `cynchro/modux-ia` |

Infra (no-módulo): `GET /`, `GET /health` (200 ok / 503 degraded según DB),
`/admin/logs*`.

## Cómo se comunican

- **Vía Container DI**: todos comparten el `PDO` singleton y servicios registrados.
- **Los módulos NO se referencian entre sí** directamente; el aislamiento es por
  Container + filtro `tenant_id`.
- **Vía `Request`**: `AuthMiddleware` setea `Principal` + `user()`; los middlewares
  downstream (`Tenant`, `Permission`, `Scope`) y los controllers leen de ahí
  (`$request->tenantId()`, `$request->user()`, `$request->principal()`).
- **Auto-discovery**: agregar `app/Modules/X/routes.php` (+ opcional `ServiceProvider.php`)
  es suficiente para registrar un módulo; no hay registro manual.
- **(Futuro, per ADR 0001)**: los módulos opcionales (billing) escribirán
  `tenant_entitlements`; los módulos de producto leerán vía un gate del base sin conocer
  billing.

## Decisiones implícitas importantes

- **Raw PDO, sin ORM** — control total de cada query (confirmado en README).
- **Constructor injection explícito**, sin facades ni service locator.
- **`tenant_id` = UUID v4 generado en PHP** (patrón visto en `AdminSeeder` y
  `ApiKeyManager`), no `DEFAULT (UUID())` salvo la tabla `tenants`.
- **Revocación de JWT por DB**: el token se guarda en `usuarios.token`; logout/revocación
  lo invalida aunque el `exp` siga vigente.
- **PHPStan con baseline** para deuda preexistente (92 entradas, casi todas
  `missingType.iterableValue` + el falso positivo `$router might not be defined` de los
  `routes.php`, que usan `$router` global inyectado por bootstrap). Nivel 6.
- **IA y la futura capa de billing = add-ons opcionales vía Packagist**, nunca en el
  chasis (mantiene las ~5 deps).
- **Migraciones versionadas** `NNNN_name.php` con clase anónima `up(PDO)/down(PDO)`,
  `CREATE TABLE IF NOT EXISTS`, InnoDB utf8mb4.
- *(inferido)* El repo es público y muchos lo usan → SemVer estricto; los contratos del
  base (p. ej. el futuro `EntitlementResolverInterface`) son API pública.
- *(inferido)* `config/*` leen exclusivamente de `$_ENV` con defaults; no hay otra fuente
  de configuración.
