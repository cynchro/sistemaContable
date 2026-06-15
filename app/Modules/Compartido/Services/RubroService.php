<?php

namespace App\Modules\Compartido\Services;

use App\Modules\Compartido\Repositories\RubroRepository;

/**
 * Rubros por tenant (estudio). Orquesta el repositorio acotando al tenant.
 */
class RubroService
{
    public function __construct(private RubroRepository $repository)
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
