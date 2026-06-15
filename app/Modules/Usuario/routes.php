<?php

use App\Modules\Usuario\Controllers\UsuarioController;
use App\Http\Middleware\AuthMiddleware;
use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\TenantMiddleware;

/** @var \App\Support\Router $router (inyectado por bootstrap/app.php al cargar las rutas) */
$router->group([AuthMiddleware::class, TenantMiddleware::class], function ($router) {
    $router->get('/usuarios', [UsuarioController::class, 'index']);
    $router->get('/usuarios/{id}', [UsuarioController::class, 'show']);
    $router->put('/usuarios/sucursal/{id}', [UsuarioController::class, 'updateSucursal']);
    $router->delete('/usuarios/{id}', [UsuarioController::class, 'delete'], [AdminMiddleware::class]);
});
