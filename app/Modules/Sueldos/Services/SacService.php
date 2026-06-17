<?php

namespace App\Modules\Sueldos\Services;

use App\Exceptions\ValidationException;
use App\Modules\Compartido\Repositories\EmpresaRepository;
use App\Modules\Sueldos\Repositories\EmpleadoRepository;
use App\Modules\Sueldos\Repositories\LiquidacionRepository;
use App\Modules\Sueldos\Calc\SacCalculator;

/**
 * Cálculo del SAC (aguinaldo) de un empleado para un semestre. Toma la mejor remuneración
 * remunerativa del semestre desde el historial de liquidaciones y aplica el SacCalculator.
 * Es un cálculo (no persiste todavía una liquidación de SAC): sirve para previsualizar el
 * importe mientras se confirman los supuestos con el contador.
 */
class SacService
{
    public function __construct(
        private LiquidacionRepository $liquidaciones,
        private EmpleadoRepository $empleados,
        private EmpresaRepository $empresas,
        private SacCalculator $calculator,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function calcular(
        int $empresaId,
        int $empleadoId,
        string $desde,
        string $hasta,
        string $tenantId,
        ?int $diasTrabajados = null,
        int $diasSemestre = 180,
    ): array {
        $this->empresas->findById($empresaId, $tenantId);
        $this->empleados->findById($empleadoId, $empresaId);
        $this->assertPeriodo($desde, 'desde');
        $this->assertPeriodo($hasta, 'hasta');

        $mejor = $this->liquidaciones->mejorRemuneracionSemestre($empresaId, $empleadoId, $desde, $hasta);
        $dias  = $diasTrabajados ?? $diasSemestre;
        $sac   = $this->calculator->calcular($mejor, $dias, $diasSemestre);

        return [
            'periodo_desde'      => $desde,
            'periodo_hasta'      => $hasta,
            'mejor_remuneracion' => $mejor,
            'dias_trabajados'    => $dias,
            'dias_semestre'      => $diasSemestre,
            'sac'                => $sac,
        ];
    }

    private function assertPeriodo(string $periodo, string $campo): void
    {
        if (!preg_match('/^\d{4}-\d{2}$/', $periodo)) {
            throw new ValidationException([$campo => ["El período {$campo} debe tener formato YYYY-MM."]]);
        }
    }
}
