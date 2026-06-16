<?php

namespace App\Modules\Contribuyentes\Controllers;

use App\Support\Request;
use App\Support\Response;
use App\Modules\Contribuyentes\Services\CredencialService;
use App\Modules\Contribuyentes\Requests\CreateCredencialRequest;
use App\Modules\Contribuyentes\Requests\UpdateCredencialRequest;

class CredencialController
{
    public function __construct(private CredencialService $service)
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

    public function create(Request $request, CreateCredencialRequest $validated): Response
    {
        $credencial = $this->service->create(
            $validated->validated(),
            (int) $request->route('empresaId'),
            (string) $request->tenantId(),
        );

        return Response::success($credencial, 201);
    }

    public function update(Request $request, UpdateCredencialRequest $validated): Response
    {
        $credencial = $this->service->update(
            (int) $request->route('id'),
            $validated->validated(),
            (int) $request->route('empresaId'),
            (string) $request->tenantId(),
        );

        return Response::success($credencial);
    }

    public function delete(Request $request): Response
    {
        $this->service->delete(
            (int) $request->route('id'),
            (int) $request->route('empresaId'),
            (string) $request->tenantId(),
        );

        return Response::success(['message' => 'Credencial eliminada.']);
    }
}
