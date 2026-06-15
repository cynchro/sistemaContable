<?php

namespace App\Modules\Cliente\Controllers;

use App\Support\Request;
use App\Support\Response;
use App\Modules\Cliente\Services\ClienteService;
use App\Modules\Cliente\Requests\CreateClienteRequest;
use App\Modules\Cliente\Requests\UpdateClienteRequest;

class ClienteController
{
    public function __construct(private ClienteService $service)
    {
    }

    public function index(Request $request): Response
    {
        $tenantId = (string) $request->tenantId();
        return Response::success($this->service->getAll($tenantId));
    }

    public function show(Request $request): Response
    {
        $id       = (int) $request->route('id');
        $tenantId = (string) $request->tenantId();
        return Response::success($this->service->get($id, $tenantId));
    }

    public function create(Request $request, CreateClienteRequest $validated): Response
    {
        $tenantId = (string) $request->tenantId();
        $result   = $this->service->create($validated->all(), $tenantId);
        return Response::success($result, 201);
    }

    public function update(Request $request, UpdateClienteRequest $validated): Response
    {
        $id       = (int) $request->route('id');
        $tenantId = (string) $request->tenantId();
        $result   = $this->service->update($id, $validated->all(), $tenantId);
        return Response::success(['updated' => $result]);
    }

    public function delete(Request $request): Response
    {
        $id       = (int) $request->route('id');
        $tenantId = (string) $request->tenantId();
        $this->service->delete($id, $tenantId);
        return Response::success(['message' => 'Cliente deleted.']);
    }
}
