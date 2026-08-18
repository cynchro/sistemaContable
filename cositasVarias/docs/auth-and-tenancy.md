# Auth, API keys, webhooks, DI y multi-tenancy


## Autenticación

### Login

```
POST /auth/login
Content-Type: application/json

{"usuario": "email@example.com", "clave": "password"}
```

Devuelve `access_token` (JWT) y `refresh_token` (opaco, guardado en DB).

El payload del JWT contiene `sub` (ID de usuario), `tenant_id` y expiración. TTL por defecto: 86400 segundos (configurable vía `JWT_TTL`).

### Refresh de token

```
POST /auth/refresh
Content-Type: application/json

{"refresh_token": "a8f3c1d9..."}
```

Emite un nuevo par `access_token` + `refresh_token`. El refresh token viejo se borra de inmediato (rotación — cada token es de un solo uso).

### Logout

```
POST /auth/logout
Authorization: Bearer <access_token>
Content-Type: application/json

{"refresh_token": "a8f3c1d9..."}   ← opcional, también invalida el refresh token
```

### Impersonación (solo admin)

```
POST /auth/impersonate
Authorization: Bearer <admin_access_token>
Content-Type: application/json

{"target_id": 42}
```

- Requiere `AuthMiddleware + AdminMiddleware + TenantMiddleware`
- El admin solo puede suplantar usuarios dentro de su propio tenant
- Devuelve un JWT firmado como el usuario objetivo

### Rate limiting

Los intentos de login se trackean por usuario usando APCu. Tras 5 intentos fallidos la cuenta queda bloqueada 5 minutos (`RateLimitException` → 429). Si APCu no está instalado, el rate limiting se saltea silenciosamente.

### Petición autenticada

```
GET /any-protected-route
Authorization: Bearer <access_token>
```

`AuthMiddleware` resuelve la petición a través de **guards** (JWT primero, luego API
key) y produce un `Principal` unificado. Por retrocompatibilidad sigue llamando
`$request->setUser($payload)`, así que `$request->user()`,
`TenantMiddleware` y `PermissionMiddleware` funcionan sin cambios. Usá
`$request->principal()` para leer el tipo de auth, el tenant y los scopes.

### API keys (auth de terceros)

Para acceso server-to-server por desarrolladores externos, las mismas rutas protegidas
aceptan una **API key** en vez de un JWT de usuario — sin cambiar el código de la ruta:

```
GET /any-protected-route
Authorization: Bearer mk_live_<id>_<secret>     # o: X-Api-Key: mk_live_...
```

Las keys se emiten con `App\Support\Auth\ApiKeyManager::issue($tenantId, $name, $scopes)`,
que devuelve el token **una sola vez** (solo se guardan `prefix` + un `hash` SHA-256).

Los tenants gestionan sus propias keys con el módulo `ApiKeys` incluido (CRUD):

```
POST   /api-keys          { "name": "...", "scopes": ["clientes.read"] }  → 201, token visible una vez
GET    /api-keys                                                          → lista (nunca expone el hash)
GET    /api-keys/{id}                                                     → metadata de una key
DELETE /api-keys/{id}                                                     → revocar
```

Estas rutas requieren `AuthMiddleware + TenantMiddleware + ScopeMiddleware:apikeys.manage`,
así que los usuarios de la app (scope `*`) gestionan keys de forma transparente, mientras que
una API key solo puede administrar otras si se le concedió explícitamente `apikeys.manage`
(previene la escalada de privilegios). Toda operación queda acotada al tenant del que llama.

La key lleva su tenant y una lista de **scopes**; protegé por ellos en cada ruta
con el middleware parametrizado:

```php
$router->get('/clientes', [ClienteController::class, 'index'],
    [AuthMiddleware::class, TenantMiddleware::class, 'App\Http\Middleware\ScopeMiddleware:clientes.read']);
```

Los scopes (qué puede tocar una credencial) son ortogonales a los permisos RBAC (qué puede
hacer un rol de usuario) y a los entitlements del tenant (qué tiene un tenant) — ver
`docs/adr/0001-saas-identity-entitlements-billing.md`.

### Firmas de webhooks

