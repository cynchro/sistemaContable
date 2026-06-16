<?php

namespace App\Modules\Honorarios\Controllers;

use App\Support\Request;
use App\Support\Response;
use App\Modules\Honorarios\Services\HonorarioService;
use App\Modules\Honorarios\Requests\CreateHonorarioRequest;

class HonorarioController
{
    public function __construct(private HonorarioService $service)
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

    public function create(Request $request, CreateHonorarioRequest $validated): Response
    {
        $honorario = $this->service->create(
            $validated->validated(),
            (int) $request->route('empresaId'),
            (string) $request->tenantId(),
        );

        return Response::success($honorario, 201);
    }

    public function delete(Request $request): Response
    {
        $this->service->delete(
            (int) $request->route('id'),
            (int) $request->route('empresaId'),
            (string) $request->tenantId(),
        );

        return Response::success(['message' => 'Honorario eliminado.']);
    }
}
