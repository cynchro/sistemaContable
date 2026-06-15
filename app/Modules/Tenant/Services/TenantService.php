<?php

namespace App\Modules\Tenant\Services;

use App\Modules\Tenant\Repositories\TenantRepository;

class TenantService
{
    public function __construct(private TenantRepository $repository)
    {
    }

    /** @return list<array<string, mixed>> */
    public function getAll(): array
    {
        return $this->repository->findAll();
    }

    /** @return array<string, mixed> */
    public function get(string $id): array
    {
        return $this->repository->findById($id);
    }

    public function create(string $nombre): string
    {
        return $this->repository->create($nombre);
    }

    public function update(string $id, string $nombre): bool
    {
        return $this->repository->update($id, $nombre);
    }

    public function delete(string $id): bool
    {
        return $this->repository->delete($id);
    }
}
