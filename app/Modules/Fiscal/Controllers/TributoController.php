<?php

namespace App\Modules\Fiscal\Controllers;

use App\Support\Request;
use App\Support\Response;
use App\Modules\Fiscal\Services\TributoService;
use App\Modules\Fiscal\Requests\CreateTributoRequest;
use App\Modules\Fiscal\Requests\UpdateTributoRequest;

class TributoController
{
    public function __construct(private TributoService $service)
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

    public function create(Request $request, CreateTributoRequest $validated): Response
    {
        $tributo = $this->service->create($validated->validated(), (string) $request->tenantId());

        return Response::success($tributo, 201);
    }

    public function update(Request $request, UpdateTributoRequest $validated): Response
    {
        $tributo = $this->service->update(
            (int) $request->route('id'),
            $validated->validated(),
            (string) $request->tenantId(),
        );

        return Response::success($tributo);
    }

    public function delete(Request $request): Response
    {
        $this->service->delete((int) $request->route('id'), (string) $request->tenantId());

        return Response::success(['message' => 'Tributo eliminado.']);
    }
}
