<?php

namespace App\Modules\Iva\Services;

use App\Support\ReferenceValidator;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Modules\Compartido\Repositories\EmpresaRepository;
use App\Modules\Iva\Repositories\SujetoRepository;
use App\Modules\Iva\Repositories\SujetoEmpresaRepository;
use App\Modules\Iva\Repositories\ImputacionContableRepository;

/**
 * Reglas de imputación contable del proveedor (documento "Satélite Visual IVA" §5, Pantalla B
 * del panorama de UI — ver documentacion/analisis-satelite-visual-iva.md §8/§10, página aparte
 * decisión B2). Migración 0051 reescribió el modelo con una capa de "concepto" (tenant-level)
 * para que la regla de punto de venta se cargue UNA vez por proveedor y aplique a todo el
 * estudio — administra las 3 secciones que expone `ProveedorImputacionPage`:
 *
 *  1. Regla GLOBAL de punto de venta del proveedor (aplica a todas las empresas).
 *  2. Excepción de esa regla para UNA empresa puntual.
 *  3. Excepción del concepto por defecto del proveedor para esa empresa.
 *
 * El mapeo concepto→cuenta de una empresa (traduce el catálogo tenant-level al plan de cuentas
 * real) se administra aparte, ver `mapeo*` — no depende de ningún proveedor puntual.
 */
class ImputacionContableService
{
    public function __construct(
        private ImputacionContableRepository $repo,
        private EmpresaRepository $empresas,
        private SujetoRepository $sujetos,
        private SujetoEmpresaRepository $sujetoEmpresas,
        private ReferenceValidator $refs,
    ) {
    }

    // ── 1. Regla global de punto de venta (todas las empresas) ─────────────────────────────

    /** @return list<array<string, mixed>> */
    public function listGlobales(int $sujetoId, int $empresaId, string $tenantId): array
    {
        $this->assertProveedorDelTenant($sujetoId, $tenantId);
        $this->empresas->findById($empresaId, $tenantId);

        return $this->repo->reglasGlobales($empresaId, $sujetoId);
    }

    public function setGlobal(int $sujetoId, int $empresaId, array $data, string $tenantId): void
    {
        $this->assertProveedorDelTenant($sujetoId, $tenantId);
        $this->empresas->findById($empresaId, $tenantId);
        [$pv, $conceptoId] = $this->assertPvYConcepto($data, $tenantId);
        $this->repo->setReglaGlobal($sujetoId, $pv, $conceptoId);
    }

    public function deleteGlobal(int $sujetoId, int $empresaId, int $id, string $tenantId): void
    {
        $this->assertProveedorDelTenant($sujetoId, $tenantId);
        $this->empresas->findById($empresaId, $tenantId);
        $this->repo->deleteReglaGlobal($id, $sujetoId);
    }

    // ── 2. Excepción de punto de venta para esta empresa ────────────────────────────────────

    /** @return list<array<string, mixed>> */
    public function listEmpresa(int $sujetoId, int $empresaId, string $tenantId): array
    {
        $this->assertProveedorActivo($sujetoId, $empresaId, $tenantId);

        return $this->repo->reglasEmpresa($empresaId, $sujetoId);
    }

    public function setEmpresa(int $sujetoId, int $empresaId, array $data, string $tenantId): void
    {
        $this->assertProveedorActivo($sujetoId, $empresaId, $tenantId);
        [$pv, $conceptoId] = $this->assertPvYConcepto($data, $tenantId);
        $this->repo->setReglaEmpresa($empresaId, $sujetoId, $pv, $conceptoId);
    }

    public function deleteEmpresa(int $sujetoId, int $empresaId, int $id, string $tenantId): void
    {
        $this->assertProveedorActivo($sujetoId, $empresaId, $tenantId);
        $this->repo->deleteReglaEmpresa($id, $empresaId);
    }

    // ── 3. Excepción del concepto por defecto para esta empresa ────────────────────────────

    public function getConceptoExcepcion(int $sujetoId, int $empresaId, string $tenantId): ?int
    {
        $this->assertProveedorActivo($sujetoId, $empresaId, $tenantId);

        return $this->sujetoEmpresas->conceptoDe($empresaId, $sujetoId, 'proveedor');
    }

    public function setConceptoExcepcion(int $sujetoId, int $empresaId, array $data, string $tenantId): void
    {
        $this->assertProveedorActivo($sujetoId, $empresaId, $tenantId);
        $conceptoId = $data['concepto_id'] !== null ? (int) $data['concepto_id'] : null;
        $this->refs->validate([
            'concepto_id' => [
                'table' => 'iva_conceptos', 'value' => $conceptoId, 'scope' => ['tenant_id' => $tenantId],
            ],
        ]);
        $this->sujetoEmpresas->setConcepto($empresaId, $sujetoId, 'proveedor', $conceptoId);
    }

    // ── Mapeo concepto→cuenta de esta empresa (no depende de ningún proveedor puntual) ─────

    /** @return list<array<string, mixed>> */
    public function listMapeo(int $empresaId, string $tenantId): array
    {
        $this->empresas->findById($empresaId, $tenantId);

        return $this->repo->mapeoEmpresa($empresaId);
    }

    public function setMapeo(int $empresaId, array $data, string $tenantId): void
    {
        $this->empresas->findById($empresaId, $tenantId);
        $conceptoId = (int) ($data['concepto_id'] ?? 0);
        $cuentaId   = (int) ($data['cuenta_id'] ?? 0);
        if ($conceptoId === 0 || $cuentaId === 0) {
            throw new ValidationException(['concepto_id' => ['Indicá el concepto y la cuenta.']]);
        }
        $this->refs->validate([
            'concepto_id' => [
                'table' => 'iva_conceptos', 'value' => $conceptoId, 'scope' => ['tenant_id' => $tenantId],
            ],
            'cuenta_id' => [
                'table' => 'cuentas', 'value' => $cuentaId, 'scope' => ['empresa_id' => $empresaId],
            ],
        ]);
        $this->repo->setMapeoEmpresa($empresaId, $conceptoId, $cuentaId);
    }

    public function deleteMapeo(int $empresaId, int $conceptoId, string $tenantId): void
    {
        $this->empresas->findById($empresaId, $tenantId);
        $this->repo->deleteMapeoEmpresa($empresaId, $conceptoId);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────────────────

    /** @param array<string, mixed> $data @return array{0: string, 1: int} */
    private function assertPvYConcepto(array $data, string $tenantId): array
    {
        $pv         = trim((string) ($data['punto_venta'] ?? ''));
        $conceptoId = (int) ($data['concepto_id'] ?? 0);
        if ($pv === '' || $conceptoId === 0) {
            throw new ValidationException(['punto_venta' => ['Indicá el punto de venta y el concepto.']]);
        }
        $this->refs->validate([
            'concepto_id' => [
                'table' => 'iva_conceptos', 'value' => $conceptoId, 'scope' => ['tenant_id' => $tenantId],
            ],
        ]);

        return [$pv, $conceptoId];
    }

    /** La regla global no requiere que el proveedor esté activo en ESTA empresa: aplica a todas. */
    private function assertProveedorDelTenant(int $sujetoId, string $tenantId): void
    {
        $this->sujetos->findById($sujetoId, $tenantId);
    }

    private function assertProveedorActivo(int $sujetoId, int $empresaId, string $tenantId): void
    {
        $this->empresas->findById($empresaId, $tenantId);
        if (!$this->sujetoEmpresas->existeActivo($empresaId, $sujetoId, 'proveedor')) {
            throw new NotFoundException('Proveedor', $sujetoId);
        }
    }
}
