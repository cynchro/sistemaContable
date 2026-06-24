<?php

namespace App\Modules\Contribuyentes\Controllers;

use App\Support\Request;
use App\Support\Response;
use App\Modules\Contribuyentes\Services\SocioService;
use App\Modules\Contribuyentes\Requests\CreateSocioRequest;
use App\Modules\Contribuyentes\Requests\UpdateSocioRequest;

class SocioController
{
    public function __construct(private SocioService $service)
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

    public function create(Request $request, CreateSocioRequest $validated): Response
    {
        $socio = $this->service->create(
            $validated->validated(),
            (int) $request->route('empresaId'),
            (string) $request->tenantId(),
        );

        return Response::success($socio, 201);
    }

    public function update(Request $request, UpdateSocioRequest $validated): Response
    {
        $socio = $this->service->update(
            (int) $request->route('id'),
            $validated->validated(),
            (int) $request->route('empresaId'),
            (string) $request->tenantId(),
        );

        return Response::success($socio);
    }

    public function delete(Request $request): Response
    {
        $this->service->delete(
            (int) $request->route('id'),
            (int) $request->route('empresaId'),
            (string) $request->tenantId(),
        );

        return Response::success(['message' => 'Socio eliminado.']);
    }
}
