<?php

use App\Modules\Sueldos\Controllers\EmpleadoController;
use App\Modules\Sueldos\Controllers\ConceptoController;
use App\Modules\Sueldos\Controllers\LiquidacionController;
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

    // Conceptos de liquidación (con fórmula, anidados bajo empresa)
    $router->get('/empresas/{empresaId}/conceptos', [ConceptoController::class, 'index']);
    $router->post('/empresas/{empresaId}/conceptos', [ConceptoController::class, 'create']);
    $router->get('/empresas/{empresaId}/conceptos/{id}', [ConceptoController::class, 'show']);
    $router->put('/empresas/{empresaId}/conceptos/{id}', [ConceptoController::class, 'update']);
    $router->delete('/empresas/{empresaId}/conceptos/{id}', [ConceptoController::class, 'delete']);

    // Liquidaciones (corrida por período)
    $router->get('/empresas/{empresaId}/liquidaciones', [LiquidacionController::class, 'index']);
    $router->post('/empresas/{empresaId}/liquidaciones', [LiquidacionController::class, 'create']);
    $router->get('/empresas/{empresaId}/liquidaciones/{id}', [LiquidacionController::class, 'show']);
    $router->delete('/empresas/{empresaId}/liquidaciones/{id}', [LiquidacionController::class, 'delete']);

    // Novedades, ejecución y recibo por empleado dentro de la liquidación
    $router->get(
        '/empresas/{empresaId}/liquidaciones/{id}/empleados/{empleadoId}/novedades',
        [LiquidacionController::class, 'novedades'],
    );
    $router->put(
        '/empresas/{empresaId}/liquidaciones/{id}/empleados/{empleadoId}/novedades',
        [LiquidacionController::class, 'setNovedades'],
    );
    $router->post(
        '/empresas/{empresaId}/liquidaciones/{id}/empleados/{empleadoId}/liquidar',
        [LiquidacionController::class, 'liquidar'],
    );
    $router->get(
        '/empresas/{empresaId}/liquidaciones/{id}/empleados/{empleadoId}/recibo',
        [LiquidacionController::class, 'recibo'],
    );
});
