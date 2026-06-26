<?php

namespace App\Modules\Iva\Services;

use App\Exceptions\ValidationException;
use App\Modules\Compartido\Repositories\EmpresaRepository;
use App\Modules\Iva\Repositories\EmpresaActividadRepository;

/**
 * Actividades (NAES) por empresa + mapa de puntos de venta, para la apertura por
 * actividad de la DJ IVA Simple. Valida empresa→tenant y la pertenencia.
 */
class EmpresaActividadService
{
    public function __construct(
        private EmpresaActividadRepository $repo,
        private EmpresaRepository $empresas,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function list(int $empresaId, string $tenantId): array
    {
        $this->empresas->findById($empresaId, $tenantId);

        return $this->repo->all($empresaId);
    }

    /** @return array<string, mixed> */
    public function create(int $empresaId, array $data, string $tenantId): array
    {
        $this->empresas->findById($empresaId, $tenantId);
        $codigo = trim((string) ($data['codigo'] ?? ''));
        if ($codigo === '') {
            throw new ValidationException(['codigo' => ['El código de actividad (NAES) es obligatorio.']]);
        }

        return $this->repo->create($empresaId, $codigo, $data['descripcion'] ?? null);
    }

    public function delete(int $empresaId, int $id, string $tenantId): void
    {
        $this->empresas->findById($empresaId, $tenantId);
        $this->repo->delete($id, $empresaId);
    }

    /** @return list<array<string, mixed>> */
    public function listPuntosVenta(int $empresaId, string $tenantId): array
    {
        $this->empresas->findById($empresaId, $tenantId);

        return $this->repo->puntosVenta($empresaId);
    }

    public function setPuntoVenta(int $empresaId, array $data, string $tenantId): void
    {
        $this->empresas->findById($empresaId, $tenantId);
        $pv = trim((string) ($data['punto_venta'] ?? ''));
        $actividadId = (int) ($data['actividad_id'] ?? 0);
        if ($pv === '' || $actividadId === 0) {
            throw new ValidationException([
                'punto_venta' => ['Indicá el punto de venta y la actividad.'],
            ]);
        }
        // Asegura que la actividad sea de la empresa.
        $this->repo->find($actividadId, $empresaId);
        $this->repo->setPuntoVenta($empresaId, $pv, $actividadId);
    }

    public function deletePuntoVenta(int $empresaId, int $id, string $tenantId): void
    {
        $this->empresas->findById($empresaId, $tenantId);
        $this->repo->deletePuntoVenta($id, $empresaId);
    }
}
