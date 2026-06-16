<?php

namespace App\Modules\Tareas\Controllers;

use App\Support\Request;
use App\Support\Response;
use App\Modules\Tareas\Services\TareaTipoService;
use App\Modules\Tareas\Requests\CreateTareaTipoRequest;
use App\Modules\Tareas\Requests\UpdateTareaTipoRequest;

class TareaTipoController
{
    public function __construct(private TareaTipoService $service)
    {
    }

    public function index(Request $request): Response
    {
        return Response::success($this->service->list((string) $request->tenantId()));
    }

    public function show(Request $request): Response
    {
        return Response::success(
            $this->service->get((int) $request->route('id'), (string) $request->tenantId())
        );
    }

    public function create(Request $request, CreateTareaTipoRequest $validated): Response
    {
        $tipo = $this->service->create($validated->validated(), (string) $request->tenantId());

        return Response::success($tipo, 201);
    }

    public function update(Request $request, UpdateTareaTipoRequest $validated): Response
    {
        $tipo = $this->service->update(
            (int) $request->route('id'),
            $validated->validated(),
            (string) $request->tenantId(),
        );

        return Response::success($tipo);
    }

    public function delete(Request $request): Response
    {
        $this->service->delete((int) $request->route('id'), (string) $request->tenantId());

        return Response::success(['message' => 'Tipo de tarea eliminado.']);
    }
}
