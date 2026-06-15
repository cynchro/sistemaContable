<?php

namespace App\Modules\Usuario\Services;

use App\Modules\Usuario\Repositories\UsuariosRepository;

class UsuariosService
{
    public function __construct(private UsuariosRepository $repository)
    {
    }

    public function getAll(int $page = 1, int $perPage = 10, ?string $tenantId = null): array
    {
        return $this->repository->find($page, $perPage, $tenantId);
    }

    public function get(int $id, ?string $tenantId = null): array
    {
        return $this->repository->findById($id, $tenantId);
    }

    public function updateSucursal(int $userId, int $sucursalId): bool
    {
        return $this->repository->updateSucursal($userId, $sucursalId);
    }

    public function delete(int $id, ?string $tenantId = null): bool
    {
        return $this->repository->delete($id, $tenantId);
    }
}
