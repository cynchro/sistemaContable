<?php

namespace App\Modules\Sueldos\Calc;

use App\Support\Calc\Decimal;
use App\Support\Calc\Contracts\Calculator;

/**
 * Vacaciones (Ley 20.744). Días corridos según antigüedad al 31/12 (art. 150) y valor del
 * día = remuneración mensual / 25 (art. 155). Importe = valor del día × días.
 *
 * Supuestos a confirmar con el contador (ver preguntas.md): escala 14/21/28/35; base =
 * remuneración mensual (hoy el básico del legajo); divisor 25.
 */
final class VacacionesCalculator implements Calculator
{
    public function diasPorAntiguedad(int $anios): int
    {
        if ($anios <= 5) {
            return 14;
        }
        if ($anios <= 10) {
            return 21;
        }
        if ($anios <= 20) {
            return 28;
        }

        return 35;
    }

    /** Valor del día de vacaciones: remuneración mensual / 25 (art. 155 LCT). */
    public function valorDia(int|float|string $remuneracionMensual): string
    {
        return Decimal::of($remuneracionMensual)->div(Decimal::of(25))->round(2)->value(2);
    }

    /** Importe total de las vacaciones: (remuneración / 25) × días. */
    public function importe(int|float|string $remuneracionMensual, int $dias): string
    {
        return Decimal::of($remuneracionMensual)
            ->div(Decimal::of(25))
            ->mul(Decimal::of($dias))
            ->round(2)
            ->value(2);
    }
}
