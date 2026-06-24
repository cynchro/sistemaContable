<?php

namespace App\Modules\Sueldos\Controllers;

use App\Support\Request;
use App\Support\Response;
use App\Modules\Sueldos\Services\ContribucionService;
use App\Modules\Sueldos\Requests\CreateContribucionRequest;
use App\Modules\Sueldos\Requests\UpdateContribucionRequest;

class ContribucionController
{
    public function __construct(private ContribucionService $service)
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

    public function create(Request $request, CreateContribucionRequest $validated): Response
    {
        $contrib = $this->service->create(
            $validated->validated(),
            (int) $request->route('empresaId'),
            (string) $request->tenantId(),
        );

        return Response::success($contrib, 201);
    }

    public function update(Request $request, UpdateContribucionRequest $validated): Response
    {
        $contrib = $this->service->update(
            (int) $request->route('id'),
            $validated->validated(),
            (int) $request->route('empresaId'),
            (string) $request->tenantId(),
        );

        return Response::success($contrib);
    }

    public function delete(Request $request): Response
    {
        $this->service->delete(
            (int) $request->route('id'),
            (int) $request->route('empresaId'),
            (string) $request->tenantId(),
        );

        return Response::success(['message' => 'Contribución eliminada.']);
    }

    /** Calcula y persiste las contribuciones del empleado en la liquidación. */
    public function calcular(Request $request): Response
    {
        return Response::success($this->service->calcular(
            (int) $request->route('id'),
            (int) $request->route('empleadoId'),
            (int) $request->route('empresaId'),
            (string) $request->tenantId(),
        ));
    }

    public function liquidadas(Request $request): Response
    {
        return Response::success($this->service->liquidadas(
            (int) $request->route('id'),
            (int) $request->route('empleadoId'),
            (int) $request->route('empresaId'),
            (string) $request->tenantId(),
        ));
    }
}
