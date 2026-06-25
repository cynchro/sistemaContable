<?php

namespace App\Modules\Iva\Controllers;

use App\Support\Request;
use App\Support\Response;
use App\Modules\Iva\Services\DjIvaSimpleService;

class DjIvaSimpleController
{
    public function __construct(private DjIvaSimpleService $service)
    {
    }

    /** Descarga un archivo de la DJ IVA Simple (apertura de otros conceptos, CSV). */
    public function exportar(Request $request): Response
    {
        $archivo = $this->service->exportar(
            (int) $request->route('empresaId'),
            (int) $request->route('periodoId'),
            (string) $request->tenantId(),
            (string) $request->route('archivo'),
        );

        return Response::download($archivo['contenido'], $archivo['nombre'], 'text/csv; charset=utf-8');
    }
}
