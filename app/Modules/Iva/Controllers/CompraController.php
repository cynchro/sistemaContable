<?php

namespace App\Modules\Iva\Controllers;

use App\Support\Request;
use App\Support\Response;
use App\Modules\Iva\Services\CompraService;
use App\Modules\Iva\Requests\CreateCompraRequest;
use App\Modules\Iva\Requests\UpdateCompraRequest;
use App\Modules\Iva\Requests\MoverComprobanteRequest;

class CompraController
{
    public function __construct(private CompraService $service)
    {
    }

    public function index(Request $request): Response
    {
        return Response::success($this->service->list(
            (int) $request->route('empresaId'),
            (int) $request->route('periodoId'),
            (string) $request->tenantId(),
        ));
    }

    public function show(Request $request): Response
    {
        return Response::success($this->service->get(
            (int) $request->route('id'),
            (int) $request->route('empresaId'),
            (int) $request->route('periodoId'),
            (string) $request->tenantId(),
        ));
    }

    public function create(Request $request, CreateCompraRequest $validated): Response
    {
        $compra = $this->service->create(
            $validated->validated(),
            (int) $request->route('empresaId'),
            (int) $request->route('periodoId'),
            (string) $request->tenantId(),
        );

        return Response::success($compra, 201);
    }

    public function update(Request $request, UpdateCompraRequest $validated): Response
    {
        $compra = $this->service->update(
            (int) $request->route('id'),
            $validated->validated(),
            (int) $request->route('empresaId'),
            (int) $request->route('periodoId'),
            (string) $request->tenantId(),
        );

        return Response::success($compra);
    }

    public function delete(Request $request): Response
    {
        $this->service->delete(
            (int) $request->route('id'),
            (int) $request->route('empresaId'),
            (int) $request->route('periodoId'),
            (string) $request->tenantId(),
        );

        return Response::success(['message' => 'Compra eliminada.']);
    }

    public function mover(Request $request, MoverComprobanteRequest $validated): Response
    {
        $compra = $this->service->mover(
            (int) $request->route('id'),
            (int) $request->route('empresaId'),
            (int) $request->route('periodoId'),
            (int) $validated->validated()['periodo_destino_id'],
            (string) $request->tenantId(),
        );

        return Response::success($compra);
    }
}
