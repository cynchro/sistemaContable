<?php

use App\Modules\Compartido\Controllers\EmpresaController;
use App\Modules\Compartido\Controllers\PeriodoController;
use App\Modules\Compartido\Controllers\CuentaController;
use App\Modules\Compartido\Controllers\RubroController;
use App\Modules\Compartido\Controllers\CatalogoController;
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

    // Cuentas (plan de cuentas, anidadas bajo empresa)
    $router->get('/empresas/{empresaId}/cuentas', [CuentaController::class, 'index']);
    $router->post('/empresas/{empresaId}/cuentas', [CuentaController::class, 'create']);
    $router->get('/empresas/{empresaId}/cuentas/{id}', [CuentaController::class, 'show']);
    $router->put('/empresas/{empresaId}/cuentas/{id}', [CuentaController::class, 'update']);
    $router->delete('/empresas/{empresaId}/cuentas/{id}', [CuentaController::class, 'delete']);

    // Rubros (por tenant)
    $router->get('/rubros', [RubroController::class, 'index']);
    $router->post('/rubros', [RubroController::class, 'create']);
    $router->get('/rubros/{id}', [RubroController::class, 'show']);
    $router->put('/rubros/{id}', [RubroController::class, 'update']);
    $router->delete('/rubros/{id}', [RubroController::class, 'delete']);
});

// Catálogos AFIP globales (solo lectura; no requieren tenant)
$router->group([AuthMiddleware::class], function ($router) {
    $router->get('/catalogos', [CatalogoController::class, 'index']);
    $router->get('/catalogos/{catalogo}', [CatalogoController::class, 'show']);
});
