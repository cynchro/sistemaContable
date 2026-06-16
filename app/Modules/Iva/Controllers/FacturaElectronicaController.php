<?php

namespace App\Modules\Iva\Controllers;

use App\Support\Request;
use App\Support\Response;
use App\Modules\Iva\Services\FacturaElectronicaService;

class FacturaElectronicaController
{
    public function __construct(private FacturaElectronicaService $service)
    {
    }

    public function autorizar(Request $request): Response
    {
        $data = $this->service->autorizar(
            (int) $request->route('empresaId'),
            (int) $request->route('periodoId'),
            (int) $request->route('id'),
            (string) $request->tenantId(),
        );

        return Response::success($data);
    }
}
