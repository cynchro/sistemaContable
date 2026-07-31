<?php

namespace App\Modules\Iva\Services;

use App\Support\ReferenceValidator;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Modules\Compartido\Repositories\EmpresaRepository;
use App\Modules\Iva\Repositories\SujetoEmpresaRepository;
use App\Modules\Iva\Repositories\ImputacionContableRepository;

/**
 * Reglas por punto de venta del proveedor (documento "Satélite Visual IVA" §5.4, Pantalla B
 * del panorama de UI — ver documentacion/analisis-satelite-visual-iva.md §8/§10, decisión B2:
 * página aparte en vez de una vista de detalle de proveedor nueva).
 *
 * `ImputacionContableRepository` ya existe desde la Parte 1 (resolverCuenta ya está conectado
 * a CompraService) — acá solo faltaba la capa HTTP para administrar las reglas de PV, igual
 * que le faltaba a VentaClasificacionRepository antes de la Pantalla D.
 */
class ImputacionContableService
{
    public function __construct(
        private ImputacionContableRepository $repo,
        private EmpresaRepository $empresas,
        private SujetoEmpresaRepository $sujetoEmpresas,
        private ReferenceValidator $refs,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function list(int $sujetoId, int $empresaId, string $tenantId): array
    {
        $this->assertProveedorActivo($sujetoId, $empresaId, $tenantId);

        return $this->repo->puntosVenta($empresaId, $sujetoId);
    }

    public function set(int $sujetoId, int $empresaId, array $data, string $tenantId): void
    {
        $this->assertProveedorActivo($sujetoId, $empresaId, $tenantId);
        $pv = trim((string) ($data['punto_venta'] ?? ''));
        $cuentaId = (int) ($data['cuenta_id'] ?? 0);
        if ($pv === '' || $cuentaId === 0) {
            throw new ValidationException(['punto_venta' => ['Indicá el punto de venta y la cuenta.']]);
        }
        $this->refs->validate([
            'cuenta_id' => ['table' => 'cuentas', 'value' => $cuentaId, 'scope' => ['empresa_id' => $empresaId]],
        ]);
        $this->repo->setPuntoVenta($empresaId, $sujetoId, $pv, $cuentaId);
    }

    public function delete(int $sujetoId, int $empresaId, int $id, string $tenantId): void
    {
        $this->assertProveedorActivo($sujetoId, $empresaId, $tenantId);
        $this->repo->deletePuntoVenta($id, $empresaId);
    }

    private function assertProveedorActivo(int $sujetoId, int $empresaId, string $tenantId): void
    {
        $this->empresas->findById($empresaId, $tenantId);
        if (!$this->sujetoEmpresas->existeActivo($empresaId, $sujetoId, 'proveedor')) {
            throw new NotFoundException('Proveedor', $sujetoId);
        }
    }
}
