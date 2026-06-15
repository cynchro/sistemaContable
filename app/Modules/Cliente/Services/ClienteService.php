<?php

namespace App\Modules\Cliente\Services;

use App\Modules\Cliente\Repositories\ClienteRepository;

class ClienteService
{
    public function __construct(private ClienteRepository $repository)
    {
    }

    /** @return list<array<string, mixed>> */
    public function getAll(string $tenantId): array
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

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data, string $tenantId): bool
    {
        return $this->repository->update($id, $data, $tenantId);
    }

    public function delete(int $id, string $tenantId): bool
    {
        return $this->repository->delete($id, $tenantId);
    }
}
