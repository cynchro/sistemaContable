<?php

namespace App\Modules\Iva\Services;

use App\Modules\Compartido\Repositories\EmpresaRepository;
use App\Modules\Compartido\Repositories\PeriodoRepository;
use App\Modules\Iva\Repositories\MayorRepository;

/**
 * Mayor de cuentas del período (mayorización interna): resumen por cuenta y detalle de
 * los comprobantes de una cuenta. Valida la pertenencia empresa∈tenant y período∈empresa.
 */
class MayorService
{
    public function __construct(
        private MayorRepository $datos,
        private EmpresaRepository $empresas,
        private PeriodoRepository $periodos,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function resumen(int $empresaId, int $periodoId, string $tenantId): array
    {
        $this->empresas->findById($empresaId, $tenantId);
        $this->periodos->findById($periodoId, $empresaId);

        return $this->datos->resumen($periodoId);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function detalle(int $empresaId, int $periodoId, int $cuentaId, string $tenantId): array
    {
        $this->empresas->findById($empresaId, $tenantId);
        $this->periodos->findById($periodoId, $empresaId);

        return $this->datos->detalle($periodoId, $cuentaId);
    }
}
