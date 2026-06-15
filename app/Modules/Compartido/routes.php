<?php

use App\Modules\Compartido\Controllers\EmpresaController;
use App\Http\Middleware\AuthMiddleware;
use App\Http\Middleware\TenantMiddleware;

/** @var \App\Support\Router $router (inyectado por bootstrap/app.php al cargar las rutas) */
$router->group([AuthMiddleware::class, TenantMiddleware::class], function ($router) {
    $router->get('/empresas', [EmpresaController::class, 'index']);
    $router->get('/empresas/{id}', [EmpresaController::class, 'show']);
    $router->post('/empresas', [EmpresaController::class, 'create']);
    $router->put('/empresas/{id}', [EmpresaController::class, 'update']);
    $router->delete('/empresas/{id}', [EmpresaController::class, 'delete']);
});
