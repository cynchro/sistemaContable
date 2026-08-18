# Módulos, ruteo y secuencia de arranque


## Boot sequence

`bootstrap/app.php` boots in 9 ordered stages:

| Stage | What happens |
|---|---|
| 1 | Load `.env`, enforce required vars |
| 2 | Set config path |
| 3 | Set error reporting, disable display_errors |
| 4 | Build PSR-11 Container |
| 5 | Register Logger singleton + global exception handler |
| 6 | Register PDO, DB, and CacheInterface (ApcuCache) singletons |
| 7 | Register Router + Kernel singletons |
| 7.5 | Register EventDispatcher; auto-discover + boot module ServiceProviders |
| 8 | Auto-discover module `routes.php` files |
| 9 | Register infrastructure routes (health, logs) |

Stage 7.5 calls `register()` then `boot()` on every `app/Modules/*/ServiceProvider.php` that exists. This is where modules subscribe to events and override container bindings.

---

## Creating a module

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

## Routing

### Individual routes

```php
// Public
$router->post('/auth/login', [AuthController::class, 'login']);

// With middlewares
$router->get('/productos/{id}', [ProductoController::class, 'show'],
    [AuthMiddleware::class]);

$router->delete('/productos/{id}', [ProductoController::class, 'delete'],
    [AuthMiddleware::class, AdminMiddleware::class]);
```

### Route groups — share middlewares and URI prefix

```php
// Middleware group
$router->group([AuthMiddleware::class], function ($router) {
    $router->get('/productos',      [ProductoController::class, 'index']);
    $router->post('/productos',     [ProductoController::class, 'create']);
    $router->put('/productos/{id}', [ProductoController::class, 'update']);
});

// Prefix group — all routes get /v1 prepended
$router->group([AuthMiddleware::class], '/v1', function ($router) {
    $router->get('/productos', [ProductoController::class, 'index']); // → GET /v1/productos
});

// Nested groups — middlewares and prefixes are merged, not replaced
$router->group([AuthMiddleware::class], function ($router) {
    $router->group([AdminMiddleware::class], function ($router) {
        $router->get('/admin/roles', [AdminController::class, 'roles']);
    });
});

// Parameterized middleware — RBAC permission check
$router->delete('/productos/{id}', [ProductoController::class, 'delete'], [
    AuthMiddleware::class,
    PermissionMiddleware::class . ':productos.delete',
]);
```

Route parameters are extracted automatically and available via `$request->route('id')`.

### Controller injection

The router resolves controller method parameters by type:

| Parameter type | What gets injected |
|---|---|
| `Request` | The current request (with `user()`, `tenantId()`, route params already set) |
| Subclass of `FormRequest` | A new instance constructed from `$request->all() + routeParams()` — validated on construction |
| Any other class | Resolved from the container |
| Scalar (untyped) | `$request->route($paramName)` |

**Dual-parameter pattern** — use when you need both the request context (tenantId, user) and a validated FormRequest:

```php
public function create(Request $request, CreateProductoRequest $validated): Response
{
    $tenantId = (string) $request->tenantId();
    return Response::success($this->service->create($validated->validated(), $tenantId), 201);
}
```

---

