<?php

namespace App\Modules\Iva\Controllers;

use App\Support\Request;
use App\Support\Response;
use App\Modules\Iva\Services\VentaClasificacionService;

/** Motor de clasificación de ventas por punto de venta + tipo de comprobante (Pantalla D). */
class VentaClasificacionController
{
    public function __construct(private VentaClasificacionService $service)
    {
    }

    public function indexPuntoVenta(Request $request): Response
    {
        return Response::success($this->service->listPuntoVenta(
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

        return Response::success(['message' => 'Regla guardada.']);
    }

    public function deletePuntoVenta(Request $request): Response
    {
        $this->service->deletePuntoVenta(
            (int) $request->route('empresaId'),
            (int) $request->route('id'),
            (string) $request->tenantId(),
        );

        return Response::success(['message' => 'Regla eliminada.']);
    }

    public function indexPorTipo(Request $request): Response
    {
        return Response::success($this->service->listPorTipo(
            (int) $request->route('empresaId'),
            (string) $request->tenantId(),
        ));
    }

    public function setPorTipo(Request $request): Response
    {
        $this->service->setPorTipo(
            (int) $request->route('empresaId'),
            $request->all(),
            (string) $request->tenantId(),
        );

        return Response::success(['message' => 'Excepción guardada.']);
    }

    public function deletePorTipo(Request $request): Response
    {
        $this->service->deletePorTipo(
            (int) $request->route('empresaId'),
            (int) $request->route('id'),
            (string) $request->tenantId(),
        );

        return Response::success(['message' => 'Excepción eliminada.']);
    }
}
