<?php

use App\Modules\Sueldos\Controllers\EmpleadoController;
use App\Modules\Sueldos\Controllers\FamiliarController;
use App\Modules\Sueldos\Controllers\EmpresaConfigController;
use App\Modules\Sueldos\Controllers\ConceptoController;
use App\Modules\Sueldos\Controllers\LiquidacionController;
use App\Modules\Sueldos\Controllers\ContribucionController;
use App\Modules\Sueldos\Controllers\SacController;
use App\Modules\Sueldos\Controllers\VacacionesController;
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

    // SAC (aguinaldo): cálculo para un empleado en un semestre
    $router->get('/empresas/{empresaId}/empleados/{empleadoId}/sac', [SacController::class, 'calcular']);

    // Vacaciones: días por antigüedad + importe (Ley 20.744)
    $router->get('/empresas/{empresaId}/empleados/{empleadoId}/vacaciones', [VacacionesController::class, 'calcular']);

    // Grupo familiar (anidado bajo empleado)
    $router->get('/empresas/{empresaId}/empleados/{empleadoId}/familiares', [FamiliarController::class, 'index']);
    $router->post('/empresas/{empresaId}/empleados/{empleadoId}/familiares', [FamiliarController::class, 'create']);
    $router->get('/empresas/{empresaId}/empleados/{empleadoId}/familiares/{id}', [FamiliarController::class, 'show']);
    $router->put('/empresas/{empresaId}/empleados/{empleadoId}/familiares/{id}', [FamiliarController::class, 'update']);
    $router->delete(
        '/empresas/{empresaId}/empleados/{empleadoId}/familiares/{id}',
        [FamiliarController::class, 'delete'],
    );

    // Configuración de sueldos por empresa (1:1)
    $router->get('/empresas/{empresaId}/sueldos/config', [EmpresaConfigController::class, 'show']);
    $router->put('/empresas/{empresaId}/sueldos/config', [EmpresaConfigController::class, 'save']);

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

    // Contribuciones patronales: definiciones por empresa
    $router->get('/empresas/{empresaId}/contribuciones', [ContribucionController::class, 'index']);
    $router->post('/empresas/{empresaId}/contribuciones', [ContribucionController::class, 'create']);
    $router->get('/empresas/{empresaId}/contribuciones/{id}', [ContribucionController::class, 'show']);
    $router->put('/empresas/{empresaId}/contribuciones/{id}', [ContribucionController::class, 'update']);
    $router->delete('/empresas/{empresaId}/contribuciones/{id}', [ContribucionController::class, 'delete']);

    // Contribuciones liquidadas por empleado dentro de la liquidación
    $router->post(
        '/empresas/{empresaId}/liquidaciones/{id}/empleados/{empleadoId}/contribuciones',
        [ContribucionController::class, 'calcular'],
    );
    $router->get(
        '/empresas/{empresaId}/liquidaciones/{id}/empleados/{empleadoId}/contribuciones',
        [ContribucionController::class, 'liquidadas'],
    );
});
