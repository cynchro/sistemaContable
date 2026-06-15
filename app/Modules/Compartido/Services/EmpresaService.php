<?php

namespace App\Modules\Compartido\Services;

use App\Modules\Compartido\Repositories\EmpresaRepository;

/**
 * Reglas de negocio de empresas. Orquesta el repositorio garantizando que toda
 * operación quede acotada al tenant (estudio) que hace la petición.
 */
class EmpresaService
{
    public function __construct(private EmpresaRepository $repository)
    {
    }

    /** @return list<array<string, mixed>> */
    public function list(string $tenantId): array
    {
        return $this->repository->findAll($tenantId);
    }

    /** @return array<string, mixed> */
    public function get(int $id, string $tenantId): array
    {
        return $this->repository->findById($id, $tenantId);
    }

    /**
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function create(array $data, string $tenantId): array
    {
        return $this->repository->create($data, $tenantId);
    }

    /**
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function update(int $id, array $data, string $tenantId): array
    {
        // Valida existencia dentro del tenant (lanza NotFound si no corresponde).
        $this->repository->findById($id, $tenantId);
        $this->repository->update($id, $data, $tenantId);

        return $this->repository->findById($id, $tenantId);
    }

    public function delete(int $id, string $tenantId): void
    {
        $this->repository->findById($id, $tenantId);
        $this->repository->delete($id, $tenantId);
    }
}
