<?php

namespace App\Modules\Iva\Controllers;

use App\Support\Request;
use App\Support\Response;
use App\Modules\Iva\Services\AlertaEstadisticaService;

/** Motor de alertas estadísticas v1 (documento "Satélite Visual IVA" §7). */
class AlertaEstadisticaController
{
    public function __construct(private AlertaEstadisticaService $service)
    {
    }

    public function index(Request $request): Response
    {
        return Response::success($this->service->listar((string) $request->tenantId()));
    }
}
