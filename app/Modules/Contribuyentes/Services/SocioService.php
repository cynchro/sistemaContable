<?php

namespace App\Modules\Contribuyentes\Services;

use App\Modules\Compartido\Repositories\EmpresaRepository;
use App\Modules\Contribuyentes\Repositories\SocioRepository;

/**
 * Socios/integrantes de un contribuyente (empresa). Valida la pertenencia de la
 * empresa al tenant antes de operar.
 */
class SocioService
{
    public function __construct(
        private SocioRepository $socios,
        private EmpresaRepository $empresas,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function list(int $empresaId, string $tenantId): array
    {
        $this->assertEmpresa($empresaId, $tenantId);

        return $this->socios->findAllByEmpresa($empresaId);
    }

    /** @return array<string, mixed> */
    public function get(int $id, int $empresaId, string $tenantId): array
    {
        $this->assertEmpresa($empresaId, $tenantId);

        return $this->socios->findById($id, $empresaId);
    }

    /**
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function create(array $data, int $empresaId, string $tenantId): array
    {
        $this->assertEmpresa($empresaId, $tenantId);

        return $this->socios->create($data, $empresaId);
    }

    /**
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function update(int $id, array $data, int $empresaId, string $tenantId): array
    {
        $this->assertEmpresa($empresaId, $tenantId);
        $this->socios->findById($id, $empresaId);
        $this->socios->update($id, $data, $empresaId);

        return $this->socios->findById($id, $empresaId);
    }

    public function delete(int $id, int $empresaId, string $tenantId): void
    {
        $this->assertEmpresa($empresaId, $tenantId);
        $this->socios->findById($id, $empresaId);
        $this->socios->delete($id, $empresaId);
    }

    private function assertEmpresa(int $empresaId, string $tenantId): void
    {
        $this->empresas->findById($empresaId, $tenantId);
    }
}
