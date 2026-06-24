<?php

namespace App\Modules\Iva\Calc;

use App\Support\Calc\Decimal;
use App\Support\Calc\Contracts\Calculator;

/**
 * Calculadora pura del IVA de un comprobante (venta o compra), sobre el motor
 * {@see Decimal}. Sin estado ni acceso a datos: recibe la cabecera y las líneas
 * de discriminación y devuelve los importes calculados.
 *
 * Reglas (ingeniería inversa de las vistas VIVENTAS/VICOMPRAS del legacy):
 *   iva_importe     = round(neto_gravado · iva_alicuota / 100, 2)
 *   iva_inc_importe = round(neto_gravado · iva_inc_alicuota / 100, 2)
 *   total = neto_no_grav + exento + imp_interno
 *         + Σ neto_gravado + Σ iva_importe + Σ iva_inc_importe + Σ percepciones
 *
 * El IVA del comprobante es la suma de los importes YA redondeados por línea
 * (igual que el legacy, que guarda y suma VD_IVA_IMPORTE), para reconciliar 1:1.
 *
 * Las PERCEPCIONES (ya resueltas por {@see PercepcionCalculator}) integran el total
 * del comprobante (respuestas.md A1, confirmado con factura real). Se reciben con su
 * importe y se suman al total; el cálculo de cada una vive en su propia calculadora.
 */
final class IvaComprobanteCalculator implements Calculator
{
    /**
     * @param  array{neto_no_grav?: mixed, exento?: mixed, imp_interno?: mixed} $cabecera
     * @param  list<array{neto_gravado?: mixed, iva_alicuota?: mixed, iva_inc_alicuota?: mixed}> $lineas
     * @param  list<array{importe?: mixed}> $percepciones percepciones ya resueltas (integran el total)
     * @return array{
     *   lineas: list<array{neto_gravado: string, iva_importe: string, iva_inc_importe: string}>,
     *   neto_gravado: string, iva: string, iva_inc: string, percepciones: string, total: string
     * }
     */
    public function calcular(array $cabecera, array $lineas, array $percepciones = []): array
    {
        $netoGravado = Decimal::zero();
        $ivaTotal    = Decimal::zero();
        $ivaIncTotal = Decimal::zero();
        $lineasOut   = [];

        foreach ($lineas as $linea) {
            $neto    = Decimal::of($linea['neto_gravado'] ?? 0)->round(2);
            $iva     = $neto->percentage(Decimal::of($linea['iva_alicuota'] ?? 0))->round(2);
            $ivaInc  = $neto->percentage(Decimal::of($linea['iva_inc_alicuota'] ?? 0))->round(2);

            $netoGravado = $netoGravado->add($neto);
            $ivaTotal    = $ivaTotal->add($iva);
            $ivaIncTotal = $ivaIncTotal->add($ivaInc);

            $lineasOut[] = [
                'neto_gravado'    => $neto->value(2),
                'iva_importe'     => $iva->value(2),
                'iva_inc_importe' => $ivaInc->value(2),
            ];
        }

        $percepcionesTotal = Decimal::zero();
        foreach ($percepciones as $perc) {
            $percepcionesTotal = $percepcionesTotal->add(Decimal::of($perc['importe'] ?? 0)->round(2));
        }

        $total = Decimal::of($cabecera['neto_no_grav'] ?? 0)->round(2)
            ->add(Decimal::of($cabecera['exento'] ?? 0)->round(2))
            ->add(Decimal::of($cabecera['imp_interno'] ?? 0)->round(2))
            ->add($netoGravado)
            ->add($ivaTotal)
            ->add($ivaIncTotal)
            ->add($percepcionesTotal);

        return [
            'lineas'       => $lineasOut,
            'neto_gravado' => $netoGravado->value(2),
            'iva'          => $ivaTotal->value(2),
            'iva_inc'      => $ivaIncTotal->value(2),
            'percepciones' => $percepcionesTotal->value(2),
            'total'        => $total->value(2),
        ];
    }
}
