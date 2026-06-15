# HTTP — Request, Response, validación y middleware


## API de Request

```php
// Input — prioridad: params de ruta > body JSON > POST > GET
$request->input('key');
$request->input('key', 'default');
$request->all();                   // todos los inputs combinados
$request->only(['campo1', 'campo2']);
$request->except(['_token']);

// Parámetros de ruta (de los segmentos de URI como {id})
$request->route('id');

// Metadata HTTP
$request->method();                // 'GET', 'POST', etc.
$request->uri();                   // '/path/only' (sin query string)
$request->header('X-Custom');
$request->bearerToken();           // lo extrae de Authorization: Bearer <token>
$request->ip();                    // IP del cliente, respeta proxies de confianza

// Contexto seteado por middleware
$request->user();                  // payload (array) del JWT (lo setea AuthMiddleware)
$request->tenantId();              // string (lo setea TenantMiddleware)

// Chequeos de tipo
$request->isJson();                // true si Content-Type: application/json

// Acceso mágico por propiedad
$request->nombre;                  // equivalente a $request->input('nombre')
```

---

## API de Response

Response es inmutable — cada método devuelve una instancia nueva.

```php
// Respuestas de éxito
Response::success($data);           // 200
Response::success($data, 201);      // 201 Created

// Respuestas de error
Response::error('Not allowed.', 403);

// Redirect
Response::redirect('/new-path', 302);

// Patrón builder (inmutable — cada método devuelve una instancia nueva)
(new Response())
    ->withStatus(200)
    ->withHeader('X-Custom', 'value')
    ->json(['key' => 'value']);

// Inspeccionar sin enviar
$response->getStatus();            // int
$response->getHeaders();           // array<string, string>

// Enviar (lo llama el Kernel una vez)
$response->send();
```

Forma de una respuesta de éxito:

```json
{ "success": true, "data": { ... } }
```

Forma de una respuesta de error (de excepciones tipadas):

```json
{ "success": false, "message": "Not found." }
```

Forma de un error de validación:

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

## Validación de requests

Extendé `FormRequest` — la validación corre al construirse y lanza `ValidationException` (HTTP 422) automáticamente.

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
// Body de la petición: {"nombre":"Mesa","precio":150,"admin":true}
// Reglas: {nombre, precio}

$request->all()        // {"nombre":"Mesa","precio":150,"admin":true}
$request->validated()  // {"nombre":"Mesa","precio":150}  ← solo los campos declarados
```

Usá siempre `validated()` en la lógica de negocio — previene el mass-assignment por diseño.

### Reglas de validación

| Regla | Ejemplo | Descripción |
|---|---|---|
| `required` | `required` | Presente y no vacío |
| `email` | `email` | Formato de email válido |
| `min:N` | `min:6` | Largo mínimo del string (multibyte-aware) |
| `max:N` | `max:255` | Largo máximo del string (multibyte-aware) |
| `integer` | `integer` | Debe ser un valor entero |
| `numeric` | `numeric` | Debe ser numérico (int o float) |
| `boolean` | `boolean` | `true`, `false`, `0`, `1`, `'0'`, `'1'` |
| `string` | `string` | Debe ser de tipo string de PHP |
| `array` | `array` | Debe ser de tipo array de PHP |
| `in:a,b,c` | `in:admin,user` | Debe ser uno de los valores listados |
| `url` | `url` | URL válida (`filter_var FILTER_VALIDATE_URL`) |
| `date` | `date` | Fecha válida en formato `Y-m-d` (por defecto) |
| `date:format` | `date:d/m/Y` | Fecha válida en un formato custom |
| `regex:/pattern/` | `regex:/^\d{4}$/` | Coincide con la expresión regular dada |
| `uuid` | `uuid` | Formato UUID v4 válido |
| `confirmed` | `confirmed` | Coincide con el campo hermano `{field}_confirmation` |
| `nullable` | `nullable` | Saltea todas las reglas si el campo está ausente o es string vacío |

Las reglas se componen con `|`:

```php
'email' => 'required|email|max:255',
'rol'   => 'nullable|in:1,2,3',
```

---

## Excepciones → respuestas HTTP

Lanzá una excepción tipada en cualquier lado — el manejador global la convierte a JSON automáticamente.

```php
throw new AuthException('Invalid credentials.');       // 401
throw new ForbiddenException('Admin only.');           // 403
throw new NotFoundException('Producto', $id);          // 404
throw new ValidationException(['campo' => ['msg']]);   // 422
throw new RateLimitException('Too many attempts.');    // 429
throw new DatabaseException('Query failed.');          // 500 (mensaje oculto en prod)
```

| Excepción | HTTP | Notas |
|---|---|---|
| `AuthException` | 401 | Token inválido/ausente/revocado |
| `ForbiddenException` | 403 | Autenticado pero no autorizado |
| `NotFoundException` | 404 | Recurso o ruta no encontrada |
| `MethodNotAllowedException` | 405 | Path correcto, método HTTP incorrecto |
| `ValidationException` | 422 | Lleva un array campo → mensajes |
| `RateLimitException` | 429 | Demasiados intentos de login |
| `DatabaseException` | 500 | Errores de DB; el mensaje se oculta con `APP_DEBUG=false` |

Todas las excepciones extienden `AppException`. Un `Throwable` no manejado devuelve 500 con el detalle de la excepción oculto en producción.

---

## Middleware

| Middleware | Se aplica | Efecto |
|---|---|---|
| `CorsMiddleware` | Todas las peticiones | Headers CORS; maneja el preflight OPTIONS |
| `RequestSizeLimitMiddleware` | Todas las peticiones | Rechaza bodies por encima de `app.max_request_size` (2 MB por defecto) |
| `SecurityHeadersMiddleware` | Todas las peticiones | X-Frame-Options, X-Content-Type-Options, Referrer-Policy, etc. |
| `RequestLoggerMiddleware` | Todas las peticiones | Entrada de log JSON estructurada: método, URI, status, duración |
| `AuthMiddleware` | Rutas protegidas | Decodifica el JWT, valida que el token no esté revocado, setea `$request->user()` |
| `AdminMiddleware` | Rutas de admin | Requiere `user['rol'] === 1`, lanza 403 si no |
| `TenantMiddleware` | Rutas con scope de tenant | Lee el `tenant_id` del payload del JWT, setea `$request->tenantId()` |
| `PermissionMiddleware` | Rutas RBAC | Chequea la tabla `roles_permisos` por la clave de permiso dada (403 si no está concedido) |

El pipeline global (`CorsMiddleware → RequestSizeLimitMiddleware → SecurityHeadersMiddleware → RequestLoggerMiddleware`) corre en cada petición antes de cualquier middleware de ruta.

### Escribir un middleware

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
        // post-procesamiento acá
        return $response;
    }
}
```

---
