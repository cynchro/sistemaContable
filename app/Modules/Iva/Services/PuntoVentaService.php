<?php

namespace App\Modules\Iva\Services;

use App\Exceptions\ConflictException;
use App\Modules\Compartido\Repositories\EmpresaRepository;
use App\Modules\Iva\Repositories\PuntoVentaRepository;

/**
 * ABM de puntos de venta de una empresa (numeración de factura electrónica). Valida
 * la pertenencia de la empresa al tenant y la unicidad del número por empresa.
 */
class PuntoVentaService
{
    public function __construct(
        private PuntoVentaRepository $puntos,
        private EmpresaRepository $empresas,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function list(int $empresaId, string $tenantId): array
    {
        $this->empresas->findById($empresaId, $tenantId);

        return $this->puntos->findAllByEmpresa($empresaId);
    }

    /** @return array<string, mixed> */
    public function get(int $id, int $empresaId, string $tenantId): array
    {
        $this->empresas->findById($empresaId, $tenantId);

        return $this->puntos->findById($id, $empresaId);
    }

    /**
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function create(array $data, int $empresaId, string $tenantId): array
    {
        $this->empresas->findById($empresaId, $tenantId);
        $this->assertNumeroLibre((int) $data['numero'], $empresaId);

        return $this->puntos->create($data, $empresaId);
    }

    /**
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function update(int $id, array $data, int $empresaId, string $tenantId): array
    {
        $this->empresas->findById($empresaId, $tenantId);
        $this->puntos->findById($id, $empresaId);

        if (isset($data['numero'])) {
            $this->assertNumeroLibre((int) $data['numero'], $empresaId, $id);
        }

        $this->puntos->update($id, $data, $empresaId);

        return $this->puntos->findById($id, $empresaId);
    }

    public function delete(int $id, int $empresaId, string $tenantId): void
    {
        $this->empresas->findById($empresaId, $tenantId);
        $this->puntos->findById($id, $empresaId);
        $this->puntos->delete($id, $empresaId);
    }

    private function assertNumeroLibre(int $numero, int $empresaId, ?int $exceptoId = null): void
    {
        $existente = $this->puntos->findByNumero($numero, $empresaId);

        if ($existente !== null && (int) $existente['id'] !== $exceptoId) {
            throw new ConflictException("Ya existe el punto de venta {$numero} en esta empresa.");
        }
    }
}
