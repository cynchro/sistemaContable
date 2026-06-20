<?php

namespace App\Modules\Iva\Calc;

use App\Support\Calc\Decimal;
use App\Support\Calc\Contracts\Calculator;

/**
 * DDJJ de IVA "IVA Simple" (formulario F.2051 del Portal IVA de ARCA), que reemplaza
 * al F.2002. Toma exactamente los mismos datos del período (débito y crédito fiscal
 * computable) y los encadena en la ecuación del formulario, en dos bloques:
 *
 *  Determinación del impuesto:
 *    resultado = débito fiscal − crédito fiscal computable − saldo técnico a favor anterior
 *      resultado > 0  → saldo técnico a favor de ARCA
 *      resultado < 0  → saldo técnico a favor del contribuyente (arrastra al próximo período)
 *
 *  Determinación de la posición mensual:
 *    posición = saldo técnico a favor de ARCA
 *               − saldo de libre disponibilidad del período anterior (neto de usos)
 *               − retenciones, percepciones y pagos a cuenta (neto de restituciones)
 *      posición > 0  → saldo a pagar a ARCA
 *      posición < 0  → saldo de libre disponibilidad a favor del contribuyente del período
 *
 * El débito y el crédito computable salen del período (los calcula el F2002 actual,
 * {@see DeclaracionIvaCalculator}). Los tres arrastres (saldo técnico anterior, saldo de
 * libre disponibilidad anterior y retenciones/percepciones/pagos a cuenta) son insumos:
 * el estudio los traslada de la DDJJ anterior / de las constancias, hasta que se persista
 * el historial de DDJJ (ver app/Modules/Iva/pendientes.md §F).
 *
 * Pura: sólo aritmética sobre Decimal.
 */
final class IvaSimpleCalculator implements Calculator
{
    /**
     * @return array{
     *   determinacion_impuesto: array{
     *     debito_fiscal: string, credito_fiscal: string, saldo_tecnico_anterior: string,
     *     saldo_tecnico_a_favor_arca: string, saldo_tecnico_a_favor_contribuyente: string
     *   },
     *   posicion_mensual: array{
     *     saldo_tecnico_a_favor_arca: string,
     *     saldo_libre_disponibilidad_anterior: string,
     *     retenciones_percepciones_pagos: string,
     *     saldo_a_pagar: string,
     *     saldo_libre_disponibilidad_periodo: string
     *   }
     * }
     */
    public function calcular(
        string $debitoFiscal,
        string $creditoFiscalComputable,
        string $saldoTecnicoAnterior = '0',
        string $saldoLibreDisponibilidadAnterior = '0',
        string $retencionesPercepcionesPagos = '0',
    ): array {
        $debito          = Decimal::of($debitoFiscal);
        $credito         = Decimal::of($creditoFiscalComputable);
        $saldoTecnicoAnt = Decimal::of($saldoTecnicoAnterior);
        $libreDispAnt    = Decimal::of($saldoLibreDisponibilidadAnterior);
        $retYPerc        = Decimal::of($retencionesPercepcionesPagos);

        // Determinación del impuesto.
        $resultado = $debito->sub($credito)->sub($saldoTecnicoAnt);
        $saldoTecnicoArca         = $resultado->isPositive() ? $resultado : Decimal::zero();
        $saldoTecnicoContribuyente = $resultado->isNegative() ? $resultado->abs() : Decimal::zero();

        // Determinación de la posición mensual.
        $posicion = $saldoTecnicoArca->sub($libreDispAnt)->sub($retYPerc);
        $saldoAPagar          = $posicion->isPositive() ? $posicion : Decimal::zero();
        $saldoLibreDispPeriodo = $posicion->isNegative() ? $posicion->abs() : Decimal::zero();

        return [
            'determinacion_impuesto' => [
                'debito_fiscal'                       => $debito->value(2),
                'credito_fiscal'                      => $credito->value(2),
                'saldo_tecnico_anterior'              => $saldoTecnicoAnt->value(2),
                'saldo_tecnico_a_favor_arca'          => $saldoTecnicoArca->value(2),
                'saldo_tecnico_a_favor_contribuyente' => $saldoTecnicoContribuyente->value(2),
            ],
            'posicion_mensual' => [
                'saldo_tecnico_a_favor_arca'          => $saldoTecnicoArca->value(2),
                'saldo_libre_disponibilidad_anterior' => $libreDispAnt->value(2),
                'retenciones_percepciones_pagos'      => $retYPerc->value(2),
                'saldo_a_pagar'                       => $saldoAPagar->value(2),
                'saldo_libre_disponibilidad_periodo'  => $saldoLibreDispPeriodo->value(2),
            ],
        ];
    }
}
