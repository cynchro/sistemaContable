<?php

namespace App\Modules\Sueldos\Services;

use App\Modules\Compartido\Repositories\EmpresaRepository;
use App\Modules\Sueldos\Repositories\EmpresaConfigRepository;

/**
 * Configuración de sueldos por empresa. Valida la pertenencia de la empresa al
 * tenant antes de leer/guardar.
 */
class EmpresaConfigService
{
    public function __construct(
        private EmpresaConfigRepository $config,
        private EmpresaRepository $empresas,
    ) {
    }

    /** @return array<string, mixed>|null */
    public function get(int $empresaId, string $tenantId): ?array
    {
        $this->empresas->findById($empresaId, $tenantId);

        return $this->config->find($empresaId);
    }

    /**
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function save(array $data, int $empresaId, string $tenantId): array
    {
        $this->empresas->findById($empresaId, $tenantId);

        return $this->config->upsert($data, $empresaId);
    }
}
