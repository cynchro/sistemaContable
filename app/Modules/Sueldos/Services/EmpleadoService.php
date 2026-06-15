<?php

namespace App\Modules\Sueldos\Services;

use App\Modules\Compartido\Repositories\EmpresaRepository;
use App\Modules\Sueldos\Repositories\EmpleadoRepository;

/**
 * Legajo de empleados por empresa. Cada operación valida que la empresa pertenezca
 * al tenant (estudio) antes de tocar sus empleados — reusa EmpresaRepository.
 */
class EmpleadoService
{
    public function __construct(
        private EmpleadoRepository $empleados,
        private EmpresaRepository $empresas,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function list(int $empresaId, string $tenantId): array
    {
        $this->assertEmpresa($empresaId, $tenantId);

        return $this->empleados->findAllByEmpresa($empresaId);
    }

    /** @return array<string, mixed> */
    public function get(int $id, int $empresaId, string $tenantId): array
    {
        $this->assertEmpresa($empresaId, $tenantId);

        return $this->empleados->findById($id, $empresaId);
    }

    /**
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function create(array $data, int $empresaId, string $tenantId): array
    {
        $this->assertEmpresa($empresaId, $tenantId);

        return $this->empleados->create($data, $empresaId);
    }

    /**
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function update(int $id, array $data, int $empresaId, string $tenantId): array
    {
        $this->assertEmpresa($empresaId, $tenantId);
        $this->empleados->findById($id, $empresaId);
        $this->empleados->update($id, $data, $empresaId);

        return $this->empleados->findById($id, $empresaId);
    }

    public function delete(int $id, int $empresaId, string $tenantId): void
    {
        $this->assertEmpresa($empresaId, $tenantId);
        $this->empleados->findById($id, $empresaId);
        $this->empleados->delete($id, $empresaId);
    }

    private function assertEmpresa(int $empresaId, string $tenantId): void
    {
        $this->empresas->findById($empresaId, $tenantId);
    }
}
