<?php

namespace App\Modules\Iva\Controllers;

use App\Support\Request;
use App\Support\Response;
use App\Modules\Iva\Services\PadronService;

class PadronController
{
    public function __construct(private PadronService $service)
    {
    }

    public function show(Request $request): Response
    {
        $persona = $this->service->consultar((string) $request->route('cuit'));

        return Response::success([
            'cuit'         => $persona->cuit,
            'tipo_persona' => $persona->tipoPersona,
            'estado_clave' => $persona->estadoClave,
            'denominacion' => $persona->denominacion,
            'domicilio'    => $persona->domicilio,
            'impuestos'    => $persona->impuestos,
        ]);
    }
}
