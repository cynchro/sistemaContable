<?php

namespace App\Modules\Sueldos\Services;

use App\Modules\Compartido\Repositories\EmpresaRepository;
use App\Modules\Sueldos\Repositories\EmpleadoRepository;
use App\Modules\Sueldos\Calc\AntiguedadCalculator;
use App\Modules\Sueldos\Calc\VacacionesCalculator;

/**
 * Cálculo de vacaciones de un empleado para un año: días según antigüedad al 31/12 e
 * importe sobre la remuneración del legajo (Ley 20.744). Es un cálculo (no persiste una
 * liquidación de vacaciones todavía).
 */
class VacacionesService
{
    public function __construct(
        private EmpleadoRepository $empleados,
        private EmpresaRepository $empresas,
        private AntiguedadCalculator $antiguedad,
        private VacacionesCalculator $calculator,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function calcular(int $empresaId, int $empleadoId, string $tenantId, ?int $anio = null): array
    {
        $this->empresas->findById($empresaId, $tenantId);
        $empleado = $this->empleados->findById($empleadoId, $empresaId);

        $anio  = $anio ?? (int) date('Y');
        $corte = "{$anio}-12-31";

        $antig = $this->antiguedad->aniosCumplidos(
            isset($empleado['fecha_ingreso']) ? (string) $empleado['fecha_ingreso'] : null,
            $corte,
        );

        $dias  = $this->calculator->diasPorAntiguedad($antig);
        $base  = (string) ($empleado['basico'] ?? '0');

        return [
            'anio'               => $anio,
            'antiguedad_anios'   => $antig,
            'dias'               => $dias,
            'base_remuneracion'  => $base,
            'valor_dia'          => $this->calculator->valorDia($base),
            'importe'            => $this->calculator->importe($base, $dias),
        ];
    }
}
