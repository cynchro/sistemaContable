<?php

namespace App\Modules\Compartido\Controllers;

use App\Support\Request;
use App\Support\Response;
use App\Modules\Compartido\Services\PeriodoService;
use App\Modules\Compartido\Requests\CreatePeriodoRequest;
use App\Modules\Compartido\Requests\UpdatePeriodoRequest;

class PeriodoController
{
    public function __construct(private PeriodoService $service)
    {
    }

    public function index(Request $request): Response
    {
        return Response::success($this->service->list(
            (int) $request->route('empresaId'),
            (string) $request->tenantId(),
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

    public function create(Request $request, CreatePeriodoRequest $validated): Response
    {
        $periodo = $this->service->create(
            $validated->validated(),
            (int) $request->route('empresaId'),
            (string) $request->tenantId(),
        );

        return Response::success($periodo, 201);
    }

    public function update(Request $request, UpdatePeriodoRequest $validated): Response
    {
        $periodo = $this->service->update(
            (int) $request->route('id'),
            $validated->validated(),
            (int) $request->route('empresaId'),
            (string) $request->tenantId(),
        );

        return Response::success($periodo);
    }

    public function delete(Request $request): Response
    {
        $this->service->delete(
            (int) $request->route('id'),
            (int) $request->route('empresaId'),
            (string) $request->tenantId(),
        );

        return Response::success(['message' => 'Período eliminado.']);
    }

    public function cerrar(Request $request): Response
    {
        return Response::success($this->service->cerrar(
            (int) $request->route('id'),
            (int) $request->route('empresaId'),
            (string) $request->tenantId(),
        ));
    }

    public function abrir(Request $request): Response
    {
        return Response::success($this->service->abrir(
            (int) $request->route('id'),
            (int) $request->route('empresaId'),
            (string) $request->tenantId(),
        ));
    }
}
