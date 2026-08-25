<?php

use App\Modules\Iva\Controllers\IvaClienteController;
use App\Modules\Iva\Controllers\IvaProveedorController;
use App\Modules\Iva\Controllers\ImputacionContableController;
use App\Modules\Iva\Controllers\ConceptoController;
use App\Modules\Iva\Controllers\AlertaEstadisticaController;
use App\Modules\Iva\Controllers\VentaController;
use App\Modules\Iva\Controllers\CompraController;
use App\Modules\Iva\Controllers\LibroIvaController;
use App\Modules\Iva\Controllers\ReporteIvaController;
use App\Modules\Iva\Controllers\LibroIvaDigitalController;
use App\Modules\Iva\Controllers\DjIvaSimpleController;
use App\Modules\Iva\Controllers\SifereController;
use App\Modules\Iva\Controllers\MayorController;
use App\Modules\Iva\Controllers\EmpresaActividadController;
use App\Modules\Iva\Controllers\VentaClasificacionController;
use App\Modules\Iva\Controllers\AuditoriaController;
use App\Modules\Iva\Audit\AuditMiddleware;
use App\Modules\Iva\Controllers\PadronController;
use App\Modules\Iva\Controllers\PadronUnicoController;
use App\Modules\Iva\Controllers\AfipController;
use App\Modules\Iva\Controllers\FacturaElectronicaController;
use App\Modules\Iva\Controllers\PuntoVentaController;
use App\Modules\Iva\Controllers\AuditoriaAfipController;
use App\Modules\Iva\Controllers\ExportFormatoController;
use App\Modules\Iva\Controllers\LiquidacionController;
use App\Http\Middleware\AuthMiddleware;
use App\Http\Middleware\TenantMiddleware;
use App\Http\Middleware\PermissionMiddleware;
use App\Http\Middleware\EmpresaLockMiddleware;

