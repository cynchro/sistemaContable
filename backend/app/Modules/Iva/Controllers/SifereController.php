<?php

namespace App\Modules\Iva\Controllers;

use App\Support\Request;
use App\Support\Response;
use App\Modules\Iva\Services\SifereService;

class SifereController
{
    public function __construct(private SifereService $service)
    {
    }

    /** Descarga el TXT SIFERE de la jurisdicción indicada (?provincia_id=). */
    public function exportar(Request $request): Response
    {
        $provinciaId = $request->input('provincia_id');

        $archivo = $this->service->exportar(
            (int) $request->route('empresaId'),
            (int) $request->route('periodoId'),
            (string) $request->tenantId(),
            (string) $request->route('tipo'),
            $provinciaId !== null && $provinciaId !== '' ? (int) $provinciaId : null,
        );

        return Response::download($archivo['contenido'], $archivo['nombre'], 'text/plain; charset=utf-8');
    }
}
