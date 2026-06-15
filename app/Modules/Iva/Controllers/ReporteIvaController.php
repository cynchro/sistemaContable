<?php

namespace App\Modules\Iva\Controllers;

use App\Support\Request;
use App\Support\Response;
use App\Modules\Iva\Services\ReporteIvaService;

class ReporteIvaController
{
    public function __construct(private ReporteIvaService $service)
    {
    }

    /** Subdiario / Libro IVA Ventas del período (comprobantes + totales). */
    public function ventas(Request $request): Response
    {
        return Response::success($this->service->libroVentas(
            (int) $request->route('empresaId'),
            (int) $request->route('periodoId'),
            (string) $request->tenantId(),
        ));
    }

    /** Subdiario / Libro IVA Compras del período (comprobantes + totales). */
    public function compras(Request $request): Response
    {
        return Response::success($this->service->libroCompras(
            (int) $request->route('empresaId'),
            (int) $request->route('periodoId'),
            (string) $request->tenantId(),
        ));
    }
}
