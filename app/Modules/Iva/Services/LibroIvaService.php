<?php

namespace App\Modules\Iva\Services;

use App\Modules\Compartido\Repositories\EmpresaRepository;
use App\Modules\Compartido\Repositories\PeriodoRepository;
use App\Modules\Iva\Calc\LibroIvaCalculator;
use App\Modules\Iva\Repositories\LibroIvaRepository;

/**
 * Totales del período (libro IVA), derivados on-the-fly: el repositorio agrega por
 * signo y la calculadora compone los totales firmados y el saldo de IVA. Valida
 * que la empresa pertenezca al tenant y el período a la empresa.
 */
class LibroIvaService
{
    public function __construct(
        private LibroIvaRepository $libro,
        private EmpresaRepository $empresas,
        private PeriodoRepository $periodos,
        private LibroIvaCalculator $calculator,
    ) {
    }

    /**
     * @return array{
     *   total_ventas: string, total_compras: string,
     *   iva_ventas: string, iva_compras: string, saldo_iva: string
     * }
     */
    public function totales(int $empresaId, int $periodoId, string $tenantId): array
    {
        $this->empresas->findById($empresaId, $tenantId);
        $this->periodos->findById($periodoId, $empresaId);

        return $this->calculator->totalesPeriodo(
            $this->libro->ventasTotalPorSigno($periodoId),
            $this->libro->ventasIvaPorSigno($periodoId),
            $this->libro->comprasTotalPorSigno($periodoId),
            $this->libro->comprasIvaPorSigno($periodoId),
        );
    }
}
