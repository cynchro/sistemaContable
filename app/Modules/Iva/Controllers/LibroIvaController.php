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
     * DDJJ "IVA Simple" del período (F.2051 del Portal IVA, reemplaza al F2002).
     * Los arrastres se pasan por query: saldo_tecnico_anterior,
     * saldo_libre_disponibilidad_anterior, retenciones_percepciones_pagos.
     */
    public function ivaSimple(Request $request): Response
    {
        return Response::success($this->service->ivaSimple(
            (int) $request->route('empresaId'),
            (int) $request->route('periodoId'),
            (string) $request->tenantId(),
            (string) $request->input('saldo_tecnico_anterior', '0'),
            (string) $request->input('saldo_libre_disponibilidad_anterior', '0'),
            (string) $request->input('retenciones_percepciones_pagos', '0'),
        ));
    }
}
