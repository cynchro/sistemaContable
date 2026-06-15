<?php

namespace App\Modules\Sueldos\Controllers;

use App\Support\Request;
use App\Support\Response;
use App\Modules\Sueldos\Services\EmpleadoService;
use App\Modules\Sueldos\Requests\CreateEmpleadoRequest;
use App\Modules\Sueldos\Requests\UpdateEmpleadoRequest;

class EmpleadoController
{
    public function __construct(private EmpleadoService $service)
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

    public function create(Request $request, CreateEmpleadoRequest $validated): Response
    {
        $empleado = $this->service->create(
            $validated->validated(),
            (int) $request->route('empresaId'),
            (string) $request->tenantId(),
        );

        return Response::success($empleado, 201);
    }

    public function update(Request $request, UpdateEmpleadoRequest $validated): Response
    {
        $empleado = $this->service->update(
            (int) $request->route('id'),
            $validated->validated(),
            (int) $request->route('empresaId'),
            (string) $request->tenantId(),
        );

        return Response::success($empleado);
    }

    public function delete(Request $request): Response
    {
        $this->service->delete(
            (int) $request->route('id'),
            (int) $request->route('empresaId'),
            (string) $request->tenantId(),
        );

        return Response::success(['message' => 'Empleado eliminado.']);
    }
}
