<?php

use App\Modules\Iva\Controllers\IvaClienteController;
use App\Modules\Iva\Controllers\IvaProveedorController;
use App\Modules\Iva\Controllers\VentaController;
use App\Modules\Iva\Controllers\CompraController;
use App\Modules\Iva\Controllers\LibroIvaController;
use App\Http\Middleware\AuthMiddleware;
use App\Http\Middleware\TenantMiddleware;

/** @var \App\Support\Router $router (inyectado por bootstrap/app.php al cargar las rutas) */
$router->group([AuthMiddleware::class, TenantMiddleware::class], function ($router) {
    // Clientes (anidados bajo empresa)
    $router->get('/empresas/{empresaId}/clientes', [IvaClienteController::class, 'index']);
    $router->post('/empresas/{empresaId}/clientes', [IvaClienteController::class, 'create']);
    $router->get('/empresas/{empresaId}/clientes/{id}', [IvaClienteController::class, 'show']);
    $router->put('/empresas/{empresaId}/clientes/{id}', [IvaClienteController::class, 'update']);
    $router->delete('/empresas/{empresaId}/clientes/{id}', [IvaClienteController::class, 'delete']);

    // Proveedores (anidados bajo empresa)
    $router->get('/empresas/{empresaId}/proveedores', [IvaProveedorController::class, 'index']);
    $router->post('/empresas/{empresaId}/proveedores', [IvaProveedorController::class, 'create']);
    $router->get('/empresas/{empresaId}/proveedores/{id}', [IvaProveedorController::class, 'show']);
    $router->put('/empresas/{empresaId}/proveedores/{id}', [IvaProveedorController::class, 'update']);
    $router->delete('/empresas/{empresaId}/proveedores/{id}', [IvaProveedorController::class, 'delete']);

    // Ventas (agregado bajo período: cabecera + discriminación + retenciones)
    $router->get('/empresas/{empresaId}/periodos/{periodoId}/ventas', [VentaController::class, 'index']);
    $router->post('/empresas/{empresaId}/periodos/{periodoId}/ventas', [VentaController::class, 'create']);
    $router->get('/empresas/{empresaId}/periodos/{periodoId}/ventas/{id}', [VentaController::class, 'show']);
    $router->put('/empresas/{empresaId}/periodos/{periodoId}/ventas/{id}', [VentaController::class, 'update']);
    $router->delete('/empresas/{empresaId}/periodos/{periodoId}/ventas/{id}', [VentaController::class, 'delete']);

    // Compras (agregado bajo período: cabecera + discriminación + retenciones)
    $router->get('/empresas/{empresaId}/periodos/{periodoId}/compras', [CompraController::class, 'index']);
    $router->post('/empresas/{empresaId}/periodos/{periodoId}/compras', [CompraController::class, 'create']);
    $router->get('/empresas/{empresaId}/periodos/{periodoId}/compras/{id}', [CompraController::class, 'show']);
    $router->put('/empresas/{empresaId}/periodos/{periodoId}/compras/{id}', [CompraController::class, 'update']);
    $router->delete('/empresas/{empresaId}/periodos/{periodoId}/compras/{id}', [CompraController::class, 'delete']);

    // Libro IVA: totales del período (derivados) y detalle por alícuota/condición
    $router->get('/empresas/{empresaId}/periodos/{periodoId}/totales', [LibroIvaController::class, 'totales']);
    $router->get('/empresas/{empresaId}/periodos/{periodoId}/libro-iva', [LibroIvaController::class, 'detalle']);
    $router->get('/empresas/{empresaId}/periodos/{periodoId}/ddjj', [LibroIvaController::class, 'ddjj']);
});
