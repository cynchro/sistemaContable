<?php

namespace App\Modules\Sueldos\Controllers;

use App\Support\Request;
use App\Support\Response;
use App\Modules\Sueldos\Services\LiquidacionService;
use App\Modules\Sueldos\Requests\CreateLiquidacionRequest;
use App\Modules\Sueldos\Requests\NovedadesRequest;

class LiquidacionController
{
    public function __construct(private LiquidacionService $service)
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

    public function create(Request $request, CreateLiquidacionRequest $validated): Response
    {
        $liq = $this->service->crear(
            $validated->validated(),
            (int) $request->route('empresaId'),
            (string) $request->tenantId(),
        );

        return Response::success($liq, 201);
    }

    public function delete(Request $request): Response
    {
        $this->service->delete(
            (int) $request->route('id'),
            (int) $request->route('empresaId'),
            (string) $request->tenantId(),
        );

        return Response::success(['message' => 'Liquidación eliminada.']);
    }

    public function novedades(Request $request): Response
    {
        return Response::success($this->service->getNovedades(
            (int) $request->route('id'),
            (int) $request->route('empleadoId'),
            (int) $request->route('empresaId'),
            (string) $request->tenantId(),
        ));
    }

    public function setNovedades(Request $request, NovedadesRequest $validated): Response
    {
        /** @var array<int, mixed> $items */
        $items = (array) $validated->input('novedades', []);

        return Response::success($this->service->setNovedades(
            (int) $request->route('id'),
            (int) $request->route('empleadoId'),
            $items,
            (int) $request->route('empresaId'),
            (string) $request->tenantId(),
        ));
    }

    /** Ejecuta la liquidación del empleado y persiste los recibos. */
    public function liquidar(Request $request): Response
    {
        return Response::success($this->service->liquidar(
            (int) $request->route('id'),
            (int) $request->route('empleadoId'),
            (int) $request->route('empresaId'),
            (string) $request->tenantId(),
        ));
    }

    public function recibo(Request $request): Response
    {
        return Response::success($this->service->recibo(
            (int) $request->route('id'),
            (int) $request->route('empleadoId'),
            (int) $request->route('empresaId'),
            (string) $request->tenantId(),
        ));
    }
}