`App\Support\Webhook\WebhookVerifier` (vinculado a `WebhookVerifierInterface`) endurece los
webhooks entrantes/salientes con un esquema propio sin dependencias:

```
X-Signature: t=<unix_ts>,v1=<hex_hmac_sha256>
signature  = HMAC-SHA256("<ts>.<rawBody>", secret)
```

`verify($request, $secret, $tolerance = 300)` devuelve true solo si el HMAC coincide
(en tiempo constante), el timestamp está dentro de la ventana **y** la firma no se vio
antes (anti-replay vía `CacheInterface`, TTL = ventana). `sign($payload, $secret)`
produce la cabecera para webhooks salientes. Inyectá la interfaz en cualquier controller que
reciba callbacks de proveedores (p. ej. pasarelas de pago) y verificá contra el secret de esa
integración antes de actuar. La lectura del body crudo usa `Request::rawBody()`.

**El anti-replay exige un store operativo.** El nonce vive en `CacheInterface`, cuyo binding
por defecto es `ApcuCache`. Si el cache no es operativo (`available() === false`, p. ej. APCu
deshabilitado), `verify()` **falla cerrado** — rechaza la firma en vez de aceptarla sin
protección — y se loguea un aviso al bootear. Además, **APCu es por proceso**: en un deploy
multi-instancia, vinculá `CacheInterface` a un store compartido (Redis/DB) implementando la
interfaz, o un reenvío podría no detectarse al caer en otra instancia. El esquema HMAC no
cambia. Ver `docs/adr/0001-saas-identity-entitlements-billing.md` §1.3.

---

## Roles y permisos (RBAC)

Además del multi-tenancy, el framework trae un RBAC por roles con permisos
granulares, niveles de acceso, gate de admin y jerarquía de roles.

### Modelo de datos

| Tabla            | Rol                                                         |
|------------------|------------------------------------------------------------|
| `roles`          | roles del sistema; `parent_id` opcional para herencia      |
| `permisos`       | permisos con `key` (identificador) y `descripcion`         |
| `roles_permisos` | pivote rol↔permiso; `estado` = nivel concedido             |
| `usuarios.rol`   | FK al rol del usuario                                      |

El `estado` del pivote codifica el **nivel de acceso**: `0` sin permiso,
`1` lectura, `2` lectura-escritura.

### Proteger rutas por permiso

`PermissionMiddleware` exige que el rol del usuario tenga un permiso con al
menos el nivel requerido. El nivel se indica en el spec de la ruta:

```php
// Exige el permiso 'facturas' en nivel lectura (estado >= 1)
$router->get('/facturas', [FacturaController::class, 'index'],
    [AuthMiddleware::class, PermissionMiddleware::class . ':facturas']);

// Exige nivel escritura (estado = 2)
$router->post('/facturas', [FacturaController::class, 'store'],
    [AuthMiddleware::class, PermissionMiddleware::class . ':facturas:write']);
```

Un rol con `facturas` en nivel lectura pasa el GET pero recibe **403** en el
POST. Sin la relación rol↔permiso, ambos dan 403.

> `PermissionMiddleware` no viene cableado en ninguna ruta por defecto: la
> taxonomía de permisos la define cada aplicación (crea las `key`, las siembra
> y protege sus rutas). El framework provee el mecanismo, no las claves.

### PermissionChecker

`App\Support\Auth\PermissionChecker` es la única fuente de verdad de la
resolución rol→permiso. Inyectalo donde necesites comprobar permisos fuera de
una ruta:

```php
$checker->level($rolId, 'facturas');             // 0 | 1 | 2 (nivel efectivo)
$checker->allows($rolId, 'facturas', PermissionChecker::LEVEL_WRITE); // bool
```

### Gate de admin

`AdminMiddleware` no compara contra un id de rol fijo: considera admin a
cualquier rol que tenga el super-permiso `Roles::SUPER_PERMISSION`
(`'Acceso Total'`) en la BD. La impersonación usa el mismo criterio. Para volver
admin a un rol basta con asignarle ese permiso.

### Jerarquía de roles

Un rol puede tener un rol padre (`roles.parent_id`) y **hereda los permisos de
toda su cadena de ancestros**. El nivel efectivo de un permiso es el máximo
entre el propio rol y sus padres (si el padre tiene `facturas` en escritura, el
hijo también, salvo que el hijo defina uno mayor).

