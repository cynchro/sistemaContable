<?php

namespace App\Modules\Compartido\Controllers;

use App\Support\Request;
use App\Support\Response;
use App\Modules\Compartido\Repositories\CatalogoRepository;

/**
 * Endpoints de solo lectura de los catálogos AFIP globales (para poblar selects
 * del front). No requieren tenant: la data es global.
 */
class CatalogoController
{
    public function __construct(private CatalogoRepository $repository)
    {
    }

    /** Todos los catálogos en un solo request (bootstrap del front). */
    public function index(Request $request): Response
    {
        return Response::success($this->repository->all());
    }

    /** Un catálogo puntual por slug (p. ej. /catalogos/tipos-comprobante). */
    public function show(Request $request): Response
    {
        return Response::success(
            $this->repository->listBySlug((string) $request->route('catalogo'))
        );
    }
}
