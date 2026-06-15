<?php

namespace App\Modules\Usuario\Controllers;

use App\Support\Request;
use App\Support\Response;
use App\Modules\Usuario\Services\UsuariosService;

class UsuarioController
{
    public function __construct(private UsuariosService $service)
    {
    }

    public function index(Request $request): Response
    {
        $page     = max(1, (int) $request->input('page', 1));
        $perPage  = max(1, (int) $request->input('perPage', 10));
        $data     = $this->service->getAll($page, $perPage, $request->tenantId());
        return Response::success($data);
    }

    public function show(Request $request): Response
    {
        $id   = (int) $request->route('id');
        $user = $this->service->get($id, $request->tenantId());
        return Response::success($user);
    }

    public function updateSucursal(Request $request): Response
    {
        $userId     = (int) $request->user()['sub'];
        $sucursalId = (int) $request->route('id');
        $this->service->updateSucursal($userId, $sucursalId);
        return Response::success(['message' => 'Sucursal actualizada.']);
    }

    public function delete(Request $request): Response
    {
        $id = (int) $request->route('id');
        $this->service->delete($id, $request->tenantId());
        return Response::success(['message' => 'Usuario eliminado.']);
    }
}
