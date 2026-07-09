<?php

namespace App\Modules\Iva\Controllers;

use App\Support\Request;
use App\Support\Response;
use App\Modules\Iva\Services\IvaProveedorService;
use App\Modules\Iva\Requests\CreateProveedorRequest;
use App\Modules\Iva\Requests\UpdateProveedorRequest;

class IvaProveedorController
{
    public function __construct(private IvaProveedorService $service)
    {
    }

    public function index(Request $request): Response
    {
        return Response::success($this->service->list(
            (int) $request->route('empresaId'),
            (string) $request->tenantId(),
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
        ));
    }

    public function create(Request $request, CreateProveedorRequest $validated): Response
    {
        $proveedor = $this->service->create(
            $validated->validated(),
            (int) $request->route('empresaId'),
            (string) $request->tenantId(),
        );

        return Response::success($proveedor, 201);
    }

    public function update(Request $request, UpdateProveedorRequest $validated): Response
    {
        $proveedor = $this->service->update(
            (int) $request->route('id'),
            $validated->validated(),
            (int) $request->route('empresaId'),
            (string) $request->tenantId(),
        );

        return Response::success($proveedor);
    }

    public function delete(Request $request): Response
    {
        $this->service->delete(
            (int) $request->route('id'),
            (int) $request->route('empresaId'),
            (string) $request->tenantId(),
        );

        return Response::success(['message' => 'Proveedor eliminado.']);
    }
}
