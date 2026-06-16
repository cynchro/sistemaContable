<?php

use App\Modules\Tareas\Controllers\TareaTipoController;
use App\Modules\Tareas\Controllers\TareaController;
use App\Http\Middleware\AuthMiddleware;
use App\Http\Middleware\TenantMiddleware;

/** @var \App\Support\Router $router (inyectado por bootstrap/app.php al cargar las rutas) */
$router->group([AuthMiddleware::class, TenantMiddleware::class], function ($router) {
    // Tipos de tarea (catálogo por tenant)
    $router->get('/tareas/tipos', [TareaTipoController::class, 'index']);
    $router->post('/tareas/tipos', [TareaTipoController::class, 'create']);
    $router->get('/tareas/tipos/{id}', [TareaTipoController::class, 'show']);
    $router->put('/tareas/tipos/{id}', [TareaTipoController::class, 'update']);
    $router->delete('/tareas/tipos/{id}', [TareaTipoController::class, 'delete']);

    // Tareas (workflow por tenant)
    $router->get('/tareas', [TareaController::class, 'index']);
    $router->post('/tareas', [TareaController::class, 'create']);
    $router->get('/tareas/{id}', [TareaController::class, 'show']);
    $router->put('/tareas/{id}', [TareaController::class, 'update']);
    $router->delete('/tareas/{id}', [TareaController::class, 'delete']);
    $router->put('/tareas/{id}/estado', [TareaController::class, 'cambiarEstado']);
    $router->get('/tareas/{id}/comentarios', [TareaController::class, 'comentarios']);
    $router->post('/tareas/{id}/comentarios', [TareaController::class, 'agregarComentario']);
    $router->get('/tareas/{id}/historial', [TareaController::class, 'historial']);
});
