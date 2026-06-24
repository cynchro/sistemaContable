<?php

use App\Modules\Auth\Controllers\AuthController;
use App\Http\Middleware\AuthMiddleware;
use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\TenantMiddleware;

/** @var \App\Support\Router $router (inyectado por bootstrap/app.php al cargar las rutas) */
$router->post('/auth/register', [AuthController::class, 'register'], [AuthMiddleware::class, AdminMiddleware::class]);
$router->post('/auth/login', [AuthController::class, 'login']);
$router->post('/auth/refresh', [AuthController::class, 'refresh']);
$router->post('/auth/logout', [AuthController::class, 'logout'], [AuthMiddleware::class]);
$router->post('/auth/me', [AuthController::class, 'me'], [AuthMiddleware::class]);
$router->get('/auth/permisos/{key}', [AuthController::class, 'permisos'], [AuthMiddleware::class]);
$router->post('/auth/impersonate', [AuthController::class, 'impersonate'], [
    AuthMiddleware::class,
    AdminMiddleware::class,
    TenantMiddleware::class,
]);
