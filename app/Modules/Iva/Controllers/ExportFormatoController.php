<?php

namespace App\Modules\Iva\Controllers;

use App\Support\Request;
use App\Support\Response;
use App\Modules\Iva\Services\ExportFormatoService;

/**
 * Formatos de exportación TXT configurables: CRUD por tenant y descarga del subdiario
 * generado con un formato. La validación de los campos vive en el Service
 * ({@see ExportFormatoService}), por eso el create recibe el body crudo.
 */
class ExportFormatoController
{
    public function __construct(private ExportFormatoService $service)
    {
    }

    public function index(Request $request): Response
    {
        return Response::success($this->service->listar((string) $request->tenantId()));
    }

    public function show(Request $request): Response
    {
        return Response::success($this->service->ver(
            (int) $request->route('id'),
            (string) $request->tenantId(),
        ));
    }

    public function create(Request $request): Response
    {
        $formato = $this->service->crear((string) $request->tenantId(), $request->all());

        return Response::success($formato, 201);
    }

    public function delete(Request $request): Response
    {
        $this->service->borrar((int) $request->route('id'), (string) $request->tenantId());

        return Response::success(['deleted' => true]);
    }

    /** Descarga del subdiario (?tipo=ventas|compras) generado con el formato. */
    public function exportar(Request $request): Response
    {
        $archivo = $this->service->exportar(
            (int) $request->route('empresaId'),
            (int) $request->route('periodoId'),
            (string) $request->tenantId(),
            (int) $request->route('formatoId'),
            (string) $request->input('tipo', 'ventas'),
        );

        return Response::download($archivo['contenido'], $archivo['nombre'], 'text/plain');
    }
}
