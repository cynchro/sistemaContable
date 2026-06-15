# HTTP — Request, Response, validación y middleware


## Request API

```php
// Input — priority: route params > JSON body > POST > GET
$request->input('key');
$request->input('key', 'default');
$request->all();                   // all merged inputs
$request->only(['campo1', 'campo2']);
$request->except(['_token']);

// Route parameters (from URI segments like {id})
$request->route('id');

// HTTP metadata
$request->method();                // 'GET', 'POST', etc.
$request->uri();                   // '/path/only' (no query string)
$request->header('X-Custom');
$request->bearerToken();           // extracts from Authorization: Bearer <token>
$request->ip();                    // client IP, respects trusted proxies

// Middleware-set context
$request->user();                  // array payload from JWT (set by AuthMiddleware)
$request->tenantId();              // string (set by TenantMiddleware)

// Type checks
$request->isJson();                // true if Content-Type: application/json

// Magic property access
$request->nombre;                  // same as $request->input('nombre')
```

---

## Response API

Response is immutable — every method returns a new instance.

```php
// Success responses
Response::success($data);           // 200
Response::success($data, 201);      // 201 Created

// Error responses
Response::error('Not allowed.', 403);

// Redirect
Response::redirect('/new-path', 302);

// Builder pattern (immutable — each method returns a new instance)
(new Response())
    ->withStatus(200)
    ->withHeader('X-Custom', 'value')
    ->json(['key' => 'value']);

// Inspect without sending
$response->getStatus();            // int
$response->getHeaders();           // array<string, string>

// Send (called once by Kernel)
$response->send();
```

Success response shape:

```json
{ "success": true, "data": { ... } }
```

Error response shape (from typed exceptions):

```json
{ "success": false, "message": "Not found." }
```

Validation error shape:

```json
{
  "success": false,
  "message": "Validation failed.",
  "errors": {
    "email": ["email is required.", "email must be a valid email address."],
    "precio": ["precio must be an integer."]
  }
}
```

---

## Request validation

Extend `FormRequest` — validation runs on construction and throws `ValidationException` (HTTP 422) automatically.

```php
namespace App\Modules\Producto\Requests;

use App\Support\FormRequest;

class CreateProductoRequest extends FormRequest
{
    protected function rules(): array
    {
        return [
            'nombre'    => 'required|min:2|max:100',
            'precio'    => 'required|integer',
            'activo'    => 'boolean',
            'tipo'      => 'required|in:fisico,digital',
            'url_foto'  => 'nullable|url',
            'sku'       => 'nullable|regex:/^[A-Z]{2}-\d{4}$/',
            'lanzado'   => 'nullable|date',
            'ext_id'    => 'nullable|uuid',
        ];
    }
}
```

### `all()` vs `validated()`

```php
// Request body: {"nombre":"Mesa","precio":150,"admin":true}
// Rules: {nombre, precio}

$request->all()        // {"nombre":"Mesa","precio":150,"admin":true}
$request->validated()  // {"nombre":"Mesa","precio":150}  ← only declared fields
```

Always use `validated()` in business logic — it prevents mass-assignment by design.

### Validation rules

| Rule | Example | Description |
|---|---|---|
| `required` | `required` | Present and non-empty |
| `email` | `email` | Valid email format |
| `min:N` | `min:6` | Minimum string length (multibyte-aware) |
| `max:N` | `max:255` | Maximum string length (multibyte-aware) |
| `integer` | `integer` | Must be an integer value |
| `numeric` | `numeric` | Must be numeric (int or float) |
| `boolean` | `boolean` | `true`, `false`, `0`, `1`, `'0'`, `'1'` |
| `string` | `string` | Must be a PHP string type |
| `array` | `array` | Must be a PHP array type |
| `in:a,b,c` | `in:admin,user` | Must be one of the listed values |
| `url` | `url` | Valid URL (`filter_var FILTER_VALIDATE_URL`) |
| `date` | `date` | Valid date in `Y-m-d` format (default) |
| `date:format` | `date:d/m/Y` | Valid date in custom format |
| `regex:/pattern/` | `regex:/^\d{4}$/` | Matches the given regular expression |
| `uuid` | `uuid` | Valid UUID v4 format |
| `confirmed` | `confirmed` | Matches `{field}_confirmation` sibling |
| `nullable` | `nullable` | Skip all rules if field is absent or empty string |

Rules are composable with `|`:

```php
'email' => 'required|email|max:255',
'rol'   => 'nullable|in:1,2,3',
```

---

## Exceptions → HTTP responses

Throw a typed exception anywhere — the global handler converts it to JSON automatically.

```php
throw new AuthException('Invalid credentials.');       // 401
throw new ForbiddenException('Admin only.');           // 403
throw new NotFoundException('Producto', $id);          // 404
throw new ValidationException(['campo' => ['msg']]);   // 422
throw new RateLimitException('Too many attempts.');    // 429
throw new DatabaseException('Query failed.');          // 500 (message hidden in prod)
```

| Exception | HTTP | Notes |
|---|---|---|
| `AuthException` | 401 | Invalid/missing/revoked token |
| `ForbiddenException` | 403 | Authenticated but not authorized |
| `NotFoundException` | 404 | Resource or route not found |
| `MethodNotAllowedException` | 405 | Right path, wrong HTTP method |
| `ValidationException` | 422 | Carries a field → messages array |
| `RateLimitException` | 429 | Too many login attempts |
| `DatabaseException` | 500 | DB errors; message hidden when `APP_DEBUG=false` |

All exceptions extend `AppException`. Unhandled `Throwable` returns 500 with the exception detail hidden in production.

---

## Middleware

| Middleware | Applied | Effect |
|---|---|---|
| `CorsMiddleware` | All requests | CORS headers; handles OPTIONS preflight |
| `RequestSizeLimitMiddleware` | All requests | Rejects bodies over `app.max_request_size` (default 2 MB) |
| `SecurityHeadersMiddleware` | All requests | X-Frame-Options, X-Content-Type-Options, Referrer-Policy, etc. |
| `RequestLoggerMiddleware` | All requests | Structured JSON log entry: method, URI, status, duration |
| `AuthMiddleware` | Protected routes | Decodes JWT, validates token is not revoked, sets `$request->user()` |
| `AdminMiddleware` | Admin routes | Requires `user['rol'] === 1`, throws 403 otherwise |
| `TenantMiddleware` | Tenant-scoped routes | Reads `tenant_id` from JWT payload, sets `$request->tenantId()` |
| `PermissionMiddleware` | RBAC routes | Checks `roles_permisos` table for the given permission key (403 if not granted) |

The global pipeline (`CorsMiddleware → RequestSizeLimitMiddleware → SecurityHeadersMiddleware → RequestLoggerMiddleware`) runs on every request before any route middleware.

### Writing a middleware

```php
namespace App\Http\Middleware;

use App\Support\Request;
use App\Support\Response;
use App\Support\Contracts\MiddlewareInterface;

class AuditMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        $response = $next($request);
        // post-processing here
        return $response;
    }
}
```

---

