<?php

namespace App\Modules\Iva\Controllers;

use App\Support\Request;
use App\Support\Response;
use App\Modules\Iva\Services\AuditoriaAfipService;

class AuditoriaAfipController
{
    public function __construct(private AuditoriaAfipService $service)
    {
    }

    public function resumen(Request $request): Response
    {
        return Response::success($this->service->resumen(
            (int) $request->route('empresaId'),
            (string) $request->tenantId(),
        ));
    }

    public function comprobante(Request $request): Response
    {
        $data = $this->service->detalleComprobante(
            (int) $request->route('empresaId'),
            (int) $request->input('tipo_comprobante_id'),
            (string) $request->input('punto_venta'),
            (string) $request->input('letra'),
            (string) $request->input('numero'),
            (string) $request->tenantId(),
        );

        return Response::success($data);
    }
}
