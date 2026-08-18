<?php

namespace App\Modules\Compartido\Controllers;

use App\Support\Request;
use App\Support\Response;
use App\Support\Roles;
use App\Support\Auth\PermissionChecker;
use App\Modules\Compartido\Services\EmpresaLockService;

class EmpresaLockController
{
    public function __construct(
        private EmpresaLockService $service,
        private PermissionChecker $checker,
    ) {
    }

    public function ocupar(Request $request): Response
    {
        $user = $request->user();
        $esAdmin = $this->checker->allows((int) ($user['rol'] ?? 0), Roles::SUPER_PERMISSION);

        return Response::success($this->service->ocupar(
            (int) $request->route('id'),
            (string) $request->tenantId(),
            (int) ($user['sub'] ?? 0),
            $esAdmin,
        ));
    }

    public function ping(Request $request): Response
    {
        $user = $request->user();
        $this->service->ping((int) $request->route('id'), (int) ($user['sub'] ?? 0));

        return Response::success(['ok' => true]);
    }

    public function liberar(Request $request): Response
    {
        $user = $request->user();
        $this->service->liberar((int) $request->route('id'), (int) ($user['sub'] ?? 0));

        return Response::success(['ok' => true]);
    }
}
