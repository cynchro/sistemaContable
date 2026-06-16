<?php

use App\Modules\Fiscal\Controllers\TributoController;
use App\Modules\Fiscal\Controllers\VencimientoController;
use App\Modules\Fiscal\Controllers\RequerimientoController;
use App\Http\Middleware\AuthMiddleware;
use App\Http\Middleware\TenantMiddleware;

/** @var \App\Support\Router $router (inyectado por bootstrap/app.php al cargar las rutas) */
$router->group([AuthMiddleware::class, TenantMiddleware::class], function ($router) {
    // Catálogo de tributos (por tenant)
    $router->get('/tributos', [TributoController::class, 'index']);
    $router->post('/tributos', [TributoController::class, 'create']);
    $router->get('/tributos/{id}', [TributoController::class, 'show']);
    $router->put('/tributos/{id}', [TributoController::class, 'update']);
    $router->delete('/tributos/{id}', [TributoController::class, 'delete']);

    // Vencimientos / obligaciones (por empresa = contribuyente)
    $router->get('/empresas/{empresaId}/vencimientos', [VencimientoController::class, 'index']);
    $router->post('/empresas/{empresaId}/vencimientos', [VencimientoController::class, 'create']);
    $router->get('/empresas/{empresaId}/vencimientos/{id}', [VencimientoController::class, 'show']);
    $router->put('/empresas/{empresaId}/vencimientos/{id}', [VencimientoController::class, 'update']);
    $router->delete('/empresas/{empresaId}/vencimientos/{id}', [VencimientoController::class, 'delete']);
    $router->put(
        '/empresas/{empresaId}/vencimientos/{id}/estado',
        [VencimientoController::class, 'cambiarEstado'],
    );

    // Requerimientos (de organismos, por empresa = contribuyente)
    $router->get('/empresas/{empresaId}/requerimientos', [RequerimientoController::class, 'index']);
    $router->post('/empresas/{empresaId}/requerimientos', [RequerimientoController::class, 'create']);
    $router->get('/empresas/{empresaId}/requerimientos/{id}', [RequerimientoController::class, 'show']);
    $router->put('/empresas/{empresaId}/requerimientos/{id}', [RequerimientoController::class, 'update']);
    $router->delete('/empresas/{empresaId}/requerimientos/{id}', [RequerimientoController::class, 'delete']);
});
