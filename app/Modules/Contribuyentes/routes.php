<?php

use App\Modules\Contribuyentes\Controllers\SocioController;
use App\Modules\Contribuyentes\Controllers\CredencialController;
use App\Http\Middleware\AuthMiddleware;
use App\Http\Middleware\TenantMiddleware;

/** @var \App\Support\Router $router (inyectado por bootstrap/app.php al cargar las rutas) */
$router->group([AuthMiddleware::class, TenantMiddleware::class], function ($router) {
    // Socios/integrantes del contribuyente (empresa)
    $router->get('/empresas/{empresaId}/socios', [SocioController::class, 'index']);
    $router->post('/empresas/{empresaId}/socios', [SocioController::class, 'create']);
    $router->get('/empresas/{empresaId}/socios/{id}', [SocioController::class, 'show']);
    $router->put('/empresas/{empresaId}/socios/{id}', [SocioController::class, 'update']);
    $router->delete('/empresas/{empresaId}/socios/{id}', [SocioController::class, 'delete']);

    // Credenciales de acceso del contribuyente (portales fiscales + procesadoras de tarjeta)
    $router->get('/empresas/{empresaId}/credenciales', [CredencialController::class, 'index']);
    $router->post('/empresas/{empresaId}/credenciales', [CredencialController::class, 'create']);
    $router->get('/empresas/{empresaId}/credenciales/{id}', [CredencialController::class, 'show']);
    $router->put('/empresas/{empresaId}/credenciales/{id}', [CredencialController::class, 'update']);
    $router->delete('/empresas/{empresaId}/credenciales/{id}', [CredencialController::class, 'delete']);
});
