<?php

namespace App\Modules\Tenant\Controllers;

use App\Support\Request;
use App\Support\Response;
use App\Modules\Tenant\Services\TenantService;

class TenantController
{
    public function __construct(private TenantService $service)
    {
    }

    public function index(Request $request): Response
    {
        return Response::success($this->service->getAll());
    }

    public function show(Request $request): Response
    {
        $id = (string) $request->route('id');
        return Response::success($this->service->get($id));
    }

    public function create(Request $request): Response
    {
        $nombre = (string) $request->input('nombre', '');
        $id     = $this->service->create($nombre);
        return Response::success(['id' => $id], 201);
    }

    public function update(Request $request): Response
    {
        $id     = (string) $request->route('id');
        $nombre = (string) $request->input('nombre', '');
        $this->service->update($id, $nombre);
        return Response::success(['updated' => true]);
    }

    public function delete(Request $request): Response
    {
        $id = (string) $request->route('id');
        $this->service->delete($id);
        return Response::success(['message' => 'Tenant deleted.']);
    }
}
