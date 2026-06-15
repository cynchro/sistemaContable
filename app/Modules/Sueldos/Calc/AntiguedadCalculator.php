<?php

namespace App\Modules\Sueldos\Calc;

use App\Support\Calc\Contracts\Calculator;

/**
 * Antigüedad del empleado en años cumplidos entre el ingreso y la fecha de corte
 * de la liquidación. Las fórmulas del legacy usan ANTIG como porcentaje (p. ej.
 * `BASICO*(ANTIG/100)` = 1% por año), de modo que ANTIG = años cumplidos.
 *
 * Puro: sólo aritmética de fechas.
 */
final class AntiguedadCalculator implements Calculator
{
    /** Años cumplidos entre $fechaIngreso y $fechaCorte (0 si falta dato o es futuro). */
    public function aniosCumplidos(?string $fechaIngreso, ?string $fechaCorte): int
    {
        if ($fechaIngreso === null || $fechaCorte === null) {
            return 0;
        }

        try {
            $ingreso = new \DateTimeImmutable($fechaIngreso);
            $corte   = new \DateTimeImmutable($fechaCorte);
        } catch (\Exception) {
            return 0;
        }

        if ($corte < $ingreso) {
            return 0;
        }

        return $ingreso->diff($corte)->y;
    }
}
