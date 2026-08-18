<?php

namespace App\Modules\Compartido\Services;

use App\Modules\Compartido\Repositories\EmpresaRepository;
use App\Modules\Compartido\Repositories\UsuarioEmpresaRepository;

/**
 * Reglas de negocio de empresas. Orquesta el repositorio garantizando que toda
 * operación quede acotada al tenant (estudio) que hace la petición.
 */
class EmpresaService
{
    public function __construct(
        private EmpresaRepository $repository,
        private UsuarioEmpresaRepository $asignaciones,
    ) {
    }

    /**
     * Sin `$usuarioId` o si es admin: todas las empresas del tenant (comportamiento de
     * siempre). Si no es admin y tiene al menos una empresa asignada
     * (`UsuarioEmpresaRepository`, WhatsApp con el cliente 11/08/2026), se filtra a esas — sin
     * asignaciones, sigue viendo todas (la restricción es opcional, no forzada).
     *
     * @return list<array<string, mixed>>
     */
    public function list(string $tenantId, ?int $usuarioId = null, bool $esAdmin = true): array
    {
        if ($esAdmin || $usuarioId === null) {
            return $this->repository->findAll($tenantId);
        }

        $asignadas = $this->asignaciones->idsDe($usuarioId);
        if ($asignadas === []) {
            return $this->repository->findAll($tenantId);
        }

        return $this->repository->findAllPorIds($tenantId, $asignadas);
    }

    /** @return array<string, mixed> */
    public function get(int $id, string $tenantId): array
    {
        return $this->repository->findById($id, $tenantId);
    }

    /** @return array<string, mixed>|null */
    public function findByCuit(string $tenantId, string $cuit): ?array
    {
        return $this->repository->findByCuit($tenantId, $cuit);
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
