# Módulos, ruteo y secuencia de arranque


## Secuencia de arranque

`bootstrap/app.php` arranca en 9 etapas ordenadas:

| Etapa | Qué pasa |
|---|---|
| 1 | Carga `.env`, valida las variables requeridas |
| 2 | Setea la ruta de config |
| 3 | Setea el error reporting, desactiva display_errors |
| 4 | Construye el Container PSR-11 |
| 5 | Registra el singleton del Logger + el manejador global de excepciones |
| 6 | Registra los singletons PDO, DB y CacheInterface (ApcuCache) |
| 7 | Registra los singletons Router + Kernel |
| 7.5 | Registra el EventDispatcher; autodescubre + arranca los ServiceProviders de los módulos |
| 8 | Autodescubre los `routes.php` de los módulos |
| 9 | Registra las rutas de infraestructura (health, logs) |

La etapa 7.5 llama `register()` y luego `boot()` en cada `app/Modules/*/ServiceProvider.php` que exista. Ahí es donde los módulos se suscriben a eventos y sobrescriben bindings del container.

---

## Crear un módulo

### Repository

```php
namespace App\Modules\Producto\Repositories;

use PDO;
use App\Exceptions\NotFoundException;

class ProductoRepository
{
    public function __construct(private PDO $pdo) {}

    /** @return list<array<string, mixed>> */
    public function findAll(): array
    {
        return $this->pdo->query('SELECT * FROM productos')
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string, mixed> */
    public function findById(int $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM productos WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new NotFoundException('Producto', $id);
        }

        return $row;
    }

    /** @return array<string, mixed> */
    public function create(array $data): array
    {
        $stmt = $this->pdo->prepare('INSERT INTO productos (nombre, precio) VALUES (?, ?)');
        $stmt->execute([$data['nombre'], $data['precio']]);
        return $this->findById((int) $this->pdo->lastInsertId());
    }
}
```

### Service

```php
namespace App\Modules\Producto\Services;

use App\Modules\Producto\Repositories\ProductoRepository;

class ProductoService
{
    public function __construct(private ProductoRepository $repository) {}

    public function getAll(): array       { return $this->repository->findAll(); }
    public function get(int $id): array   { return $this->repository->findById($id); }
    public function create(array $d): array { return $this->repository->create($d); }
}
```

### Controller

```php
namespace App\Modules\Producto\Controllers;

use App\Support\Request;
use App\Support\Response;
use App\Modules\Producto\Services\ProductoService;
use App\Modules\Producto\Requests\CreateProductoRequest;

class ProductoController
{
    public function __construct(private ProductoService $service) {}

    public function index(Request $request): Response
    {
        return Response::success($this->service->getAll());
    }

    public function show(Request $request): Response
    {
        return Response::success($this->service->get((int) $request->route('id')));
    }

    public function create(CreateProductoRequest $request): Response
    {
        return Response::success($this->service->create($request->validated()), 201);
    }
}
```

### ServiceProvider

```php
namespace App\Modules\Producto;

use App\Support\ServiceProvider;
use App\Modules\Producto\Repositories\ProductoRepository;
use App\Modules\Producto\Services\ProductoService;
use App\Modules\Producto\Controllers\ProductoController;

class ProductoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(ProductoRepository::class, fn ($c) =>
            new ProductoRepository($c->get(\PDO::class))
        );
        $this->container->singleton(ProductoService::class, fn ($c) =>
            new ProductoService($c->get(ProductoRepository::class))
        );
        $this->container->singleton(ProductoController::class, fn ($c) =>
            new ProductoController($c->get(ProductoService::class))
        );
    }

    public function boot(): void
    {
        $router = $this->container->get(\App\Support\Router::class);
        require __DIR__ . '/routes.php';
    }
}
```

---

## Ruteo

### Rutas individuales

```php
// Pública
$router->post('/auth/login', [AuthController::class, 'login']);

// Con middlewares
$router->get('/productos/{id}', [ProductoController::class, 'show'],
    [AuthMiddleware::class]);

$router->delete('/productos/{id}', [ProductoController::class, 'delete'],
    [AuthMiddleware::class, AdminMiddleware::class]);
```

### Grupos de rutas — comparten middlewares y prefijo de URI

```php
// Grupo por middleware
$router->group([AuthMiddleware::class], function ($router) {
    $router->get('/productos',      [ProductoController::class, 'index']);
    $router->post('/productos',     [ProductoController::class, 'create']);
    $router->put('/productos/{id}', [ProductoController::class, 'update']);
});

// Grupo con prefijo — a todas las rutas se les antepone /v1
$router->group([AuthMiddleware::class], '/v1', function ($router) {
    $router->get('/productos', [ProductoController::class, 'index']); // → GET /v1/productos
});

// Grupos anidados — los middlewares y prefijos se combinan, no se reemplazan
$router->group([AuthMiddleware::class], function ($router) {
    $router->group([AdminMiddleware::class], function ($router) {
        $router->get('/admin/roles', [AdminController::class, 'roles']);
    });
});

// Middleware parametrizado — chequeo de permiso RBAC
$router->delete('/productos/{id}', [ProductoController::class, 'delete'], [
    AuthMiddleware::class,
    PermissionMiddleware::class . ':productos.delete',
]);
```

Los parámetros de ruta se extraen automáticamente y están disponibles vía `$request->route('id')`.

### Inyección en controllers

El router resuelve los parámetros de los métodos del controller por tipo:

| Tipo del parámetro | Qué se inyecta |
|---|---|
| `Request` | La petición actual (con `user()`, `tenantId()`, params de ruta ya seteados) |
| Subclase de `FormRequest` | Una instancia nueva construida desde `$request->all() + routeParams()` — validada al construirse |
| Cualquier otra clase | Se resuelve desde el container |
| Escalar (sin tipo) | `$request->route($paramName)` |

**Patrón de doble parámetro** — usalo cuando necesitás tanto el contexto de la petición (tenantId, user) como un FormRequest validado:

```php
public function create(Request $request, CreateProductoRequest $validated): Response
{
    $tenantId = (string) $request->tenantId();
    return Response::success($this->service->create($validated->validated(), $tenantId), 201);
}
```

---