Asigná el padre al crear/actualizar un rol vía el módulo `Admin`:

```
POST /admin/roles          { "nombre": "Analista", "parent_id": 3 }
PUT  /admin/roles/{id}     { "nombre": "Analista", "estado": 1, "parent_id": 3 }
```

Asignar como padre el propio rol o uno de sus descendientes cerraría un ciclo;
el servicio lo detecta y responde **422** (`ValidationException`) sin persistir.

### Gestión de roles y permisos

El módulo `Admin` (rutas bajo `AuthMiddleware + AdminMiddleware`) expone el CRUD:

```
GET/POST/PUT  /admin/roles               gestionar roles (incluye parent_id)
GET/POST/PUT  /admin/permisos            gestionar permisos (key, descripcion, estado)
POST/DELETE   /admin/roles/{id}/assign   asignar / quitar permisos a un rol
```

---

## Container de DI

Compatible con PSR-11, con autowiring por reflexión.

```php
// Registrar un factory
$app->bind(MyService::class, fn ($c) => new MyService($c->get(PDO::class)));

// Registrar un singleton (se resuelve una vez, se reutiliza)
$app->singleton(MyService::class, fn ($c) => new MyService($c->get(PDO::class)));

// Registrar una instancia ya construida
$app->instance(\PDO::class, $existingPdo);

// Resolver
$service = $app->get(MyService::class);

// Autowiring sin registro (usa reflexión)
$service = $app->make(MyService::class);

// Autowiring + inyectar escalares extra en parámetros builtin del constructor
// (PermissionMiddleware: el PermissionChecker se autowirea; 'facturas' y 'write'
//  son los escalares de permiso y nivel)
$middleware = $app->makeWith(PermissionMiddleware::class, 'facturas', 'write');
```

El autowiring resuelve los parámetros del constructor por nombre de tipo. Si no hay binding para un tipo, resuelve la clase recursivamente. Los parámetros escalares sin default lanzan `ContainerException`. `makeWith` inyecta escalares adicionales de forma posicional en los parámetros de tipo builtin — lo usa internamente el Router para los middlewares parametrizados.

---

## Multi-tenancy

El framework trae multi-tenancy a nivel de fila. Es **opt-in** — si no incluís `TenantMiddleware` en una ruta, el tenantId nunca se setea y no hay scoping por tenant.

### Cómo funciona

1. La tabla `usuarios` tiene una columna `tenant_id CHAR(36)` (FK → `tenants.id`)
2. En el login, el `tenant_id` se embebe en el payload del JWT
3. `TenantMiddleware` lee el `tenant_id` del JWT decodificado y llama `$request->setTenantId()`
4. Los controllers leen `$request->tenantId()` y lo pasan a los repositorios
5. Los repositorios agregan `AND tenant_id = ?` a sus queries cuando `$tenantId !== null`

```php
// Ruta — agregá TenantMiddleware para habilitar el scoping
$router->group([AuthMiddleware::class, TenantMiddleware::class], function ($router) {
    $router->get('/productos', [ProductoController::class, 'index']);
});
```

```php
// Controller
public function index(Request $request): Response
{
    return Response::success($this->service->getAllForTenant($request->tenantId()));
}
```

```php
// Repository — scoping condicional
public function findAll(?string $tenantId = null): array
{
    $sql    = 'SELECT * FROM productos';
    $params = [];

    if ($tenantId !== null) {
        $sql    .= ' WHERE tenant_id = ?';
        $params[] = $tenantId;
    }

    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
```

### Correr sin multi-tenancy

Simplemente no agregues `TenantMiddleware` a ninguna ruta. La columna `tenant_id` en `usuarios` se puede omitir. Los repositorios reciben `null` y saltean el filtro de tenant. No hace falta ningún otro cambio.

### Impersonación de admin entre tenants

Un admin solo puede suplantar usuarios dentro de su propio tenant. Intentar una suplantación cross-tenant lanza `AuthException(403)`. Pasar `$adminTenantId = null` saltea este chequeo (uso interno solamente — la ruta siempre pasa el tenant ID real vía `TenantMiddleware`).

---
