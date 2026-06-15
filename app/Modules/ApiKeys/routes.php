<?php

use App\Modules\ApiKeys\Controllers\ApiKeyController;
use App\Http\Middleware\AuthMiddleware;
use App\Http\Middleware\TenantMiddleware;
use App\Http\Middleware\ScopeMiddleware;

// Gestión de las API keys del tenant. Protegida por el scope 'apikeys.manage':
// los usuarios de la app (scope '*') pasan transparentemente; una API key sólo
// puede administrar otras si se le concedió ese scope explícitamente.
/** @var \App\Support\Router $router (inyectado por bootstrap/app.php al cargar las rutas) */
$router->group(
    [AuthMiddleware::class, TenantMiddleware::class, ScopeMiddleware::class . ':apikeys.manage'],
    function ($router) {
        $router->get('/api-keys', [ApiKeyController::class, 'index']);
        $router->post('/api-keys', [ApiKeyController::class, 'create']);
        $router->get('/api-keys/{id}', [ApiKeyController::class, 'show']);
        $router->delete('/api-keys/{id}', [ApiKeyController::class, 'revoke']);
    }
);
