<?php

use App\Modules\Iva\Controllers\IvaClienteController;
use App\Modules\Iva\Controllers\IvaProveedorController;
use App\Modules\Iva\Controllers\VentaController;
use App\Modules\Iva\Controllers\CompraController;
use App\Modules\Iva\Controllers\LibroIvaController;
use App\Modules\Iva\Controllers\ReporteIvaController;
use App\Modules\Iva\Controllers\LibroIvaDigitalController;
use App\Modules\Iva\Controllers\DjIvaSimpleController;
use App\Modules\Iva\Controllers\AuditoriaController;
use App\Modules\Iva\Audit\AuditMiddleware;
use App\Modules\Iva\Controllers\PadronController;
use App\Modules\Iva\Controllers\FacturaElectronicaController;
use App\Modules\Iva\Controllers\PuntoVentaController;
use App\Modules\Iva\Controllers\ExportFormatoController;
use App\Http\Middleware\AuthMiddleware;
use App\Http\Middleware\TenantMiddleware;
use App\Http\Middleware\PermissionMiddleware;

/** @var \App\Support\Router $router (inyectado por bootstrap/app.php al cargar las rutas) */
$router->group([AuthMiddleware::class, TenantMiddleware::class, AuditMiddleware::class], function ($router) {
    // RBAC por recurso: cada ruta exige un permiso (read = GET, write = POST/PUT/DELETE).
    // Un rol con el super-permiso 'Acceso Total' los cubre todos (ver PermissionChecker).
    $perm = static fn (string $p): string => PermissionMiddleware::class . ':' . $p;
    $pw   = static fn (string $p): string => $perm($p) . ':write';

    $base = '/empresas/{empresaId}';
    $bajoPeriodo = $base . '/periodos/{periodoId}';

    // Clientes (anidados bajo empresa)
    $router->get("{$base}/clientes", [IvaClienteController::class, 'index'], [$perm('iva.clientes')]);
    $router->post("{$base}/clientes", [IvaClienteController::class, 'create'], [$pw('iva.clientes')]);
    $router->get("{$base}/clientes/{id}", [IvaClienteController::class, 'show'], [$perm('iva.clientes')]);
    $router->put("{$base}/clientes/{id}", [IvaClienteController::class, 'update'], [$pw('iva.clientes')]);
    $router->delete("{$base}/clientes/{id}", [IvaClienteController::class, 'delete'], [$pw('iva.clientes')]);

    // Proveedores (anidados bajo empresa)
    $router->get("{$base}/proveedores", [IvaProveedorController::class, 'index'], [$perm('iva.proveedores')]);
    $router->post("{$base}/proveedores", [IvaProveedorController::class, 'create'], [$pw('iva.proveedores')]);
    $router->get("{$base}/proveedores/{id}", [IvaProveedorController::class, 'show'], [$perm('iva.proveedores')]);
    $router->put("{$base}/proveedores/{id}", [IvaProveedorController::class, 'update'], [$pw('iva.proveedores')]);
    $router->delete("{$base}/proveedores/{id}", [IvaProveedorController::class, 'delete'], [$pw('iva.proveedores')]);

    // Ventas (agregado bajo período: cabecera + discriminación + percepciones)
    $router->get("{$bajoPeriodo}/ventas", [VentaController::class, 'index'], [$perm('iva.ventas')]);
    $router->post("{$bajoPeriodo}/ventas", [VentaController::class, 'create'], [$pw('iva.ventas')]);
    $router->get("{$bajoPeriodo}/ventas/{id}", [VentaController::class, 'show'], [$perm('iva.ventas')]);
    $router->put("{$bajoPeriodo}/ventas/{id}", [VentaController::class, 'update'], [$pw('iva.ventas')]);
    $router->delete("{$bajoPeriodo}/ventas/{id}", [VentaController::class, 'delete'], [$pw('iva.ventas')]);
    $router->post("{$bajoPeriodo}/ventas/{id}/mover", [VentaController::class, 'mover'], [$pw('iva.ventas')]);

    // Compras (agregado bajo período: cabecera + discriminación + percepciones)
    $router->get("{$bajoPeriodo}/compras", [CompraController::class, 'index'], [$perm('iva.compras')]);
    $router->post("{$bajoPeriodo}/compras", [CompraController::class, 'create'], [$pw('iva.compras')]);
    $router->get("{$bajoPeriodo}/compras/{id}", [CompraController::class, 'show'], [$perm('iva.compras')]);
    $router->put("{$bajoPeriodo}/compras/{id}", [CompraController::class, 'update'], [$pw('iva.compras')]);
    $router->delete("{$bajoPeriodo}/compras/{id}", [CompraController::class, 'delete'], [$pw('iva.compras')]);
    $router->post("{$bajoPeriodo}/compras/{id}/mover", [CompraController::class, 'mover'], [$pw('iva.compras')]);

    // Libro IVA: totales, detalle por alícuota, DDJJ F2002 e IVA Simple (lectura)
    $router->get("{$bajoPeriodo}/totales", [LibroIvaController::class, 'totales'], [$perm('iva.libro')]);
    $router->get("{$bajoPeriodo}/libro-iva", [LibroIvaController::class, 'detalle'], [$perm('iva.libro')]);
    $router->get("{$bajoPeriodo}/ddjj", [LibroIvaController::class, 'ddjj'], [$perm('iva.libro')]);
    $router->get("{$bajoPeriodo}/iva-simple", [LibroIvaController::class, 'ivaSimple'], [$perm('iva.libro')]);
    // Presentar (persistir) la DDJJ IVA Simple del período → escritura
    $router->post("{$bajoPeriodo}/iva-simple", [LibroIvaController::class, 'presentarIvaSimple'], [$pw('iva.libro')]);

    // Reportes: subdiario / libro IVA (listado de comprobantes + totales)
    $router->get("{$bajoPeriodo}/reportes/ventas", [ReporteIvaController::class, 'ventas'], [$perm('iva.libro')]);
    $router->get("{$bajoPeriodo}/reportes/compras", [ReporteIvaController::class, 'compras'], [$perm('iva.libro')]);
    $router->get(
        "{$bajoPeriodo}/reportes/percepciones",
        [ReporteIvaController::class, 'percepciones'],
        [$perm('iva.libro')],
    );

    // Exportaciones: descarga del subdiario como CSV/TXT (?formato=csv|txt)
    $router->get(
        "{$bajoPeriodo}/exportar/ventas",
        [ReporteIvaController::class, 'exportarVentas'],
        [$perm('iva.libro')],
    );
    $router->get(
        "{$bajoPeriodo}/exportar/compras",
        [ReporteIvaController::class, 'exportarCompras'],
        [$perm('iva.libro')],
    );

    // Libro IVA Digital (Portal IVA): descarga de los archivos de ancho fijo de ARCA.
    // archivo: ventas-cbte | ventas-alicuotas | compras-cbte | compras-alicuotas
    $router->get(
        "{$bajoPeriodo}/libro-iva-digital/{archivo}",
        [LibroIvaDigitalController::class, 'exportar'],
        [$perm('iva.libro')],
    );

    // DJ IVA Simple (apertura de otros conceptos por actividad, CSV de ARCA)
    // archivo: debito-fiscal | restitucion-debito | credito-fiscal | restitucion-credito
    $router->get(
        "{$bajoPeriodo}/dj-iva-simple/{archivo}",
        [DjIvaSimpleController::class, 'exportar'],
        [$perm('iva.libro')],
    );

    // Auditoría de operaciones (registro de cambios) — lectura paginada por tenant
    $router->get('/iva/auditoria', [AuditoriaController::class, 'index'], [$perm('iva.auditoria')]);

    // Exportador TXT configurable: formatos por tenant + descarga del subdiario con un formato
    $router->get('/iva/export-formatos', [ExportFormatoController::class, 'index'], [$perm('iva.libro')]);
    $router->post('/iva/export-formatos', [ExportFormatoController::class, 'create'], [$pw('iva.libro')]);
    $router->get('/iva/export-formatos/{id}', [ExportFormatoController::class, 'show'], [$perm('iva.libro')]);
    $router->delete('/iva/export-formatos/{id}', [ExportFormatoController::class, 'delete'], [$pw('iva.libro')]);
    $router->get(
        "{$bajoPeriodo}/exportar-config/{formatoId}",
        [ExportFormatoController::class, 'exportar'],
        [$perm('iva.libro')],
    );

    // Padrón AFIP: consulta de un contribuyente por CUIT (autocompletar cliente/proveedor)
    $router->get('/padron/{cuit}', [PadronController::class, 'show'], [$perm('iva.padron')]);
    $router->get('/padron/{cuit}/sugerencia', [PadronController::class, 'sugerencia'], [$perm('iva.padron')]);

    // Factura electrónica: solicitar CAE para una venta (numeración por punto de venta + WSFEv1)
    $router->post(
        "{$bajoPeriodo}/ventas/{id}/cae",
        [FacturaElectronicaController::class, 'autorizar'],
        [$pw('iva.facturacion')],
    );

    // Puntos de venta (por empresa) — registro para la numeración de factura electrónica
    $router->get("{$base}/puntos-venta", [PuntoVentaController::class, 'index'], [$perm('iva.puntos-venta')]);
    $router->post("{$base}/puntos-venta", [PuntoVentaController::class, 'create'], [$pw('iva.puntos-venta')]);
    $router->get("{$base}/puntos-venta/{id}", [PuntoVentaController::class, 'show'], [$perm('iva.puntos-venta')]);
    $router->put("{$base}/puntos-venta/{id}", [PuntoVentaController::class, 'update'], [$pw('iva.puntos-venta')]);
    $router->delete("{$base}/puntos-venta/{id}", [PuntoVentaController::class, 'delete'], [$pw('iva.puntos-venta')]);
});
