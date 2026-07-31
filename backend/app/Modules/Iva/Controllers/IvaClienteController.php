<?php

namespace App\Modules\Iva\Controllers;

use App\Support\Request;
use App\Support\Response;
use App\Modules\Iva\Services\SujetoService;
use App\Modules\Iva\Requests\CreateSujetoRequest;
use App\Modules\Iva\Requests\UpdateSujetoRequest;

/** Clientes = sujetos del Padrón Único activados con rol 'cliente' para la empresa. */
class IvaClienteController
{
    private const ROL = 'cliente';

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
        $cliente = $this->service->create(
            $validated->validated(),
            (int) $request->route('empresaId'),
            (string) $request->tenantId(),
            self::ROL,
        );

        return Response::success($cliente, 201);
    }

    public function update(Request $request, UpdateSujetoRequest $validated): Response
    {
        $cliente = $this->service->update(
            (int) $request->route('id'),
            $validated->validated(),
            (int) $request->route('empresaId'),
            (string) $request->tenantId(),
            self::ROL,
        );

        return Response::success($cliente);
    }

    public function delete(Request $request): Response
    {
        $this->service->delete(
            (int) $request->route('id'),
            (int) $request->route('empresaId'),
            (string) $request->tenantId(),
            self::ROL,
        );

        return Response::success(['message' => 'Cliente eliminado.']);
    }
}
