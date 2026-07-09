<?php

namespace App\Modules\Sueldos\Calc;

use App\Support\Calc\Decimal;
use App\Support\Calc\Contracts\Calculator;

/**
 * Calcula las contribuciones patronales de un empleado sobre la base imponible del
 * recibo, según CONTRIBUCIONES_RECIBO_DETALLE del legacy:
 *   base = total remunerativo (+ no remunerativo si la contribución lo incluye)
 *   base' = topes(base) si aplica_topes   →  se acota a [tope_min, tope_max]
 *   base'' = max(0, base' − detracción) si aplica_detraccion   (Dto 99/2019)
 *   importe_total = base'' · porcentaje / 100 + importe_fijo
 *
 * Detracción y topes son la respuesta B6 (manual Contribuciones Patronales v5.80):
 *  - `detraccion` es un monto a nivel de empresa (se pasa por parámetro) y solo se aplica
 *    a las contribuciones marcadas con `aplica_detraccion = 'S'` (las de SIPA).
 *  - los topes se acotan por contribución cuando `aplica_topes = 'S'` (con `tope_min`/`tope_max`).
 * Pura.
 */
final class ContribucionCalculator implements Calculator
{
    /**
     * @param  array<string, mixed>       $base           ['remunerativo' => , 'no_remunerativo' => ]
     * @param  list<array<string, mixed>> $contribuciones definiciones (porcentaje, importe_fijo,
     *                                    incluye_norem, aplica_detraccion, aplica_topes, tope_min, tope_max)
     * @param  mixed                      $detraccion     monto de la detracción vigente (empresa); 0 si no hay
     * @return array{lineas: list<array<string, mixed>>, total: string}
     */
    public function calcular(array $base, array $contribuciones, mixed $detraccion = 0): array
    {
        $rem       = Decimal::of($base['remunerativo'] ?? 0);
        $noRem     = Decimal::of($base['no_remunerativo'] ?? 0);
        $detrac    = Decimal::of($detraccion ?: 0);

        $total  = Decimal::zero();
        $lineas = [];

        foreach ($contribuciones as $contrib) {
            $baseImp = ($contrib['incluye_norem'] ?? 'N') === 'S' ? $rem->add($noRem) : $rem;

            // Topes: acotar la base a [tope_min, tope_max] si la contribución los aplica.
            if (($contrib['aplica_topes'] ?? 'N') === 'S') {
                $baseImp = $this->aplicarTopes($baseImp, $contrib['tope_min'] ?? null, $contrib['tope_max'] ?? null);
            }

            // Detracción (SIPA): restar el monto de la empresa, sin bajar de cero.
            $detraccionAplicada = Decimal::zero();
            if (($contrib['aplica_detraccion'] ?? 'N') === 'S' && $detrac->compareTo(Decimal::zero()) > 0) {
                $detraccionAplicada = $detrac->compareTo($baseImp) > 0 ? $baseImp : $detrac;
                $baseImp            = $baseImp->sub($detraccionAplicada);
            }

            $porc = Decimal::of($contrib['porcentaje'] ?? 0);
            $fijo = Decimal::of($contrib['importe_fijo'] ?? 0);

            $importe = $baseImp->percentage($porc)->add($fijo)->round(2);
            $total   = $total->add($importe);

            $lineas[] = [
                'contribucion_id' => $contrib['id'] ?? null,
                'descripcion'     => $contrib['descripcion'] ?? null,
                'base_imponible'  => $baseImp->value(2),
                'detraccion'      => $detraccionAplicada->value(2),
                'porcentaje'      => $porc->value(3),
                'importe_fijo'    => $fijo->value(2),
                'importe_total'   => $importe->value(2),
            ];
        }

        return ['lineas' => $lineas, 'total' => $total->value(2)];
    }

    /** Acota la base al rango [min, max] (cualquiera puede ser null = sin ese límite). */
    private function aplicarTopes(Decimal $base, mixed $min, mixed $max): Decimal
    {
        if ($min !== null && $min !== '') {
            $topeMin = Decimal::of($min);
            if ($base->compareTo($topeMin) < 0) {
                $base = $topeMin;
            }
        }
        if ($max !== null && $max !== '') {
            $topeMax = Decimal::of($max);
            if ($base->compareTo($topeMax) > 0) {
                $base = $topeMax;
            }
        }

        return $base;
    }
}
