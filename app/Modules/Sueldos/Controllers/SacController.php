<?php

namespace App\Modules\Sueldos\Controllers;

use App\Support\Request;
use App\Support\Response;
use App\Modules\Sueldos\Services\SacService;

class SacController
{
    public function __construct(private SacService $service)
    {
    }

    /** GET /empresas/{id}/empleados/{empId}/sac?desde=YYYY-MM&hasta=YYYY-MM[&dias_trabajados=N] */
    public function calcular(Request $request): Response
    {
        $dias = $request->input('dias_trabajados');

        return Response::success($this->service->calcular(
            (int) $request->route('empresaId'),
            (int) $request->route('empleadoId'),
            (string) $request->input('desde', ''),
            (string) $request->input('hasta', ''),
            (string) $request->tenantId(),
            $dias !== null && $dias !== '' ? (int) $dias : null,
        ));
    }
}
