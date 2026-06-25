<?php

namespace App\Modules\Iva\Controllers;

use App\Support\Request;
use App\Support\Response;
use App\Modules\Iva\Repositories\AuditoriaRepository;

class AuditoriaController
{
    public function __construct(private AuditoriaRepository $auditoria)
    {
    }

    /** Listado paginado de la auditoría de operaciones del tenant (más reciente primero). */
    public function index(Request $request): Response
    {
        $page    = max(1, (int) $request->input('page', 1));
        $perPage = min(200, max(1, (int) $request->input('per_page', 50)));

        return Response::success($this->auditoria->listar((string) $request->tenantId(), $page, $perPage));
    }
}
