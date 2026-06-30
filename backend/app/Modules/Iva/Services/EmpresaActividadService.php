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

    /** @return list<array<string, mixed>> */
    public function listAlicuotas(int $empresaId, string $tenantId): array
    {
        $this->empresas->findById($empresaId, $tenantId);

        return $this->repo->alicuotas($empresaId);
    }

    public function setAlicuota(int $empresaId, array $data, string $tenantId): void
    {
        $this->empresas->findById($empresaId, $tenantId);
        $alicuota    = trim((string) ($data['alicuota'] ?? ''));
        $actividadId = (int) ($data['actividad_id'] ?? 0);
        if ($alicuota === '' || !is_numeric($alicuota) || $actividadId === 0) {
            throw new ValidationException(['alicuota' => ['Indicá la alícuota (numérica) y la actividad.']]);
        }
        $this->repo->find($actividadId, $empresaId);
        $this->repo->setAlicuota($empresaId, $alicuota, $actividadId);
    }

    public function deleteAlicuota(int $empresaId, int $id, string $tenantId): void
    {
        $this->empresas->findById($empresaId, $tenantId);
        $this->repo->deleteAlicuota($id, $empresaId);
    }

    /** @return list<array<string, mixed>> */
    public function listReceptores(int $empresaId, string $tenantId): array
    {
        $this->empresas->findById($empresaId, $tenantId);

        return $this->repo->receptores($empresaId);
    }

    public function setReceptor(int $empresaId, array $data, string $tenantId): void
    {
        $this->empresas->findById($empresaId, $tenantId);
        $clienteId   = (int) ($data['cliente_id'] ?? 0);
        $actividadId = (int) ($data['actividad_id'] ?? 0);
        if ($clienteId === 0 || $actividadId === 0) {
            throw new ValidationException(['cliente_id' => ['Indicá el cliente y la actividad.']]);
        }
        $this->repo->find($actividadId, $empresaId);
        $this->repo->setReceptor($empresaId, $clienteId, $actividadId);
    }

    public function deleteReceptor(int $empresaId, int $id, string $tenantId): void
    {
        $this->empresas->findById($empresaId, $tenantId);
        $this->repo->deleteReceptor($id, $empresaId);
    }

    /** @return list<array<string, mixed>> */
    public function listCoeficientes(int $empresaId, string $tenantId): array
    {
        $this->empresas->findById($empresaId, $tenantId);

        return $this->repo->coeficientes($empresaId);
    }

    public function setCoeficiente(int $empresaId, array $data, string $tenantId): void
    {
        $this->empresas->findById($empresaId, $tenantId);
        $actividadId = (int) ($data['actividad_id'] ?? 0);
        $coef        = (string) ($data['coeficiente'] ?? '');
        if ($actividadId === 0 || !is_numeric($coef)) {
            throw new ValidationException(['coeficiente' => ['Indicá la actividad y un coeficiente numérico.']]);
        }
        $this->repo->find($actividadId, $empresaId);
        $this->repo->setCoeficiente($empresaId, $actividadId, $coef);
    }

    public function deleteCoeficiente(int $empresaId, int $id, string $tenantId): void
    {
        $this->empresas->findById($empresaId, $tenantId);
        $this->repo->deleteCoeficiente($id, $empresaId);
    }
}
