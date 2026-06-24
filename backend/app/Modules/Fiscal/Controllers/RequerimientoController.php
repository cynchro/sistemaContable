<?php

namespace App\Modules\Fiscal\Controllers;

use App\Support\Request;
use App\Support\Response;
use App\Modules\Fiscal\Services\RequerimientoService;
use App\Modules\Fiscal\Requests\CreateRequerimientoRequest;
use App\Modules\Fiscal\Requests\UpdateRequerimientoRequest;

class RequerimientoController
{
    public function __construct(private RequerimientoService $service)
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

    public function create(Request $request, CreateRequerimientoRequest $validated): Response
    {
        $req = $this->service->create(
            $validated->validated(),
            (int) $request->route('empresaId'),
            (string) $request->tenantId(),
        );

        return Response::success($req, 201);
    }

    public function update(Request $request, UpdateRequerimientoRequest $validated): Response
    {
        $req = $this->service->update(
            (int) $request->route('id'),
            $validated->validated(),
            (int) $request->route('empresaId'),
            (string) $request->tenantId(),
        );

        return Response::success($req);
    }

    public function delete(Request $request): Response
    {
        $this->service->delete(
            (int) $request->route('id'),
            (int) $request->route('empresaId'),
            (string) $request->tenantId(),
        );

        return Response::success(['message' => 'Requerimiento eliminado.']);
    }
}
