<?php

namespace App\Modules\Iva\Controllers;

use App\Support\Request;
use App\Support\Response;
use App\Modules\Iva\Services\MayorService;
use App\Modules\Iva\Services\ReporteMayorService;

class MayorController
{
    public function __construct(
        private MayorService $service,
        private ReporteMayorService $reporte,
    ) {
    }

    /** Resumen de Movimientos (Mayor de Cuentas): total por cuenta del período. */
    public function resumen(Request $request): Response
    {
        return Response::success($this->service->resumen(
            (int) $request->route('empresaId'),
            (int) $request->route('periodoId'),
            (string) $request->tenantId(),
        ));
    }

    /** Detalle de Movimientos de una cuenta: comprobantes imputados a ella. */
    public function detalle(Request $request): Response
    {
        return Response::success($this->service->detalle(
            (int) $request->route('empresaId'),
            (int) $request->route('periodoId'),
            (int) $request->route('cuentaId'),
            (string) $request->tenantId(),
        ));
    }

    /**
     * Reporte analítico del Mayor por rango de fechas (R2): movimientos por línea agrupados
     * en cascada con subtotales. Query: desde, hasta, cuenta_id, provincia_id, cuit, origen, agrupar.
     */
    public function reporte(Request $request): Response
    {
        return Response::success($this->reporte->reporte(
            (int) $request->route('empresaId'),
            (string) $request->tenantId(),
            $request->all(),
        ));
    }
}
