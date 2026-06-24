<?php

namespace App\Modules\Sueldos\Controllers;

use App\Support\Request;
use App\Support\Response;
use App\Modules\Sueldos\Services\FamiliarService;
use App\Modules\Sueldos\Requests\CreateFamiliarRequest;
use App\Modules\Sueldos\Requests\UpdateFamiliarRequest;

class FamiliarController
{
    public function __construct(private FamiliarService $service)
    {
    }

    public function index(Request $request): Response
    {
        return Response::success($this->service->list(
            (int) $request->route('empleadoId'),
            (int) $request->route('empresaId'),
            (string) $request->tenantId(),
        ));
    }

    public function show(Request $request): Response
    {
        return Response::success($this->service->get(
            (int) $request->route('id'),
            (int) $request->route('empleadoId'),
            (int) $request->route('empresaId'),
            (string) $request->tenantId(),
        ));
    }

    public function create(Request $request, CreateFamiliarRequest $validated): Response
    {
        $familiar = $this->service->create(
            $validated->validated(),
            (int) $request->route('empleadoId'),
            (int) $request->route('empresaId'),
            (string) $request->tenantId(),
        );

        return Response::success($familiar, 201);
    }

    public function update(Request $request, UpdateFamiliarRequest $validated): Response
    {
        $familiar = $this->service->update(
            (int) $request->route('id'),
            $validated->validated(),
            (int) $request->route('empleadoId'),
            (int) $request->route('empresaId'),
            (string) $request->tenantId(),
        );

        return Response::success($familiar);
    }

    public function delete(Request $request): Response
    {
        $this->service->delete(
            (int) $request->route('id'),
            (int) $request->route('empleadoId'),
            (int) $request->route('empresaId'),
            (string) $request->tenantId(),
        );

        return Response::success(['message' => 'Familiar eliminado.']);
    }
}
