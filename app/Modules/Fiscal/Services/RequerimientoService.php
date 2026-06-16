<?php

namespace App\Modules\Fiscal\Services;

use App\Exceptions\ValidationException;
use App\Modules\Compartido\Repositories\EmpresaRepository;
use App\Modules\Fiscal\Repositories\RequerimientoRepository;

/**
 * Requerimientos (de organismos) por empresa (contribuyente). Valida la pertenencia
 * de la empresa al tenant y el estado contra el conjunto conocido.
 */
class RequerimientoService
{
    public const ESTADOS = ['notificado', 'en_proceso', 'presentado', 'archivado'];

    public function __construct(
        private RequerimientoRepository $requerimientos,
        private EmpresaRepository $empresas,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function list(int $empresaId, string $tenantId): array
    {
        $this->assertEmpresa($empresaId, $tenantId);

        return $this->requerimientos->findAllByEmpresa($empresaId);
    }

    /** @return array<string, mixed> */
    public function get(int $id, int $empresaId, string $tenantId): array
    {
        $this->assertEmpresa($empresaId, $tenantId);

        return $this->requerimientos->findById($id, $empresaId);
    }

    /**
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function create(array $data, int $empresaId, string $tenantId): array
    {
        $this->assertEmpresa($empresaId, $tenantId);
        $this->assertEstado($data['estado'] ?? null);

        return $this->requerimientos->create($data, $empresaId);
    }

    /**
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function update(int $id, array $data, int $empresaId, string $tenantId): array
    {
        $this->assertEmpresa($empresaId, $tenantId);
        $this->requerimientos->findById($id, $empresaId);
        $this->assertEstado($data['estado'] ?? null);
        $this->requerimientos->update($id, $data, $empresaId);

        return $this->requerimientos->findById($id, $empresaId);
    }

    public function delete(int $id, int $empresaId, string $tenantId): void
    {
        $this->assertEmpresa($empresaId, $tenantId);
        $this->requerimientos->findById($id, $empresaId);
        $this->requerimientos->delete($id, $empresaId);
    }

    private function assertEstado(mixed $estado): void
    {
        if ($estado !== null && $estado !== '' && !in_array($estado, self::ESTADOS, true)) {
            throw new ValidationException(['estado' => ['Estado de requerimiento inválido.']]);
        }
    }

    private function assertEmpresa(int $empresaId, string $tenantId): void
    {
        $this->empresas->findById($empresaId, $tenantId);
    }
}
