<?php

use App\Modules\Compartido\Controllers\EmpresaController;
use App\Modules\Compartido\Controllers\PeriodoController;
use App\Http\Middleware\AuthMiddleware;
use App\Http\Middleware\TenantMiddleware;

/** @var \App\Support\Router $router (inyectado por bootstrap/app.php al cargar las rutas) */
$router->group([AuthMiddleware::class, TenantMiddleware::class], function ($router) {
    $router->get('/empresas', [EmpresaController::class, 'index']);
    $router->get('/empresas/{id}', [EmpresaController::class, 'show']);
    $router->post('/empresas', [EmpresaController::class, 'create']);
    $router->put('/empresas/{id}', [EmpresaController::class, 'update']);
    $router->delete('/empresas/{id}', [EmpresaController::class, 'delete']);

    // Períodos (anidados bajo empresa)
    $router->get('/empresas/{empresaId}/periodos', [PeriodoController::class, 'index']);
    $router->post('/empresas/{empresaId}/periodos', [PeriodoController::class, 'create']);
    $router->get('/empresas/{empresaId}/periodos/{id}', [PeriodoController::class, 'show']);
    $router->put('/empresas/{empresaId}/periodos/{id}', [PeriodoController::class, 'update']);
    $router->delete('/empresas/{empresaId}/periodos/{id}', [PeriodoController::class, 'delete']);
    $router->post('/empresas/{empresaId}/periodos/{id}/cerrar', [PeriodoController::class, 'cerrar']);
    $router->post('/empresas/{empresaId}/periodos/{id}/abrir', [PeriodoController::class, 'abrir']);
});
