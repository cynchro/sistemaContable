<?php

namespace App\Modules\Sueldos\Controllers;

use App\Support\Request;
use App\Support\Response;
use App\Modules\Sueldos\Services\VacacionesService;

class VacacionesController
{
    public function __construct(private VacacionesService $service)
    {
    }

    /** GET /empresas/{id}/empleados/{empId}/vacaciones[?anio=YYYY] */
    public function calcular(Request $request): Response
    {
        $anio = $request->input('anio');

        return Response::success($this->service->calcular(
            (int) $request->route('empresaId'),
            (int) $request->route('empleadoId'),
            (string) $request->tenantId(),
            $anio !== null && $anio !== '' ? (int) $anio : null,
        ));
    }
}
