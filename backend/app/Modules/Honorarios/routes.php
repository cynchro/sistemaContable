<?php

use App\Modules\Honorarios\Controllers\ServicioController;
use App\Modules\Honorarios\Controllers\FactorComplejidadController;
use App\Modules\Honorarios\Controllers\HonorarioController;
use App\Http\Middleware\AuthMiddleware;
use App\Http\Middleware\TenantMiddleware;

/** @var \App\Support\Router $router (inyectado por bootstrap/app.php al cargar las rutas) */
$router->group([AuthMiddleware::class, TenantMiddleware::class], function ($router) {
    // Catálogo de servicios (por tenant)
    $router->get('/servicios', [ServicioController::class, 'index']);
    $router->post('/servicios', [ServicioController::class, 'create']);
    $router->get('/servicios/{id}', [ServicioController::class, 'show']);
    $router->put('/servicios/{id}', [ServicioController::class, 'update']);
    $router->delete('/servicios/{id}', [ServicioController::class, 'delete']);

    // Factores de complejidad (por tenant)
    $router->get('/factores-complejidad', [FactorComplejidadController::class, 'index']);
    $router->post('/factores-complejidad', [FactorComplejidadController::class, 'create']);
    $router->get('/factores-complejidad/{id}', [FactorComplejidadController::class, 'show']);
    $router->put('/factores-complejidad/{id}', [FactorComplejidadController::class, 'update']);
    $router->delete('/factores-complejidad/{id}', [FactorComplejidadController::class, 'delete']);

    // Honorarios (documento por empresa = contribuyente)
    $router->get('/empresas/{empresaId}/honorarios', [HonorarioController::class, 'index']);
    $router->post('/empresas/{empresaId}/honorarios', [HonorarioController::class, 'create']);
    $router->get('/empresas/{empresaId}/honorarios/{id}', [HonorarioController::class, 'show']);
    $router->delete('/empresas/{empresaId}/honorarios/{id}', [HonorarioController::class, 'delete']);
});
