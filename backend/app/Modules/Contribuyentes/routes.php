<?php

use App\Modules\Contribuyentes\Controllers\SocioController;
use App\Modules\Contribuyentes\Controllers\CredencialController;
use App\Http\Middleware\AuthMiddleware;
use App\Http\Middleware\TenantMiddleware;
use App\Http\Middleware\PermissionMiddleware;

/** @var \App\Support\Router $router (inyectado por bootstrap/app.php al cargar las rutas) */
$router->group([AuthMiddleware::class, TenantMiddleware::class], function ($router) {
    // Socios/integrantes del contribuyente (empresa)
    $router->get('/empresas/{empresaId}/socios', [SocioController::class, 'index']);
    $router->post('/empresas/{empresaId}/socios', [SocioController::class, 'create']);
    $router->get('/empresas/{empresaId}/socios/{id}', [SocioController::class, 'show']);
    $router->put('/empresas/{empresaId}/socios/{id}', [SocioController::class, 'update']);
    $router->delete('/empresas/{empresaId}/socios/{id}', [SocioController::class, 'delete']);

    // Credenciales de acceso del contribuyente (portales fiscales + procesadoras de tarjeta):
    // devuelven la clave EN CLARO (el estudio la necesita para operar) — RBAC agregado
    // (25/08/2026, hallazgo de la investigación del botón "Liquidar IVA"): antes cualquier
    // usuario autenticado del tenant podía leer la Clave Fiscal de cualquier empresa, sin
    // ningún permiso granular. Ahora exige 'contribuyentes.credenciales' (lectura/escritura).
    $credPerm = PermissionMiddleware::class . ':contribuyentes.credenciales';
    $credPw   = PermissionMiddleware::class . ':contribuyentes.credenciales:write';
    $router->get('/empresas/{empresaId}/credenciales', [CredencialController::class, 'index'], [$credPerm]);
    $router->post('/empresas/{empresaId}/credenciales', [CredencialController::class, 'create'], [$credPw]);
    $router->get('/empresas/{empresaId}/credenciales/{id}', [CredencialController::class, 'show'], [$credPerm]);
    $router->put('/empresas/{empresaId}/credenciales/{id}', [CredencialController::class, 'update'], [$credPw]);
    $router->delete('/empresas/{empresaId}/credenciales/{id}', [CredencialController::class, 'delete'], [$credPw]);
});
