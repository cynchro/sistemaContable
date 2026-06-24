<?php

namespace App\Modules\Admin\Services;

use App\Modules\Admin\Repositories\PermisosRepository;

class PermisosService
{
    public function __construct(private PermisosRepository $repository)
    {
    }

    public function getAll(): array
    {
        return $this->repository->find();
    }

    public function get(int $id): array
    {
        return $this->repository->findById($id);
    }

    public function getAvailable(int $rolId): array
    {
        return $this->repository->findAvailable($rolId);
    }

    public function getOnUse(int $rolId): array
    {
        return $this->repository->findInUse($rolId);
    }

    public function asignar(int $rolId, array $permisos): void
    {
        $this->repository->asignarBatch($rolId, array_map('intval', $permisos));
    }

    public function desasignar(int $rolId, array $permisos): void
    {
        $this->repository->desasignarBatch($rolId, array_map('intval', $permisos));
    }

    public function createPermiso(string $key, string $descripcion): int
    {
        return $this->repository->createPermiso($key, $descripcion);
    }

    public function updatePermiso(int $id, string $key, string $descripcion, int $estado): bool
    {
        return $this->repository->updatePermiso($id, $key, $descripcion, $estado);
    }
}
