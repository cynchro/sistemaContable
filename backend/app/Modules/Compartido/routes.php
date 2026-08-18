<?php

use App\Modules\Compartido\Controllers\EmpresaController;
use App\Modules\Compartido\Controllers\EmpresaLockController;
use App\Modules\Compartido\Controllers\PeriodoController;
use App\Modules\Compartido\Controllers\CuentaController;
use App\Modules\Compartido\Controllers\RubroController;
use App\Modules\Compartido\Controllers\TipoRetencionController;
use App\Modules\Compartido\Controllers\CatalogoController;
use App\Modules\Compartido\Controllers\SigeController;
use App\Http\Middleware\AuthMiddleware;
use App\Http\Middleware\TenantMiddleware;
use App\Http\Middleware\EmpresaLockMiddleware;

/** @var \App\Support\Router $router (inyectado por bootstrap/app.php al cargar las rutas) */

// "Ocupación" de empresa (WhatsApp con el cliente, 11/08/2026): estas 3 rutas MANEJAN el lock,
// por eso van en un grupo aparte, sin EmpresaLockMiddleware — si estuvieran adentro, un admin
// nunca podría llamar a /ocupar para entrar en modo observador (quedaría bloqueado como
// cualquier otra escritura, ver el docblock de EmpresaLockMiddleware).
$router->group([AuthMiddleware::class, TenantMiddleware::class], function ($router) {
    $router->post('/empresas/{id}/ocupar', [EmpresaLockController::class, 'ocupar']);
    $router->post('/empresas/{id}/ping', [EmpresaLockController::class, 'ping']);
    $router->post('/empresas/{id}/liberar', [EmpresaLockController::class, 'liberar']);
});

$router->group([AuthMiddleware::class, TenantMiddleware::class, EmpresaLockMiddleware::class], function ($router) {
    $router->get('/empresas', [EmpresaController::class, 'index']);
    $router->get('/empresas/{id}', [EmpresaController::class, 'show']);
    $router->post('/empresas', [EmpresaController::class, 'create']);
    $router->put('/empresas/{id}', [EmpresaController::class, 'update']);
    $router->delete('/empresas/{id}', [EmpresaController::class, 'delete']);

    // SIGE (sistemaCuarto): autocompletar el alta de empresa por CUIT, sin duplicar la carga.
    $router->get('/sige/{cuit}/sugerencia', [SigeController::class, 'sugerencia']);

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

    // Tipos de retención/percepción (estándar AFIP + propios del estudio)
    $router->get('/tipos-retencion', [TipoRetencionController::class, 'index']);
    $router->post('/tipos-retencion', [TipoRetencionController::class, 'create']);
    $router->get('/tipos-retencion/{id}', [TipoRetencionController::class, 'show']);
    $router->put('/tipos-retencion/{id}', [TipoRetencionController::class, 'update']);
    $router->delete('/tipos-retencion/{id}', [TipoRetencionController::class, 'delete']);
});

// Catálogos AFIP globales (solo lectura; no requieren tenant)
$router->group([AuthMiddleware::class], function ($router) {
    $router->get('/catalogos', [CatalogoController::class, 'index']);
    $router->get('/catalogos/{catalogo}', [CatalogoController::class, 'show']);
});
