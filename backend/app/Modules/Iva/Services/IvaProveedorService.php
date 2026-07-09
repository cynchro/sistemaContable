<?php

namespace App\Modules\Iva\Services;

use App\Support\ReferenceValidator;
use App\Modules\Compartido\Repositories\EmpresaRepository;
use App\Modules\Iva\Repositories\IvaProveedorRepository;

/**
 * Proveedores por empresa. Cada operación valida que la empresa pertenezca al
 * tenant (estudio) antes de tocar sus proveedores.
 */
class IvaProveedorService
{
    public function __construct(
        private IvaProveedorRepository $proveedores,
        private EmpresaRepository $empresas,
        private ReferenceValidator $refs,
    ) {
    }

    /** @return list<array<string, mixed>> */
    /** @param array{q?: ?string, orden?: ?string} $filtros */
    public function list(int $empresaId, string $tenantId, array $filtros = []): array
    {
        $this->assertEmpresa($empresaId, $tenantId);

        return $this->proveedores->findAllByEmpresa($empresaId, $tenantId, $filtros);
    }

    /** @return array<string, mixed> */
    public function get(int $id, int $empresaId, string $tenantId): array
    {
        $this->assertEmpresa($empresaId, $tenantId);

        return $this->proveedores->findById($id, $empresaId);
    }

    /**
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function create(array $data, int $empresaId, string $tenantId): array
    {
        $this->assertEmpresa($empresaId, $tenantId);
        $this->assertReferencias($data, $empresaId, $tenantId);

        return $this->proveedores->create($data, $empresaId);
    }

    /**
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function update(int $id, array $data, int $empresaId, string $tenantId): array
    {
        $this->assertEmpresa($empresaId, $tenantId);
        $this->proveedores->findById($id, $empresaId);
        $this->assertReferencias($data, $empresaId, $tenantId);
        $this->proveedores->update($id, $data, $empresaId);

        return $this->proveedores->findById($id, $empresaId);
    }

    public function delete(int $id, int $empresaId, string $tenantId): void
    {
        $this->assertEmpresa($empresaId, $tenantId);
        $this->proveedores->findById($id, $empresaId);
        $this->proveedores->delete($id, $empresaId);
    }

    private function assertEmpresa(int $empresaId, string $tenantId): void
    {
        $this->empresas->findById($empresaId, $tenantId);
    }

    /**
     * Valida que las FKs existan y pertenezcan al ámbito: cuenta de la empresa, rubro del
     * tenant; condición de IVA y provincia son catálogos globales. Devuelve 422 (no 500).
     *
     * @param array<string, mixed> $data
     */
    private function assertReferencias(array $data, int $empresaId, string $tenantId): void
    {
        $this->refs->validate([
            'condicion_iva_id' => ['table' => 'condiciones_iva', 'value' => $data['condicion_iva_id'] ?? null],
            'provincia_id'     => ['table' => 'provincias', 'value' => $data['provincia_id'] ?? null],
            'cuenta_id'        => [
                'table' => 'cuentas', 'value' => $data['cuenta_id'] ?? null, 'scope' => ['empresa_id' => $empresaId],
            ],
            'rubro_id'         => [
                'table' => 'rubros', 'value' => $data['rubro_id'] ?? null, 'scope' => ['tenant_id' => $tenantId],
            ],
        ]);
    }
}
