<?php

namespace App\Modules\Iva\Controllers;

use App\Support\Request;
use App\Support\Response;
use App\Modules\Iva\Services\LibroIvaDigitalService;

class LibroIvaDigitalController
{
    public function __construct(private LibroIvaDigitalService $service)
    {
    }

    /** Descarga un archivo del Libro IVA Digital del período (ancho fijo, Portal IVA). */
    public function exportar(Request $request): Response
    {
        $archivo = $this->service->exportar(
            (int) $request->route('empresaId'),
            (int) $request->route('periodoId'),
            (string) $request->tenantId(),
            (string) $request->route('archivo'),
        );

        return Response::download($archivo['contenido'], $archivo['nombre'], 'text/plain; charset=utf-8');
    }
}
