<?php

namespace App\Modules\Iva\Controllers;

use App\Support\Request;
use App\Support\Response;
use App\Modules\Iva\Services\ImputacionContableService;

/** Reglas por punto de venta del proveedor (Pantalla B, página aparte — decisión B2). */
class ImputacionContableController
{
    public function __construct(private ImputacionContableService $service)
    {
    }

    public function index(Request $request): Response
    {
        return Response::success($this->service->list(
            (int) $request->route('proveedorId'),
            (int) $request->route('empresaId'),
            (string) $request->tenantId(),
        ));
    }

    public function store(Request $request): Response
    {
        $this->service->set(
            (int) $request->route('proveedorId'),
            (int) $request->route('empresaId'),
            $request->all(),
            (string) $request->tenantId(),
        );

        return Response::success(['message' => 'Regla guardada.']);
    }

    public function delete(Request $request): Response
    {
        $this->service->delete(
            (int) $request->route('proveedorId'),
            (int) $request->route('empresaId'),
            (int) $request->route('id'),
            (string) $request->tenantId(),
        );

        return Response::success(['message' => 'Regla eliminada.']);
    }
}
