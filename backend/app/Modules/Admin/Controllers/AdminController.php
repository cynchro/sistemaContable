<?php

namespace App\Modules\Admin\Controllers;

use App\Support\Request;
use App\Support\Response;
use App\Modules\Admin\Requests\StoreRolRequest;
use App\Modules\Admin\Requests\UpdateRolRequest;
use App\Modules\Admin\Requests\StorePermisoRequest;
use App\Modules\Admin\Requests\UpdatePermisoRequest;
use App\Modules\Admin\Services\RolService;
use App\Modules\Admin\Services\PermisosService;
use App\Modules\Auth\Services\AuthService;
use App\Modules\Admin\Services\UsuariosService;
use App\Modules\Compartido\Repositories\UsuarioEmpresaRepository;
use App\Modules\Compartido\Repositories\EmpresaRepository;

class AdminController
{
    public function __construct(
        private RolService $rolService,
        private PermisosService $permisosService,
        private AuthService $authService,
        private UsuariosService $usuariosService,
        private UsuarioEmpresaRepository $usuarioEmpresas,
        private EmpresaRepository $empresas,
    ) {
    }

    // ── Roles ─────────────────────────────────────────────────────────────────

    public function indexRoles(Request $request): Response
    {
        return Response::success($this->rolService->getAll());
    }

    public function showRole(Request $request): Response
    {
        $id = (int) $request->route('id');

        return Response::success([
            'rol'                  => $this->rolService->get($id),
            'permisosDisponibles'  => $this->permisosService->getAvailable($id),
            'permisosAsignados'    => $this->permisosService->getOnUse($id),
        ]);
    }

    public function storeRole(StoreRolRequest $validated): Response
    {
        $id = $this->rolService->create(
            $validated->input('nombre'),
            $this->nullableInt($validated->input('parent_id'))
        );

        return Response::success(['id' => $id], 201);
    }

    public function updateRole(Request $request, UpdateRolRequest $validated): Response
    {
        $id = (int) $request->route('id');

        $this->rolService->update(
            $id,
            $validated->input('nombre'),
            (int) $validated->input('estado'),
            $this->nullableInt($validated->input('parent_id'))
        );

        return Response::success(['updated' => true]);
    }

    private function nullableInt(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    public function assignPermisos(Request $request): Response
    {
        $id       = (int) $request->route('id');
        $permisos = (array) $request->input('permisos', []);

        $this->permisosService->asignar($id, $permisos);

        return Response::success(['assigned' => true]);
    }

    public function unassignPermisos(Request $request): Response
    {
        $id       = (int) $request->route('id');
        $permisos = (array) $request->input('permisos', []);

        $this->permisosService->desasignar($id, $permisos);

        return Response::success(['unassigned' => true]);
    }

    // ── Permisos ──────────────────────────────────────────────────────────────

    public function indexPermisos(Request $request): Response
    {
        return Response::success($this->permisosService->getAll());
    }

    public function showPermiso(Request $request): Response
    {
        $id = (int) $request->route('id');

        return Response::success($this->permisosService->get($id));
    }

    public function storePermiso(StorePermisoRequest $validated): Response
    {
        $this->permisosService->createPermiso(
            $validated->input('key'),
            $validated->input('descripcion')
        );

        return Response::success(['created' => true], 201);
    }

    public function updatePermiso(Request $request, UpdatePermisoRequest $validated): Response
    {
        $id = (int) $request->route('id');

        $this->permisosService->updatePermiso(
            $id,
            $validated->input('key'),
            $validated->input('descripcion'),
            (int) $validated->input('estado')
        );

        return Response::success(['updated' => true]);
    }

    // ── Users & Impersonation ─────────────────────────────────────────────────

    public function users(Request $request): Response
    {
        $page     = max(1, (int) $request->input('page', 1));
        $perPage  = max(1, (int) $request->input('perPage', 10));
        $tenantId = $request->tenantId();
        $data     = $this->usuariosService->getAll($page, $perPage, $tenantId);

        return Response::success($data['results'] ?? []);
    }

    /**
     * Empresas "asignadas" a un usuario (WhatsApp con el cliente, 11/08/2026: "me gustaría
     * que de última él pueda ver sus empresas asignadas"). Filtro de visibilidad opcional —
     * ver `EmpresaService::list`.
     */
    public function empresasDeUsuario(Request $request): Response
    {
        return Response::success($this->usuarioEmpresas->empresasDe(
            (int) $request->route('id'),
            (string) $request->tenantId(),
        ));
    }

    /** Reemplaza la lista completa de empresas asignadas al usuario. */
    public function asignarEmpresas(Request $request): Response
    {
        $tenantId   = (string) $request->tenantId();
        $usuarioId  = (int) $request->route('id');
        $empresaIds = array_map('intval', (array) $request->input('empresa_ids', []));

        // Valida que cada empresa sea del tenant del admin (lanza NotFound si no) — evita
        // asignar una empresa ajena aunque alguien mande un id a mano.
        foreach ($empresaIds as $empresaId) {
            $this->empresas->findById($empresaId, $tenantId);
        }

        $this->usuarioEmpresas->asignar($usuarioId, $empresaIds);

        return Response::success(['ok' => true]);
    }

    public function impersonate(Request $request): Response
    {
        $adminId      = (int) ($request->user()['sub'] ?? 0);
        $targetId     = (int) $request->input('userId');
        $adminTenantId = $request->tenantId();
        $token        = $this->authService->impersonate($adminId, $targetId, $adminTenantId);

        return Response::success(['token' => $token]);
    }
}