/** @var \App\Support\Router $router (inyectado por bootstrap/app.php al cargar las rutas) */
$mw = [AuthMiddleware::class, TenantMiddleware::class, EmpresaLockMiddleware::class, AuditMiddleware::class];
$router->group($mw, function ($router) {
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

    // Reglas de imputación contable del proveedor (documento "Satélite Visual IVA" §5.4,
    // Pantalla B — página aparte, decisión B2, ver documentacion/analisis-satelite-visual-iva.md
    // §10). Migración 0051: capa de "concepto" global — 3 secciones. Anidadas bajo el proveedor,
    // no colisionan con la ruta de arriba: tienen un segmento más (/imputacion/...).
    $router->get(
        "{$base}/proveedores/{proveedorId}/imputacion/global",
        [ImputacionContableController::class, 'indexGlobal'],
        [$perm('iva.proveedores')],
    );
    $router->post(
        "{$base}/proveedores/{proveedorId}/imputacion/global",
        [ImputacionContableController::class, 'storeGlobal'],
        [$pw('iva.proveedores')],
    );
    $router->delete(
        "{$base}/proveedores/{proveedorId}/imputacion/global/{id}",
        [ImputacionContableController::class, 'deleteGlobal'],
        [$pw('iva.proveedores')],
    );
    $router->get(
        "{$base}/proveedores/{proveedorId}/imputacion/empresa",
        [ImputacionContableController::class, 'indexEmpresa'],
        [$perm('iva.proveedores')],
    );
    $router->post(
        "{$base}/proveedores/{proveedorId}/imputacion/empresa",
        [ImputacionContableController::class, 'storeEmpresa'],
        [$pw('iva.proveedores')],
    );
    $router->delete(
        "{$base}/proveedores/{proveedorId}/imputacion/empresa/{id}",
        [ImputacionContableController::class, 'deleteEmpresa'],
        [$pw('iva.proveedores')],
    );
    $router->get(
        "{$base}/proveedores/{proveedorId}/imputacion/concepto-default",
        [ImputacionContableController::class, 'showConceptoDefault'],
        [$perm('iva.proveedores')],
    );
    $router->put(
        "{$base}/proveedores/{proveedorId}/imputacion/concepto-default",
        [ImputacionContableController::class, 'updateConceptoDefault'],
        [$pw('iva.proveedores')],
    );

    // Mapeo concepto→cuenta de la empresa (traduce el catálogo tenant-level al plan de cuentas
    // real de esta empresa) — no depende de ningún proveedor puntual.
    $router->get(
        "{$base}/conceptos-cuenta",
        [ImputacionContableController::class, 'indexMapeo'],
        [$perm('iva.proveedores')],
    );
    $router->post(
        "{$base}/conceptos-cuenta",
        [ImputacionContableController::class, 'storeMapeo'],
        [$pw('iva.proveedores')],
    );
    $router->delete(
        "{$base}/conceptos-cuenta/{conceptoId}",
        [ImputacionContableController::class, 'deleteMapeo'],
        [$pw('iva.proveedores')],
    );

    // Ventas (agregado bajo período: cabecera + discriminación + percepciones)
    $router->get("{$bajoPeriodo}/ventas", [VentaController::class, 'index'], [$perm('iva.ventas')]);
    $router->post("{$bajoPeriodo}/ventas", [VentaController::class, 'create'], [$pw('iva.ventas')]);
    // "pendientes" DEBE registrarse antes de "{id}": el router matchea por orden y {id} ([^/]+)
    // capturaría el literal "pendientes" si esta ruta se declarara después.
    $router->get("{$bajoPeriodo}/ventas/pendientes", [VentaController::class, 'pendientes'], [$perm('iva.ventas')]);
    $router->get("{$bajoPeriodo}/ventas/{id}", [VentaController::class, 'show'], [$perm('iva.ventas')]);
    $router->put("{$bajoPeriodo}/ventas/{id}", [VentaController::class, 'update'], [$pw('iva.ventas')]);
    $router->delete("{$bajoPeriodo}/ventas/{id}", [VentaController::class, 'delete'], [$pw('iva.ventas')]);
    $router->post("{$bajoPeriodo}/ventas/{id}/mover", [VentaController::class, 'mover'], [$pw('iva.ventas')]);
    $router->post("{$bajoPeriodo}/ventas/import", [VentaController::class, 'import'], [$pw('iva.ventas')]);

    // Compras (agregado bajo período: cabecera + discriminación + percepciones)
    $router->get("{$bajoPeriodo}/compras", [CompraController::class, 'index'], [$perm('iva.compras')]);
    $router->post("{$bajoPeriodo}/compras", [CompraController::class, 'create'], [$pw('iva.compras')]);
    // "pendientes" DEBE registrarse antes de "{id}" (mismo motivo que en ventas, ver arriba).
    $router->get(
        "{$bajoPeriodo}/compras/pendientes",
        [CompraController::class, 'pendientes'],
        [$perm('iva.compras')],
    );
    $router->get("{$bajoPeriodo}/compras/{id}", [CompraController::class, 'show'], [$perm('iva.compras')]);
    $router->put("{$bajoPeriodo}/compras/{id}", [CompraController::class, 'update'], [$pw('iva.compras')]);
    $router->delete("{$bajoPeriodo}/compras/{id}", [CompraController::class, 'delete'], [$pw('iva.compras')]);
    $router->post("{$bajoPeriodo}/compras/{id}/mover", [CompraController::class, 'mover'], [$pw('iva.compras')]);
    $router->post("{$bajoPeriodo}/compras/import", [CompraController::class, 'import'], [$pw('iva.compras')]);

    // Libro IVA: totales, detalle por alícuota, DDJJ F2002 e IVA Simple (lectura)
    $router->get("{$bajoPeriodo}/totales", [LibroIvaController::class, 'totales'], [$perm('iva.libro')]);
    $router->get("{$bajoPeriodo}/reintegro-t", [LibroIvaController::class, 'reintegroT'], [$perm('iva.libro')]);
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

    // Exportación SIFERE Convenio Multilateral V4 (percepciones IIBB por jurisdicción).
    // tipo: percepciones · ?provincia_id=<id de la jurisdicción>
    $router->get(
        "{$bajoPeriodo}/sifere/{tipo}",
        [SifereController::class, 'exportar'],
        [$perm('iva.libro')],
    );

    // Mayor de cuentas (mayorización interna): resumen por cuenta + detalle de una cuenta.
    $router->get("{$bajoPeriodo}/mayor", [MayorController::class, 'resumen'], [$perm('iva.libro')]);
    $router->get("{$bajoPeriodo}/mayor/{cuentaId}", [MayorController::class, 'detalle'], [$perm('iva.libro')]);

    // Reporte analítico del Mayor por rango de fechas (empresa-level): filtros + cascada.
    // Query: ?desde&hasta&cuenta_id&provincia_id&cuit&origen&agrupar=cuenta,proveedor,provincia
    $router->get("{$base}/reportes/mayor", [MayorController::class, 'reporte'], [$perm('iva.libro')]);

    // Actividades (NAES) por empresa + mapa {punto de venta → actividad} (apertura DJ por actividad)
    $router->get("{$base}/actividades", [EmpresaActividadController::class, 'index'], [$perm('iva.libro')]);
    $router->post("{$base}/actividades", [EmpresaActividadController::class, 'create'], [$pw('iva.libro')]);
    $router->delete("{$base}/actividades/{id}", [EmpresaActividadController::class, 'delete'], [$pw('iva.libro')]);
    $router->get(
        "{$base}/actividades-punto-venta",
        [EmpresaActividadController::class, 'indexPuntosVenta'],
        [$perm('iva.libro')],
    );
    $router->post(
        "{$base}/actividades-punto-venta",
        [EmpresaActividadController::class, 'setPuntoVenta'],
        [$pw('iva.libro')],
    );
    $router->delete(
        "{$base}/actividades-punto-venta/{id}",
        [EmpresaActividadController::class, 'deletePuntoVenta'],
        [$pw('iva.libro')],
    );
    // Estrategia por alícuota (construcción)
    $router->get(
        "{$base}/actividades-alicuota",
        [EmpresaActividadController::class, 'indexAlicuotas'],
        [$perm('iva.libro')],
    );
    $router->post(
        "{$base}/actividades-alicuota",
        [EmpresaActividadController::class, 'setAlicuota'],
        [$pw('iva.libro')],
    );
    $router->delete(
        "{$base}/actividades-alicuota/{id}",
        [EmpresaActividadController::class, 'deleteAlicuota'],
        [$pw('iva.libro')],
    );
    // Estrategia por receptor (cliente / CUIT)
    $router->get(
        "{$base}/actividades-receptor",
        [EmpresaActividadController::class, 'indexReceptores'],
        [$perm('iva.libro')],
    );
    $router->post(
        "{$base}/actividades-receptor",
        [EmpresaActividadController::class, 'setReceptor'],
        [$pw('iva.libro')],
    );
    $router->delete(
        "{$base}/actividades-receptor/{id}",
        [EmpresaActividadController::class, 'deleteReceptor'],
        [$pw('iva.libro')],
    );
    // Estrategia por porcentajes fijos (coeficientes a nivel período)
    $router->get(
        "{$base}/actividades-coeficiente",
        [EmpresaActividadController::class, 'indexCoeficientes'],
        [$perm('iva.libro')],
    );
    $router->post(
        "{$base}/actividades-coeficiente",
        [EmpresaActividadController::class, 'setCoeficiente'],
        [$pw('iva.libro')],
    );
    $router->delete(
        "{$base}/actividades-coeficiente/{id}",
        [EmpresaActividadController::class, 'deleteCoeficiente'],
        [$pw('iva.libro')],
    );

    // Clasificación de ventas por punto de venta + tipo de comprobante → cuenta contable
    // (documento "Satélite Visual IVA" §4, Pantalla D). Empresa-scoped, como actividades.
    $router->get(
        "{$base}/ventas-punto-venta",
        [VentaClasificacionController::class, 'indexPuntoVenta'],
        [$perm('iva.ventas')],
    );
    $router->post(
        "{$base}/ventas-punto-venta",
        [VentaClasificacionController::class, 'setPuntoVenta'],
        [$pw('iva.ventas')],
    );
    $router->delete(
        "{$base}/ventas-punto-venta/{id}",
        [VentaClasificacionController::class, 'deletePuntoVenta'],
        [$pw('iva.ventas')],
    );
    $router->get(
        "{$base}/ventas-punto-venta-tipo",
        [VentaClasificacionController::class, 'indexPorTipo'],
        [$perm('iva.ventas')],
    );
    $router->post(
        "{$base}/ventas-punto-venta-tipo",
        [VentaClasificacionController::class, 'setPorTipo'],
        [$pw('iva.ventas')],
    );
    $router->delete(
        "{$base}/ventas-punto-venta-tipo/{id}",
        [VentaClasificacionController::class, 'deletePorTipo'],
        [$pw('iva.ventas')],
    );

    // Catálogo de conceptos del Padrón Único (documento "Satélite Visual IVA" §5.2/§5.4,
    // migración 0051), tenant-level — ABM simple, mismo patrón que /rubros.
    $router->get('/iva/conceptos', [ConceptoController::class, 'index'], [$perm('iva.proveedores')]);
    $router->post('/iva/conceptos', [ConceptoController::class, 'create'], [$pw('iva.proveedores')]);
    $router->get('/iva/conceptos/{id}', [ConceptoController::class, 'show'], [$perm('iva.proveedores')]);
    $router->put('/iva/conceptos/{id}', [ConceptoController::class, 'update'], [$pw('iva.proveedores')]);
    $router->delete('/iva/conceptos/{id}', [ConceptoController::class, 'delete'], [$pw('iva.proveedores')]);

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

    // Ambiente AFIP activo (homologación = pruebas, producción = real) — para el aviso del front
    $router->get('/afip/ambiente', [AfipController::class, 'ambiente'], [$perm('iva.padron')]);

    // Padrón AFIP: consulta de un contribuyente por CUIT (autocompletar cliente/proveedor)
    $router->get('/padron/{cuit}', [PadronController::class, 'show'], [$perm('iva.padron')]);
    $router->get('/padron/{cuit}/sugerencia', [PadronController::class, 'sugerencia'], [$perm('iva.padron')]);

    // Padrón Único de Sujetos (propio del estudio, no AFIP): vista global de consulta, sin
    // filtrar por empresa (documento "Satélite Visual IVA" §10, Etapa 4).
    $router->get('/padron-unico', [PadronUnicoController::class, 'index'], [$perm('iva.clientes')]);
    // "CUIT único" (pedido 3 del informe 10/08/2026): consulta exacta por CUIT, usada por el
    // alta de empresa para no volver a tipear un CUIT que ya está en el padrón de sujetos.
    $router->get('/padron-unico/cuit/{cuit}', [PadronUnicoController::class, 'porCuit'], [$perm('iva.clientes')]);

    // Motor de alertas estadísticas v1 (documento "Satélite Visual IVA" §7): tenant-wide, sin
    // permiso granular nuevo — cualquier usuario autenticado del estudio la puede ver (Auth +
    // Tenant ya los aplica el group de arriba).
    $router->get('/alertas', [AlertaEstadisticaController::class, 'index'], []);

    // Factura electrónica: solicitar CAE para una venta (numeración por punto de venta + WSFEv1)
    $router->post(
        "{$bajoPeriodo}/ventas/{id}/cae",
        [FacturaElectronicaController::class, 'autorizar'],
        [$pw('iva.facturacion')],
    );

    // Auditoría de ventas vs. ARCA (WSFEv1): compara la numeración local contra lo que
    // AFIP tiene autorizado por punto de venta + tipo, y permite consultar el detalle de
    // un comprobante puntual. Solo lectura, se calcula en vivo (sin tabla propia).
    $router->get(
        "{$base}/auditoria-afip",
        [AuditoriaAfipController::class, 'resumen'],
        [$perm('iva.auditoria-afip')],
    );
    $router->get(
        "{$base}/auditoria-afip/comprobante",
        [AuditoriaAfipController::class, 'comprobante'],
        [$perm('iva.auditoria-afip')],
    );

    // Puntos de venta (por empresa) — registro para la numeración de factura electrónica
    $router->get("{$base}/puntos-venta", [PuntoVentaController::class, 'index'], [$perm('iva.puntos-venta')]);
    $router->post("{$base}/puntos-venta", [PuntoVentaController::class, 'create'], [$pw('iva.puntos-venta')]);
    $router->get("{$base}/puntos-venta/{id}", [PuntoVentaController::class, 'show'], [$perm('iva.puntos-venta')]);
    $router->put("{$base}/puntos-venta/{id}", [PuntoVentaController::class, 'update'], [$pw('iva.puntos-venta')]);
    $router->delete("{$base}/puntos-venta/{id}", [PuntoVentaController::class, 'delete'], [$pw('iva.puntos-venta')]);

    // Botón "Liquidar IVA" (plan 25/08/2026): pedido del usuario (crear/ver) bajo período.
    $router->post(
        "{$bajoPeriodo}/liquidaciones",
        [LiquidacionController::class, 'create'],
        [$pw('iva.liquidar')],
    );
    $router->get(
        "{$bajoPeriodo}/liquidaciones",
        [LiquidacionController::class, 'index'],
        [$perm('iva.liquidar')],
    );
    $router->get(
        "{$bajoPeriodo}/liquidaciones/{id}",
        [LiquidacionController::class, 'show'],
        [$perm('iva.liquidar')],
    );

    // Endpoints del worker del bot (extractor/, en este mismo repo): no anidados bajo empresa
    // — la API key ya trae su propio tenant, la cola se acota por ahí (ver LiquidacionRepository).
    // Scopes propios, separados de 'iva.liquidar' (el de usuario): un rol de usuario normal no
    // tiene sentido que los tenga, pero conviven en el mismo catálogo de `permisos`.
    $router->get(
        '/iva/liquidaciones/pendientes',
        [LiquidacionController::class, 'pendiente'],
        [$perm('iva.liquidaciones.worker')],
    );
    $router->post(
        '/iva/liquidaciones/{id}/estado',
        [LiquidacionController::class, 'estado'],
        [$pw('iva.liquidaciones.worker')],
    );
    // Scope más estrecho a propósito (defensa en profundidad/auditoría): revela la Clave Fiscal
    // en claro, se llama solo cuando el worker detecta que la sesión de ese CUIT expiró.
    $router->post(
        '/iva/liquidaciones/{id}/credencial',
        [LiquidacionController::class, 'credencial'],
        [$pw('iva.liquidaciones.credencial')],
    );
});
