<?php

namespace App\Modules\Compartido\Controllers;

use App\Support\Cuit;
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

    /**
     * "CUIT único" (informe del cliente 10/08/2026, pedido 3): antes de dar de alta un sujeto
     * (cliente/proveedor) del padrón, el frontend consulta acá si ese CUIT ya es una empresa
     * propia del estudio, para ofrecer reusar esos datos en vez de tipearlos de nuevo. Mismo
     * shape de respuesta que `SigeController::sugerencia`/`PadronController::sugerencia`
     * (`encontrado` + datos), para reusar el hook `useCuitLookup` en el frontend.
     */
    public function buscarPorCuit(Request $request): Response
    {
        $cuit    = Cuit::normalizar((string) $request->route('cuit'));
        $empresa = $this->service->findByCuit((string) $request->tenantId(), $cuit);

        if ($empresa === null) {
            return Response::success(['encontrado' => false, 'cuit' => $cuit]);
        }

        return Response::success([
            'encontrado'       => true,
            'id'               => (int) $empresa['id'],
            'nombre'           => $empresa['nombre'],
            'cuit'             => $empresa['cuit'],
            'domicilio'        => $empresa['domicilio'],
            'localidad'        => $empresa['localidad'],
            'provincia_id'     => $empresa['provincia_id'],
            'telefono'         => $empresa['telefono'],
            'condicion_iva_id' => $empresa['condicion_iva_id'],
        ]);
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
