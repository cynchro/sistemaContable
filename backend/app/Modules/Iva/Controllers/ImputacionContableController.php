<?php

namespace App\Modules\Iva\Controllers;

use App\Support\Request;
use App\Support\Response;
use App\Modules\Iva\Services\ImputacionContableService;

/**
 * Reglas de imputación contable del proveedor (Pantalla B, página aparte — decisión B2). Tres
 * secciones (regla global de PV, excepción de PV por empresa, excepción del concepto por
 * defecto) más el mapeo concepto→cuenta de la empresa — ver ImputacionContableService.
 */
class ImputacionContableController
{
    public function __construct(private ImputacionContableService $service)
    {
    }

    // ── 1. Regla global de punto de venta ───────────────────────────────────────────────────

    public function indexGlobal(Request $request): Response
    {
        return Response::success($this->service->listGlobales(
            (int) $request->route('proveedorId'),
            (int) $request->route('empresaId'),
            (string) $request->tenantId(),
        ));
    }

    public function storeGlobal(Request $request): Response
    {
        $this->service->setGlobal(
            (int) $request->route('proveedorId'),
            (int) $request->route('empresaId'),
            $request->all(),
            (string) $request->tenantId(),
        );

        return Response::success(['message' => 'Regla global guardada.']);
    }

    public function deleteGlobal(Request $request): Response
    {
        $this->service->deleteGlobal(
            (int) $request->route('proveedorId'),
            (int) $request->route('empresaId'),
            (int) $request->route('id'),
            (string) $request->tenantId(),
        );

        return Response::success(['message' => 'Regla global eliminada.']);
    }

    // ── 2. Excepción de punto de venta por empresa ──────────────────────────────────────────

    public function indexEmpresa(Request $request): Response
    {
        return Response::success($this->service->listEmpresa(
            (int) $request->route('proveedorId'),
            (int) $request->route('empresaId'),
            (string) $request->tenantId(),
        ));
    }

    public function storeEmpresa(Request $request): Response
    {
        $this->service->setEmpresa(
            (int) $request->route('proveedorId'),
            (int) $request->route('empresaId'),
            $request->all(),
            (string) $request->tenantId(),
        );

        return Response::success(['message' => 'Excepción guardada.']);
    }

    public function deleteEmpresa(Request $request): Response
    {
        $this->service->deleteEmpresa(
            (int) $request->route('proveedorId'),
            (int) $request->route('empresaId'),
            (int) $request->route('id'),
            (string) $request->tenantId(),
        );

        return Response::success(['message' => 'Excepción eliminada.']);
    }

    // ── 3. Excepción del concepto por defecto para esta empresa ─────────────────────────────

    public function showConceptoDefault(Request $request): Response
    {
        $conceptoId = $this->service->getConceptoExcepcion(
            (int) $request->route('proveedorId'),
            (int) $request->route('empresaId'),
            (string) $request->tenantId(),
        );

        return Response::success(['concepto_id' => $conceptoId]);
    }

    public function updateConceptoDefault(Request $request): Response
    {
        $this->service->setConceptoExcepcion(
            (int) $request->route('proveedorId'),
            (int) $request->route('empresaId'),
            $request->all(),
            (string) $request->tenantId(),
        );

        return Response::success(['message' => 'Excepción de concepto actualizada.']);
    }

    // ── Mapeo concepto→cuenta de la empresa ─────────────────────────────────────────────────

    public function indexMapeo(Request $request): Response
    {
        return Response::success($this->service->listMapeo(
            (int) $request->route('empresaId'),
            (string) $request->tenantId(),
        ));
    }

    public function storeMapeo(Request $request): Response
    {
        $this->service->setMapeo(
            (int) $request->route('empresaId'),
            $request->all(),
            (string) $request->tenantId(),
        );

        return Response::success(['message' => 'Mapeo guardado.']);
    }

    public function deleteMapeo(Request $request): Response
    {
        $this->service->deleteMapeo(
            (int) $request->route('empresaId'),
            (int) $request->route('conceptoId'),
            (string) $request->tenantId(),
        );

        return Response::success(['message' => 'Mapeo eliminado.']);
    }
}
