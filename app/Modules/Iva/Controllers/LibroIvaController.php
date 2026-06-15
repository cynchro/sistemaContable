<?php

namespace App\Modules\Iva\Controllers;

use App\Support\Request;
use App\Support\Response;
use App\Modules\Iva\Services\LibroIvaService;

class LibroIvaController
{
    public function __construct(private LibroIvaService $service)
    {
    }

    /** Totales del período: ventas/compras, IVA débito/crédito y saldo. */
    public function totales(Request $request): Response
    {
        return Response::success($this->service->totales(
            (int) $request->route('empresaId'),
            (int) $request->route('periodoId'),
            (string) $request->tenantId(),
        ));
    }
}
