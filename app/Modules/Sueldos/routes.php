<?php

use App\Modules\Sueldos\Controllers\EmpleadoController;
use App\Http\Middleware\AuthMiddleware;
use App\Http\Middleware\TenantMiddleware;

/** @var \App\Support\Router $router (inyectado por bootstrap/app.php al cargar las rutas) */
$router->group([AuthMiddleware::class, TenantMiddleware::class], function ($router) {
    // Legajo de empleados (anidado bajo empresa)
    $router->get('/empresas/{empresaId}/empleados', [EmpleadoController::class, 'index']);
    $router->post('/empresas/{empresaId}/empleados', [EmpleadoController::class, 'create']);
    $router->get('/empresas/{empresaId}/empleados/{id}', [EmpleadoController::class, 'show']);
    $router->put('/empresas/{empresaId}/empleados/{id}', [EmpleadoController::class, 'update']);
    $router->delete('/empresas/{empresaId}/empleados/{id}', [EmpleadoController::class, 'delete']);
});
