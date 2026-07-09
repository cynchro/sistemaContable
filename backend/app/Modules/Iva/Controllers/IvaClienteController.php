<?php

namespace App\Modules\Iva\Controllers;

use App\Support\Request;
use App\Support\Response;
use App\Modules\Iva\Services\IvaClienteService;
use App\Modules\Iva\Requests\CreateClienteRequest;
use App\Modules\Iva\Requests\UpdateClienteRequest;

class IvaClienteController
{
    public function __construct(private IvaClienteService $service)
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

    public function create(Request $request, CreateClienteRequest $validated): Response
    {
        $cliente = $this->service->create(
            $validated->validated(),
            (int) $request->route('empresaId'),
            (string) $request->tenantId(),
        );

        return Response::success($cliente, 201);
    }

    public function update(Request $request, UpdateClienteRequest $validated): Response
    {
        $cliente = $this->service->update(
            (int) $request->route('id'),
            $validated->validated(),
            (int) $request->route('empresaId'),
            (string) $request->tenantId(),
        );

        return Response::success($cliente);
    }

    public function delete(Request $request): Response
    {
        $this->service->delete(
            (int) $request->route('id'),
            (int) $request->route('empresaId'),
            (string) $request->tenantId(),
        );

        return Response::success(['message' => 'Cliente eliminado.']);
    }
}
