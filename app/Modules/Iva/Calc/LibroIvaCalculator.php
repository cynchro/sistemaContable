<?php

namespace App\Modules\Iva\Calc;

use App\Support\Calc\Decimal;
use App\Support\Calc\Contracts\Calculator;

/**
 * Calculadora pura de los totales de un período (libro IVA), sobre el motor
 * {@see Decimal}. Sin estado ni acceso a datos: recibe las sumas por signo que
 * arma el repositorio y compone los totales firmados y el saldo de IVA.
 *
 * Regla central del legacy (procedure ACTUALIZA_TOTALES_PERIODO): cada importe se
 * pondera por el signo de su tipo de comprobante (las notas de crédito restan):
 *   total = Σ (importe · signo)
 *
 * Saldo de IVA = IVA débito (ventas) − IVA crédito (compras):
 *   > 0 ⇒ a pagar · < 0 ⇒ a favor.
 */
final class LibroIvaCalculator implements Calculator
{
    /**
     * @param  list<array{signo: mixed, importe: mixed}> $ventasTotal
     * @param  list<array{signo: mixed, importe: mixed}> $ventasIva
     * @param  list<array{signo: mixed, importe: mixed}> $comprasTotal
     * @param  list<array{signo: mixed, importe: mixed}> $comprasIva
     * @return array{
     *   total_ventas: string, total_compras: string,
     *   iva_ventas: string, iva_compras: string, saldo_iva: string
     * }
     */
    public function totalesPeriodo(
        array $ventasTotal,
        array $ventasIva,
        array $comprasTotal,
        array $comprasIva,
    ): array {
        $ivaVentas  = $this->sumarConSigno($ventasIva);
        $ivaCompras = $this->sumarConSigno($comprasIva);

        return [
            'total_ventas'  => $this->sumarConSigno($ventasTotal)->value(2),
            'total_compras' => $this->sumarConSigno($comprasTotal)->value(2),
            'iva_ventas'    => $ivaVentas->value(2),
            'iva_compras'   => $ivaCompras->value(2),
            'saldo_iva'     => $ivaVentas->sub($ivaCompras)->value(2),
        ];
    }

    /**
     * Σ (importe · signo) sobre filas ya agrupadas por signo.
     *
     * @param list<array{signo: mixed, importe: mixed}> $rows
     */
    private function sumarConSigno(array $rows): Decimal
    {
        $total = Decimal::zero();

        foreach ($rows as $row) {
            $importe = Decimal::of($row['importe'] ?? 0);
            $signo   = Decimal::of($row['signo'] ?? 1);
            $total   = $total->add($importe->mul($signo));
        }

        return $total;
    }
}
