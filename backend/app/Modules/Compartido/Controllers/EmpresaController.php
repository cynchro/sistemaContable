<?php

namespace App\Modules\Compartido\Controllers;

use App\Support\Request;
use App\Support\Response;
use App\Support\Roles;
use App\Support\Auth\PermissionChecker;
use App\Modules\Compartido\Services\EmpresaService;
use App\Modules\Compartido\Requests\CreateEmpresaRequest;
use App\Modules\Compartido\Requests\UpdateEmpresaRequest;

class EmpresaController
{
    public function __construct(
        private EmpresaService $service,
        private PermissionChecker $checker,
    ) {
    }

    public function index(Request $request): Response
    {
        $user    = $request->user();
        $esAdmin = $this->checker->allows((int) ($user['rol'] ?? 0), Roles::SUPER_PERMISSION);

        return Response::success($this->service->list(
            (string) $request->tenantId(),
            $user !== null ? (int) ($user['sub'] ?? 0) : null,
            $esAdmin,
        ));
    }

    public function show(Request $request): Response
    {
        return Response::success(
            $this->service->get((int) $request->route('id'), (string) $request->tenantId())
        );
    }

    public function create(Request $request, CreateEmpresaRequest $validated): Response
    {
        $empresa = $this->service->create($validated->validated(), (string) $request->tenantId());

        return Response::success($empresa, 201);
    }

    public function update(Request $request, UpdateEmpresaRequest $validated): Response
    {
        $empresa = $this->service->update(
            (int) $request->route('id'),
            $validated->validated(),
            (string) $request->tenantId(),
        );

        return Response::success($empresa);
    }

    public function delete(Request $request): Response
    {
        $this->service->delete((int) $request->route('id'), (string) $request->tenantId());

        return Response::success(['message' => 'Empresa eliminada.']);
    }
}
