<?php

namespace App\Modules\Sueldos\Calc;

use App\Support\Calc\Decimal;
use App\Support\Calc\Contracts\Calculator;

/**
 * Sueldo Anual Complementario (SAC / aguinaldo). Por Ley 23.041 se calcula como el
 * 50% de la mayor remuneración mensual devengada en el semestre. Si el empleado no
 * trabajó el semestre completo, se prorratea por días trabajados.
 *
 * Supuestos a confirmar con el contador (ver preguntas.md): base = mejor remuneración
 * REMUNERATIVA del semestre; proporcional por días/180.
 */
final class SacCalculator implements Calculator
{
    /**
     * @param  int|float|string $mejorRemuneracion mayor remuneración remunerativa del semestre
     * @return string  importe del SAC (2 decimales)
     */
    public function calcular(
        int|float|string $mejorRemuneracion,
        int $diasTrabajados = 180,
        int $diasSemestre = 180,
    ): string {
        $medio = Decimal::of($mejorRemuneracion)->percentage(Decimal::of(50)); // 50%

        if ($diasSemestre <= 0 || $diasTrabajados >= $diasSemestre) {
            return $medio->round(2)->value(2);
        }

        return $medio->mul(Decimal::of($diasTrabajados))
            ->div(Decimal::of($diasSemestre))
            ->round(2)
            ->value(2);
    }
}
