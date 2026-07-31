<?php

namespace App\Modules\Iva\Services;

use App\Support\ReferenceValidator;
use App\Exceptions\ValidationException;
use App\Modules\Compartido\Repositories\EmpresaRepository;
use App\Modules\Iva\Repositories\VentaClasificacionRepository;

/**
 * Motor de clasificación de ventas por punto de venta + tipo de comprobante (documento
 * "Satélite Visual IVA" §4, ver documentacion/analisis-satelite-visual-iva.md §7.1.4/§8
 * Pantalla D). Mismo patrón que EmpresaActividadService (valida empresa→tenant + que la
 * cuenta pertenezca al plan de esa empresa), pero resolviendo cuenta contable en vez de
 * actividad, y sin capa de sujeto (el punto de venta es del propio contribuyente).
 */
class VentaClasificacionService
{
    public function __construct(
        private VentaClasificacionRepository $repo,
        private EmpresaRepository $empresas,
        private ReferenceValidator $refs,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function listPuntoVenta(int $empresaId, string $tenantId): array
    {
        $this->empresas->findById($empresaId, $tenantId);

        return $this->repo->reglasPuntoVenta($empresaId);
    }

    public function setPuntoVenta(int $empresaId, array $data, string $tenantId): void
    {
        $this->empresas->findById($empresaId, $tenantId);
        $pv = trim((string) ($data['punto_venta'] ?? ''));
        $cuentaId = (int) ($data['cuenta_id'] ?? 0);
        if ($pv === '' || $cuentaId === 0) {
            throw new ValidationException(['punto_venta' => ['Indicá el punto de venta y la cuenta.']]);
        }
        $this->assertCuentaDeEmpresa($cuentaId, $empresaId);
        $this->repo->setPuntoVenta($empresaId, $pv, $cuentaId);
    }

    public function deletePuntoVenta(int $empresaId, int $id, string $tenantId): void
    {
        $this->empresas->findById($empresaId, $tenantId);
        $this->repo->deletePuntoVenta($id, $empresaId);
    }

    /** @return list<array<string, mixed>> */
    public function listPorTipo(int $empresaId, string $tenantId): array
    {
        $this->empresas->findById($empresaId, $tenantId);

        return $this->repo->reglasPorTipo($empresaId);
    }

    public function setPorTipo(int $empresaId, array $data, string $tenantId): void
    {
        $this->empresas->findById($empresaId, $tenantId);
        $pv = trim((string) ($data['punto_venta'] ?? ''));
        $tipoComprobanteId = (int) ($data['tipo_comprobante_id'] ?? 0);
        $cuentaId = (int) ($data['cuenta_id'] ?? 0);
        if ($pv === '' || $tipoComprobanteId === 0 || $cuentaId === 0) {
            throw new ValidationException([
                'punto_venta' => ['Indicá el punto de venta, el tipo de comprobante y la cuenta.'],
            ]);
        }
        $this->refs->validate([
            'tipo_comprobante_id' => ['table' => 'tipos_comprobante', 'value' => $tipoComprobanteId],
        ]);
        $this->assertCuentaDeEmpresa($cuentaId, $empresaId);
        $this->repo->setPorTipo($empresaId, $pv, $tipoComprobanteId, $cuentaId);
    }

    public function deletePorTipo(int $empresaId, int $id, string $tenantId): void
    {
        $this->empresas->findById($empresaId, $tenantId);
        $this->repo->deletePorTipo($id, $empresaId);
    }

    private function assertCuentaDeEmpresa(int $cuentaId, int $empresaId): void
    {
        $this->refs->validate([
            'cuenta_id' => ['table' => 'cuentas', 'value' => $cuentaId, 'scope' => ['empresa_id' => $empresaId]],
        ]);
    }
}
