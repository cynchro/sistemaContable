<?php

namespace App\Modules\Sueldos\Calc;

use App\Support\Calc\Decimal;
use App\Support\Calc\Contracts\Calculator;

/**
 * Calcula las contribuciones patronales de un empleado sobre la base imponible del
 * recibo, según CONTRIBUCIONES_RECIBO_DETALLE del legacy:
 *   importe_total = base_imponible · porcentaje / 100 + importe_fijo
 * donde base = total remunerativo (+ no remunerativo si la contribución lo incluye).
 *
 * Pura. Detracción y topes del legacy se difieren (ver doc).
 */
final class ContribucionCalculator implements Calculator
{
    /**
     * @param  array<string, mixed>       $base        ['remunerativo' => , 'no_remunerativo' => ]
     * @param  list<array<string, mixed>> $contribuciones definiciones (porcentaje, importe_fijo, incluye_norem)
     * @return array{lineas: list<array<string, mixed>>, total: string}
     */
    public function calcular(array $base, array $contribuciones): array
    {
        $rem   = Decimal::of($base['remunerativo'] ?? 0);
        $noRem = Decimal::of($base['no_remunerativo'] ?? 0);

        $total  = Decimal::zero();
        $lineas = [];

        foreach ($contribuciones as $contrib) {
            $baseImp = ($contrib['incluye_norem'] ?? 'N') === 'S' ? $rem->add($noRem) : $rem;
            $porc    = Decimal::of($contrib['porcentaje'] ?? 0);
            $fijo    = Decimal::of($contrib['importe_fijo'] ?? 0);

            $importe = $baseImp->percentage($porc)->add($fijo)->round(2);
            $total   = $total->add($importe);

            $lineas[] = [
                'contribucion_id' => $contrib['id'] ?? null,
                'descripcion'     => $contrib['descripcion'] ?? null,
                'base_imponible'  => $baseImp->value(2),
                'porcentaje'      => $porc->value(3),
                'importe_fijo'    => $fijo->value(2),
                'importe_total'   => $importe->value(2),
            ];
        }

        return ['lineas' => $lineas, 'total' => $total->value(2)];
    }
}
