<?php

use App\Modules\Admin\Controllers\AdminController;
use App\Http\Middleware\AuthMiddleware;
use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\TenantMiddleware;

/** @var \App\Support\Router $router (inyectado por bootstrap/app.php al cargar las rutas) */
$router->group([AuthMiddleware::class, AdminMiddleware::class], function ($router) {
    // Roles
    $router->get('/admin/roles', [AdminController::class, 'indexRoles']);
    $router->post('/admin/roles', [AdminController::class, 'storeRole']);
    $router->get('/admin/roles/{id}', [AdminController::class, 'showRole']);
    $router->put('/admin/roles/{id}', [AdminController::class, 'updateRole']);
    $router->post('/admin/roles/{id}/assign', [AdminController::class, 'assignPermisos']);
    $router->delete('/admin/roles/{id}/assign', [AdminController::class, 'unassignPermisos']);

    // Permisos
    $router->get('/admin/permisos', [AdminController::class, 'indexPermisos']);
    $router->post('/admin/permisos', [AdminController::class, 'storePermiso']);
    $router->get('/admin/permisos/{id}', [AdminController::class, 'showPermiso']);
    $router->put('/admin/permisos/{id}', [AdminController::class, 'updatePermiso']);
});

// Tenant-scoped admin routes — users are isolated per tenant
$router->group([AuthMiddleware::class, AdminMiddleware::class, TenantMiddleware::class], function ($router) {
    $router->get('/admin/users', [AdminController::class, 'users']);
    $router->post('/admin/impersonate', [AdminController::class, 'impersonate']);
});
