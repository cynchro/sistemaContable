<?php

namespace App\Modules\Sueldos\Controllers;

use App\Support\Request;
use App\Support\Response;
use App\Modules\Sueldos\Services\EmpresaConfigService;
use App\Modules\Sueldos\Requests\EmpresaConfigRequest;

class EmpresaConfigController
{
    public function __construct(private EmpresaConfigService $service)
    {
    }

    public function show(Request $request): Response
    {
        return Response::success((array) $this->service->get(
            (int) $request->route('empresaId'),
            (string) $request->tenantId(),
        ));
    }

    public function save(Request $request, EmpresaConfigRequest $validated): Response
    {
        return Response::success($this->service->save(
            $validated->validated(),
            (int) $request->route('empresaId'),
            (string) $request->tenantId(),
        ));
    }
}
