<?php

namespace App\Modules\Honorarios\Calc;

use App\Support\Calc\Decimal;
use App\Support\Calc\Contracts\Calculator;

/**
 * Calcula los honorarios profesionales sobre el motor {@see Decimal}.
 *   importe_línea = uc · valor_uc · factor · cantidad
 *   total = Σ importes
 * (ingeniería inversa de servicios.uc × factor de complejidad del legacy).
 *
 * Pura: recibe las líneas ya resueltas (uc del servicio, factor de la complejidad).
 */
final class HonorarioCalculator implements Calculator
{
    /**
     * @param  int|float|string           $valorUc valor de la UC (unidad de cuenta)
     * @param  list<array<string, mixed>> $lineas  cada una con uc, factor, cantidad (+ passthrough)
     * @return array{lineas: list<array<string, mixed>>, total: string}
     */
    public function calcular(int|float|string $valorUc, array $lineas): array
    {
        $uc    = Decimal::of($valorUc);
        $total = Decimal::zero();
        $out   = [];

        foreach ($lineas as $linea) {
            $importe = Decimal::of($linea['uc'] ?? 0)
                ->mul($uc)
                ->mul(Decimal::of($linea['factor'] ?? 1))
                ->mul(Decimal::of($linea['cantidad'] ?? 1))
                ->round(2);

            $total = $total->add($importe);

            $linea['importe'] = $importe->value(2);
            $out[] = $linea;
        }

        return ['lineas' => $out, 'total' => $total->value(2)];
    }
}
