<?php

namespace App\Modules\Fiscal\Controllers;

use App\Support\Request;
use App\Support\Response;
use App\Modules\Fiscal\Services\VencimientoService;
use App\Modules\Fiscal\Requests\CreateVencimientoRequest;
use App\Modules\Fiscal\Requests\UpdateVencimientoRequest;
use App\Modules\Fiscal\Requests\CambiarEstadoVencimientoRequest;

class VencimientoController
{
    public function __construct(private VencimientoService $service)
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

    public function create(Request $request, CreateVencimientoRequest $validated): Response
    {
        $venc = $this->service->create(
            $validated->validated(),
            (int) $request->route('empresaId'),
            $this->usuarioId($request),
            (string) $request->tenantId(),
        );

        return Response::success($venc, 201);
    }

    public function update(Request $request, UpdateVencimientoRequest $validated): Response
    {
        $venc = $this->service->update(
            (int) $request->route('id'),
            $validated->validated(),
            (int) $request->route('empresaId'),
            $this->usuarioId($request),
            (string) $request->tenantId(),
        );

        return Response::success($venc);
    }

    public function cambiarEstado(Request $request, CambiarEstadoVencimientoRequest $validated): Response
    {
        $venc = $this->service->cambiarEstado(
            (int) $request->route('id'),
            (string) $validated->input('estado'),
            $validated->input('observaciones') !== null ? (string) $validated->input('observaciones') : null,
            (int) $request->route('empresaId'),
            $this->usuarioId($request),
            (string) $request->tenantId(),
        );

        return Response::success($venc);
    }

    public function delete(Request $request): Response
    {
        $this->service->delete(
            (int) $request->route('id'),
            (int) $request->route('empresaId'),
            (string) $request->tenantId(),
        );

        return Response::success(['message' => 'Vencimiento eliminado.']);
    }

    private function usuarioId(Request $request): ?int
    {
        $sub = $request->user()['sub'] ?? null;

        return $sub !== null ? (int) $sub : null;
    }
}
