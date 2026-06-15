<?php

namespace App\Modules\Admin\Services;

use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Modules\Admin\Repositories\RolRepository;

class RolService
{
    public function __construct(private RolRepository $repository)
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

    public function create(string $nombre, ?int $parentId = null): int
    {
        // Un rol recién creado no tiene descendientes, así que ningún padre
        // existente puede formar ciclo.
        return $this->repository->create($nombre, $parentId);
    }

    public function update(int $id, string $nombre, int $estado, ?int $parentId = null): bool
    {
        if ($parentId !== null) {
            $this->guardAgainstCycle($id, $parentId);
        }

        return $this->repository->update($id, $nombre, $estado, $parentId);
    }

    private function guardAgainstCycle(int $rolId, int $parentId): void
    {
        if ($parentId === $rolId || $this->repository->wouldCreateCycle($rolId, $parentId)) {
            throw new ValidationException(
                ['parent_id' => ['El rol padre crearía un ciclo en la jerarquía.']]
            );
        }
    }
}
