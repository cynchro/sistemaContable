<?php

namespace App\Modules\Iva\Controllers;

use App\Support\Request;
use App\Support\Response;
use App\Modules\Iva\Services\EmpresaActividadService;

class EmpresaActividadController
{
    public function __construct(private EmpresaActividadService $service)
    {
    }

    public function index(Request $request): Response
    {
        return Response::success($this->service->list(
            (int) $request->route('empresaId'),
            (string) $request->tenantId(),
        ));
    }

    public function create(Request $request): Response
    {
        return Response::success($this->service->create(
            (int) $request->route('empresaId'),
            $request->all(),
            (string) $request->tenantId(),
        ), 201);
    }

    public function delete(Request $request): Response
    {
        $this->service->delete(
            (int) $request->route('empresaId'),
            (int) $request->route('id'),
            (string) $request->tenantId(),
        );

        return Response::success(['message' => 'Actividad eliminada.']);
    }

    public function indexPuntosVenta(Request $request): Response
    {
        return Response::success($this->service->listPuntosVenta(
            (int) $request->route('empresaId'),
            (string) $request->tenantId(),
        ));
    }

    public function setPuntoVenta(Request $request): Response
    {
        $this->service->setPuntoVenta(
            (int) $request->route('empresaId'),
            $request->all(),
            (string) $request->tenantId(),
        );

        return Response::success(['message' => 'Mapeo guardado.']);
    }

    public function deletePuntoVenta(Request $request): Response
    {
        $this->service->deletePuntoVenta(
            (int) $request->route('empresaId'),
            (int) $request->route('id'),
            (string) $request->tenantId(),
        );

        return Response::success(['message' => 'Mapeo eliminado.']);
    }

    public function indexAlicuotas(Request $request): Response
    {
        return Response::success($this->service->listAlicuotas(
            (int) $request->route('empresaId'),
            (string) $request->tenantId(),
        ));
    }

    public function setAlicuota(Request $request): Response
    {
        $this->service->setAlicuota(
            (int) $request->route('empresaId'),
            $request->all(),
            (string) $request->tenantId(),
        );

        return Response::success(['message' => 'Mapeo guardado.']);
    }

    public function deleteAlicuota(Request $request): Response
    {
        $this->service->deleteAlicuota(
            (int) $request->route('empresaId'),
            (int) $request->route('id'),
            (string) $request->tenantId(),
        );

        return Response::success(['message' => 'Mapeo eliminado.']);
    }

    public function indexReceptores(Request $request): Response
    {
        return Response::success($this->service->listReceptores(
            (int) $request->route('empresaId'),
            (string) $request->tenantId(),
        ));
    }

    public function setReceptor(Request $request): Response
    {
        $this->service->setReceptor(
            (int) $request->route('empresaId'),
            $request->all(),
            (string) $request->tenantId(),
        );

        return Response::success(['message' => 'Mapeo guardado.']);
    }

    public function deleteReceptor(Request $request): Response
    {
        $this->service->deleteReceptor(
            (int) $request->route('empresaId'),
            (int) $request->route('id'),
            (string) $request->tenantId(),
        );

        return Response::success(['message' => 'Mapeo eliminado.']);
    }
}
