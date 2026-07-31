<?php

namespace App\Modules\Iva\Controllers;

use App\Support\Request;
use App\Support\Response;
use App\Modules\Iva\Services\SujetoService;

/**
 * Vista global del Padrón Único de Sujetos (documento "Satélite Visual IVA" §10, Etapa 4:
 * "consulta del padrón global"). Nombre distinto de `PadronController` a propósito: ese
 * controller es la consulta al padrón de AFIP (`GET /padron/{cuit}`, no relacionado); este es
 * el padrón propio del estudio (`iva_sujetos`). A diferencia de
 * `IvaClienteController`/`IvaProveedorController` (siempre scopeados a una empresa), esta ruta
 * no cuelga de `/empresas/{id}` — lista todos los sujetos del tenant con las empresas donde cada
 * uno está activo.
 */
class PadronUnicoController
{
    public function __construct(private SujetoService $service)
    {
    }

    public function index(Request $request): Response
    {
        return Response::success($this->service->listGlobal(
            (string) $request->tenantId(),
            ['q' => $request->input('q')],
        ));
    }
}
