<?php

namespace App\Modules\Iva\Controllers;

use App\Support\Request;
use App\Support\Response;
use App\Modules\Iva\Services\LibroIvaService;

class LibroIvaController
{
    public function __construct(private LibroIvaService $service)
    {
    }

    /** Totales del período: ventas/compras, IVA débito/crédito y saldo. */
    public function totales(Request $request): Response
    {
        return Response::success($this->service->totales(
            (int) $request->route('empresaId'),
            (int) $request->route('periodoId'),
            (string) $request->tenantId(),
        ));
    }

    /** Reintegro de IVA (Factura T) del período — a informar en el aplicativo de ARCA. */
    public function reintegroT(Request $request): Response
    {
        return Response::success($this->service->reintegroFacturaT(
            (int) $request->route('empresaId'),
            (int) $request->route('periodoId'),
            (string) $request->tenantId(),
        ));
    }

    /** Libro IVA detallado: subtotales por condición y alícuota (ventas y compras). */
    public function detalle(Request $request): Response
    {
        return Response::success($this->service->detalle(
            (int) $request->route('empresaId'),
            (int) $request->route('periodoId'),
            (string) $request->tenantId(),
        ));
    }

    /** Declaración jurada de IVA del período (F2002): débito, crédito y saldo. */
    public function ddjj(Request $request): Response
    {
        return Response::success($this->service->declaracion(
            (int) $request->route('empresaId'),
            (int) $request->route('periodoId'),
            (string) $request->tenantId(),
        ));
    }

    /**
     * DDJJ "IVA Simple" del período (F.2051 del Portal IVA, reemplaza al F2002) — preview.
     * Los arrastres de saldo (técnico y libre disponibilidad anteriores) se derivan de la
     * DDJJ del período anterior persistida; pasarlos por query los sobrescribe. Las
     * retenciones/percepciones/pagos sufridos van por query (insumo del período).
     */
    public function ivaSimple(Request $request): Response
    {
        $saldoTecnico = $request->input('saldo_tecnico_anterior');
        $saldoLibre   = $request->input('saldo_libre_disponibilidad_anterior');

        return Response::success($this->service->ivaSimple(
            (int) $request->route('empresaId'),
            (int) $request->route('periodoId'),
            (string) $request->tenantId(),
            $saldoTecnico !== null ? (string) $saldoTecnico : null,
            $saldoLibre !== null ? (string) $saldoLibre : null,
            (string) $request->input('retenciones_percepciones_pagos', '0'),
        ));
    }

    /**
     * Presenta (persiste) la DDJJ IVA Simple del período. Los arrastres se toman del
     * período anterior; en el body sólo van las retenciones/percepciones/pagos sufridos.
     */
    public function presentarIvaSimple(Request $request): Response
    {
        return Response::success($this->service->presentarIvaSimple(
            (int) $request->route('empresaId'),
            (int) $request->route('periodoId'),
            (string) $request->tenantId(),
            (string) $request->input('retenciones_percepciones_pagos', '0'),
        ));
    }
}
