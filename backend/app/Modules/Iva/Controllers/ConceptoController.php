<?php

namespace App\Modules\Iva\Controllers;

use App\Support\Request;
use App\Support\Response;
use App\Modules\Iva\Services\ConceptoService;
use App\Modules\Iva\Requests\CreateConceptoRequest;
use App\Modules\Iva\Requests\UpdateConceptoRequest;

class ConceptoController
{
    public function __construct(private ConceptoService $service)
    {
    }

    public function index(Request $request): Response
    {
        return Response::success($this->service->list((string) $request->tenantId()));
    }

    public function show(Request $request): Response
    {
        return Response::success(
            $this->service->get((int) $request->route('id'), (string) $request->tenantId())
        );
    }

    public function create(Request $request, CreateConceptoRequest $validated): Response
    {
        $concepto = $this->service->create($validated->validated(), (string) $request->tenantId());

        return Response::success($concepto, 201);
    }

    public function update(Request $request, UpdateConceptoRequest $validated): Response
    {
        $concepto = $this->service->update(
            (int) $request->route('id'),
            $validated->validated(),
            (string) $request->tenantId(),
        );

        return Response::success($concepto);
    }

    public function delete(Request $request): Response
    {
        $this->service->delete((int) $request->route('id'), (string) $request->tenantId());

        return Response::success(['message' => 'Concepto eliminado.']);
    }
}
