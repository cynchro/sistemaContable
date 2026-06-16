<?php

namespace App\Modules\Honorarios\Controllers;

use App\Support\Request;
use App\Support\Response;
use App\Modules\Honorarios\Services\ServicioService;
use App\Modules\Honorarios\Requests\CreateServicioRequest;
use App\Modules\Honorarios\Requests\UpdateServicioRequest;

class ServicioController
{
    public function __construct(private ServicioService $service)
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

    public function create(Request $request, CreateServicioRequest $validated): Response
    {
        return Response::success(
            $this->service->create($validated->validated(), (string) $request->tenantId()),
            201,
        );
    }

    public function update(Request $request, UpdateServicioRequest $validated): Response
    {
        return Response::success($this->service->update(
            (int) $request->route('id'),
            $validated->validated(),
            (string) $request->tenantId(),
        ));
    }

    public function delete(Request $request): Response
    {
        $this->service->delete((int) $request->route('id'), (string) $request->tenantId());

        return Response::success(['message' => 'Servicio eliminado.']);
    }
}
