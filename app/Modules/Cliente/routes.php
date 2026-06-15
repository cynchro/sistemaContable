<?php

use App\Modules\Cliente\Controllers\ClienteController;
use App\Http\Middleware\AuthMiddleware;
use App\Http\Middleware\TenantMiddleware;

/** @var \App\Support\Router $router (inyectado por bootstrap/app.php al cargar las rutas) */
$router->group([AuthMiddleware::class, TenantMiddleware::class], function ($router) {
    $router->get('/clientes', [ClienteController::class, 'index']);
    $router->get('/clientes/{id}', [ClienteController::class, 'show']);
    $router->post('/clientes', [ClienteController::class, 'create']);
    $router->put('/clientes/{id}', [ClienteController::class, 'update']);
    $router->delete('/clientes/{id}', [ClienteController::class, 'delete']);
});
