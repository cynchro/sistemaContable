<?php

namespace App\Modules\Iva\Controllers;

use App\Support\Request;
use App\Support\Response;
use App\Modules\Iva\Services\SujetoService;
use App\Modules\Iva\Requests\CreateSujetoRequest;
use App\Modules\Iva\Requests\UpdateSujetoRequest;

/** Proveedores = sujetos del Padrón Único activados con rol 'proveedor' para la empresa. */
class IvaProveedorController
{
    private const ROL = 'proveedor';

    public function __construct(private SujetoService $service)
    {
    }

    public function index(Request $request): Response
    {
        return Response::success($this->service->list(
            (int) $request->route('empresaId'),
            (string) $request->tenantId(),
            self::ROL,
            [
                'q'     => $request->input('q'),
                'orden' => $request->input('orden'),
            ],
        ));
    }

    public function show(Request $request): Response
    {
        return Response::success($this->service->get(
            (int) $request->route('id'),
            (int) $request->route('empresaId'),
            (string) $request->tenantId(),
            self::ROL,
        ));
    }

    public function create(Request $request, CreateSujetoRequest $validated): Response
    {
        $proveedor = $this->service->create(
            $validated->validated(),
            (int) $request->route('empresaId'),
            (string) $request->tenantId(),
            self::ROL,
        );

        return Response::success($proveedor, 201);
    }

    public function update(Request $request, UpdateSujetoRequest $validated): Response
    {
        $proveedor = $this->service->update(
            (int) $request->route('id'),
            $validated->validated(),
            (int) $request->route('empresaId'),
            (string) $request->tenantId(),
            self::ROL,
        );

        return Response::success($proveedor);
    }

    public function delete(Request $request): Response
    {
        $this->service->delete(
            (int) $request->route('id'),
            (int) $request->route('empresaId'),
            (string) $request->tenantId(),
            self::ROL,
        );

        return Response::success(['message' => 'Proveedor eliminado.']);
    }
}
