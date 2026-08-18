<?php

namespace App\Modules\Compartido\Controllers;

use App\Support\Request;
use App\Support\Response;
use App\Modules\Compartido\Services\SigeService;

class SigeController
{
    public function __construct(private SigeService $service)
    {
    }

    /** Datos del SIGE mapeados a los campos del alta de empresa (autocompletar). */
    public function sugerencia(Request $request): Response
    {
        return Response::success($this->service->sugerencia((string) $request->route('cuit')));
    }
}
