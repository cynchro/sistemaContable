<?php

namespace App\Modules\Iva\Calc;

use App\Support\Calc\Decimal;
use App\Support\Calc\Contracts\Calculator;

/**
 * Calculadora pura de una percepción de un comprobante (venta o compra), sobre el
 * motor {@see Decimal}. Sin estado ni acceso a datos.
 *
 * Decisión de dominio (respuestas.md A1/A2, confirmada con factura real): las
 * percepciones SE DISCRIMINAN e INTEGRAN el total del comprobante. La BASE de
 * cálculo depende del tipo de percepción y se parametriza en `tipos_retencion.base_calculo`:
 *
 *   - 'neto_gravado'         → base = Σ neto gravado de las líneas
 *                              (IIBB Catamarca 2,5%; Tasa municipal Catamarca 0,6%).
 *   - 'neto_mas_imp_interno' → base = Σ neto gravado + impuestos internos
 *                              (IIBB con bienes que llevan impuesto interno, p. ej. bebidas).
 *   - 'iva_percepcion'       → Percepción IVA: 3% sobre el neto facturado al 21% +
 *                              1,5% sobre el neto facturado al 10,5% (RG general). El importe
 *                              se calcula por tramos de alícuota, no como base × alícuota única.
 *
 * En todos los casos el importe informado explícitamente (override) tiene prioridad,
 * y una `base` explícita pisa la base de la estrategia. importe = round(base × %, 2).
 */
final class PercepcionCalculator implements Calculator
{
    /** Tasas de la Percepción IVA por alícuota de IVA de la línea (RG general). */
    private const PERCEPCION_IVA_TASAS = [
        '21'   => '3',
        '10.5' => '1.5',
    ];

    /**
     * Resuelve la base y el importe de una percepción contra las líneas del comprobante.
     *
     * @param  array{imp_interno?: mixed} $cabecera
     * @param  list<array{neto_gravado?: mixed, iva_alicuota?: mixed}> $discriminaciones
     * @param  array{base_calculo?: string, alicuota?: mixed, base?: mixed, importe?: mixed} $percepcion
     * @return array{base: string, alicuota: string|null, importe: string}
     */
    public function calcular(array $cabecera, array $discriminaciones, array $percepcion): array
    {
        $estrategia   = (string) ($percepcion['base_calculo'] ?? 'neto_gravado');
        $alicuota     = $this->aDecimalOrNull($percepcion['alicuota'] ?? null);
        $baseOverride = $this->aDecimalOrNull($percepcion['base'] ?? null);
        $importeOver  = $this->aDecimalOrNull($percepcion['importe'] ?? null);

        if ($estrategia === 'iva_percepcion') {
            $base    = $baseOverride ?? $this->sumarNeto($discriminaciones);
            $importe = $importeOver ?? $this->importePercepcionIva($discriminaciones);
        } else {
            $base    = $baseOverride ?? $this->baseDeEstrategia($estrategia, $cabecera, $discriminaciones);
            $importe = $importeOver ?? $base->percentage($alicuota ?? Decimal::zero());
        }

        return [
            'base'     => $base->value(2),
            'alicuota' => $alicuota?->value(3),
            'importe'  => $importe->round(2)->value(2),
        ];
    }

    /**
     * Importe de la Percepción IVA por tramos: Σ (neto de la línea × tasa según su alícuota).
     *
     * @param list<array{neto_gravado?: mixed, iva_alicuota?: mixed}> $discriminaciones
     */
    private function importePercepcionIva(array $discriminaciones): Decimal
    {
        $total = Decimal::zero();
        foreach ($discriminaciones as $linea) {
            $neto = Decimal::of($linea['neto_gravado'] ?? 0)->round(2);
            $tasa = $this->tasaPercepcionIva(Decimal::of($linea['iva_alicuota'] ?? 0));
            $total = $total->add($neto->percentage($tasa));
        }

        return $total;
    }

    /** Tasa de Percepción IVA para una alícuota de IVA dada (0 si no corresponde). */
    private function tasaPercepcionIva(Decimal $ivaAlicuota): Decimal
    {
        foreach (self::PERCEPCION_IVA_TASAS as $alic => $tasa) {
            if (Decimal::of((string) $alic)->equals($ivaAlicuota)) {
                return Decimal::of($tasa);
            }
        }

        return Decimal::zero();
    }

    /**
     * @param array{imp_interno?: mixed} $cabecera
     * @param list<array{neto_gravado?: mixed, iva_alicuota?: mixed}> $discriminaciones
     */
    private function baseDeEstrategia(string $estrategia, array $cabecera, array $discriminaciones): Decimal
    {
        $base = $this->sumarNeto($discriminaciones);

        if ($estrategia === 'neto_mas_imp_interno') {
            $base = $base->add(Decimal::of($cabecera['imp_interno'] ?? 0)->round(2));
        }

        return $base;
    }

    /** @param list<array{neto_gravado?: mixed}> $discriminaciones */
    private function sumarNeto(array $discriminaciones): Decimal
    {
        $total = Decimal::zero();
        foreach ($discriminaciones as $linea) {
            $total = $total->add(Decimal::of($linea['neto_gravado'] ?? 0)->round(2));
        }

        return $total;
    }

    private function aDecimalOrNull(mixed $value): ?Decimal
    {
        if (is_int($value) || is_float($value) || (is_string($value) && is_numeric($value))) {
            return Decimal::of((string) $value);
        }

        return null;
    }
}
