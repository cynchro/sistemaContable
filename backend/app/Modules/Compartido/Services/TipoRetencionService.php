<?php

namespace App\Modules\Compartido\Services;

use App\Support\ReferenceValidator;
use App\Modules\Compartido\Repositories\TipoRetencionRepository;

/**
 * ABM de tipos de retención/percepción propios del estudio (tenant). Las estándar de AFIP
 * (tenant_id NULL) se ven en el listado pero no se pueden editar ni borrar.
 */
class TipoRetencionService
{
    public function __construct(
        private TipoRetencionRepository $tipos,
        private ReferenceValidator $refs,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function list(string $tenantId): array
    {
        return $this->tipos->findAllForTenant($tenantId);
    }

    /** @return array<string, mixed> */
    public function get(int $id, string $tenantId): array
    {
        return $this->tipos->findVisible($id, $tenantId);
    }

    /**
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function create(array $data, string $tenantId): array
    {
        $this->assertProvincia($data);

        return $this->tipos->create($data, $tenantId);
    }

    /**
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function update(int $id, array $data, string $tenantId): array
    {
        $this->tipos->findOwn($id, $tenantId); // 404 si es estándar de AFIP o no es del estudio
        $this->assertProvincia($data);
        $this->tipos->update($id, $data, $tenantId);

        return $this->tipos->findVisible($id, $tenantId);
    }

    public function delete(int $id, string $tenantId): void
    {
        $this->tipos->findOwn($id, $tenantId);
        $this->tipos->delete($id, $tenantId);
    }

    /** @param array<string, mixed> $data */
    private function assertProvincia(array $data): void
    {
        $this->refs->validate([
            'provincia_id' => ['table' => 'provincias', 'value' => $data['provincia_id'] ?? null],
        ]);
    }
}
