<?php

namespace App\Modules\Compartido\Services;

use App\Modules\Compartido\Repositories\CuentaRepository;
use App\Modules\Compartido\Repositories\EmpresaRepository;

/**
 * Plan de cuentas por empresa. Cada operación valida que la empresa pertenezca
 * al tenant (estudio) antes de tocar sus cuentas.
 */
class CuentaService
{
    public function __construct(
        private CuentaRepository $cuentas,
        private EmpresaRepository $empresas,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function list(int $empresaId, string $tenantId): array
    {
        $this->assertEmpresa($empresaId, $tenantId);

        return $this->cuentas->findAllByEmpresa($empresaId);
    }

    /** @return array<string, mixed> */
    public function get(int $id, int $empresaId, string $tenantId): array
    {
        $this->assertEmpresa($empresaId, $tenantId);

        return $this->cuentas->findById($id, $empresaId);
    }

    /**
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function create(array $data, int $empresaId, string $tenantId): array
    {
        $this->assertEmpresa($empresaId, $tenantId);

        return $this->cuentas->create($data, $empresaId);
    }

    /**
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function update(int $id, array $data, int $empresaId, string $tenantId): array
    {
        $this->assertEmpresa($empresaId, $tenantId);
        $this->cuentas->findById($id, $empresaId);
        $this->cuentas->update($id, $data, $empresaId);

        return $this->cuentas->findById($id, $empresaId);
    }

    public function delete(int $id, int $empresaId, string $tenantId): void
    {
        $this->assertEmpresa($empresaId, $tenantId);
        $this->cuentas->findById($id, $empresaId);
        $this->cuentas->delete($id, $empresaId);
    }

    private function assertEmpresa(int $empresaId, string $tenantId): void
    {
        $this->empresas->findById($empresaId, $tenantId);
    }
}
