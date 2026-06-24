<?php

namespace App\Modules\Sueldos\Services;

use App\Modules\Compartido\Repositories\EmpresaRepository;
use App\Modules\Sueldos\Repositories\EmpleadoRepository;
use App\Modules\Sueldos\Repositories\FamiliarRepository;

/**
 * Grupo familiar de un empleado. Valida que el empleado pertenezca a la empresa y
 * la empresa al tenant antes de operar.
 */
class FamiliarService
{
    public function __construct(
        private FamiliarRepository $familiares,
        private EmpleadoRepository $empleados,
        private EmpresaRepository $empresas,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function list(int $empleadoId, int $empresaId, string $tenantId): array
    {
        $this->assertEmpleado($empleadoId, $empresaId, $tenantId);

        return $this->familiares->findAllByEmpleado($empleadoId);
    }

    /** @return array<string, mixed> */
    public function get(int $id, int $empleadoId, int $empresaId, string $tenantId): array
    {
        $this->assertEmpleado($empleadoId, $empresaId, $tenantId);

        return $this->familiares->findById($id, $empleadoId);
    }

    /**
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function create(array $data, int $empleadoId, int $empresaId, string $tenantId): array
    {
        $this->assertEmpleado($empleadoId, $empresaId, $tenantId);

        return $this->familiares->create($data, $empleadoId);
    }

    /**
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function update(int $id, array $data, int $empleadoId, int $empresaId, string $tenantId): array
    {
        $this->assertEmpleado($empleadoId, $empresaId, $tenantId);
        $this->familiares->findById($id, $empleadoId);
        $this->familiares->update($id, $data, $empleadoId);

        return $this->familiares->findById($id, $empleadoId);
    }

    public function delete(int $id, int $empleadoId, int $empresaId, string $tenantId): void
    {
        $this->assertEmpleado($empleadoId, $empresaId, $tenantId);
        $this->familiares->findById($id, $empleadoId);
        $this->familiares->delete($id, $empleadoId);
    }

    private function assertEmpleado(int $empleadoId, int $empresaId, string $tenantId): void
    {
        $this->empresas->findById($empresaId, $tenantId);
        $this->empleados->findById($empleadoId, $empresaId);
    }
}
