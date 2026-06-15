<?php

namespace App\Modules\Iva\Calc;

use App\Support\Calc\Decimal;
use App\Support\Calc\Contracts\Calculator;

/**
 * Consolida el detalle por alícuota (ya firmado) en los totales de la DDJJ de IVA,
 * según el F2002 del legacy (reportes IVAVentasDF_F2002 / IVAComprasCF_F2002):
 *  - Débito fiscal  = IVA de ventas.
 *  - Crédito fiscal = crédito computable (CFC = cf_computable) de compras —
 *    NO el IVA total de compras—.
 *  - Saldo técnico  = débito fiscal − crédito fiscal computable
 *    (> 0 a pagar · < 0 a favor).
 *
 * Pura: recibe el detalle ya consolidado por signo y suma los renglones.
 */
final class DeclaracionIvaCalculator implements Calculator
{
    /**
     * @param  list<array<string, mixed>> $ventasDetalle  renglones con neto_gravado, iva
     * @param  list<array<string, mixed>> $comprasDetalle renglones con neto_gravado, iva, cf_computable
     * @return array{
     *   debito_neto: string, debito_iva: string,
     *   credito_neto: string, credito_iva: string, credito_computable: string,
     *   saldo_tecnico: string
     * }
     */
    public function consolidar(array $ventasDetalle, array $comprasDetalle): array
    {
        $debitoNeto        = $this->sumar($ventasDetalle, 'neto_gravado');
        $debitoIva         = $this->sumar($ventasDetalle, 'iva');
        $creditoNeto       = $this->sumar($comprasDetalle, 'neto_gravado');
        $creditoIva        = $this->sumar($comprasDetalle, 'iva');
        $creditoComputable = $this->sumar($comprasDetalle, 'cf_computable');

        return [
            'debito_neto'        => $debitoNeto->value(2),
            'debito_iva'         => $debitoIva->value(2),
            'credito_neto'       => $creditoNeto->value(2),
            'credito_iva'        => $creditoIva->value(2),
            'credito_computable' => $creditoComputable->value(2),
            'saldo_tecnico'      => $debitoIva->sub($creditoComputable)->value(2),
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function sumar(array $rows, string $col): Decimal
    {
        $total = Decimal::zero();
        foreach ($rows as $row) {
            $total = $total->add(Decimal::of($row[$col] ?? 0));
        }

        return $total;
    }
}
