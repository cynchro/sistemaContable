<?php

namespace App\Modules\Iva\Controllers;

use App\Support\Request;
use App\Support\Response;
use App\Modules\Iva\Services\ReporteIvaService;

class ReporteIvaController
{
    public function __construct(private ReporteIvaService $service)
    {
    }

    /** Subdiario / Libro IVA Ventas del período (comprobantes + totales). */
    public function ventas(Request $request): Response
    {
        return Response::success($this->service->libroVentas(
            (int) $request->route('empresaId'),
            (int) $request->route('periodoId'),
            (string) $request->tenantId(),
        ));
    }

    /** Subdiario / Libro IVA Compras del período (comprobantes + totales). */
    public function compras(Request $request): Response
    {
        return Response::success($this->service->libroCompras(
            (int) $request->route('empresaId'),
            (int) $request->route('periodoId'),
            (string) $request->tenantId(),
        ));
    }

    /** Descarga el subdiario de ventas como CSV/TXT (?formato=csv|txt). */
    public function exportarVentas(Request $request): Response
    {
        [$delim, $ext, $mime] = $this->formato($request);
        $periodoId = (int) $request->route('periodoId');

        $csv = $this->service->exportarVentas(
            (int) $request->route('empresaId'),
            $periodoId,
            (string) $request->tenantId(),
            $delim,
        );

        return Response::download($csv, "libro-ventas-{$periodoId}.{$ext}", $mime);
    }

    /** Descarga el subdiario de compras como CSV/TXT (?formato=csv|txt). */
    public function exportarCompras(Request $request): Response
    {
        [$delim, $ext, $mime] = $this->formato($request);
        $periodoId = (int) $request->route('periodoId');

        $csv = $this->service->exportarCompras(
            (int) $request->route('empresaId'),
            $periodoId,
            (string) $request->tenantId(),
            $delim,
        );

        return Response::download($csv, "libro-compras-{$periodoId}.{$ext}", $mime);
    }

    /**
     * Resuelve el formato pedido. csv = coma/.csv; txt = punto y coma/.txt.
     *
     * @return array{0: string, 1: string, 2: string} [delimitador, extensión, mime]
     */
    private function formato(Request $request): array
    {
        return $request->input('formato') === 'txt'
            ? [';', 'txt', 'text/plain; charset=utf-8']
            : [',', 'csv', 'text/csv; charset=utf-8'];
    }
}
