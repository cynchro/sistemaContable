<?php

namespace App\Modules\Sueldos\Calc;

use App\Support\Calc\Decimal;
use App\Support\Calc\Contracts\Calculator;

/**
 * Calcula el recibo de un empleado en una liquidación: evalúa la fórmula de cada
 * concepto (en orden) con el FormulaEvaluator, encadenando las referencias entre
 * conceptos (`NNN#`) y el acumulador de no remunerativos (`NOREM`).
 *
 * Pura: recibe el contexto ya resuelto (no toca la base).
 *
 * Convenciones (ingeniería inversa; ver docs/ingenieria-inversa/sueldos.md):
 * - Se liquidan los conceptos que tienen NOVEDAD para el empleado; `CAN`/`IMP`
 *   salen de la novedad (cantidad/importe).
 * - Variables base: `BASICO` (del legajo/categoría), `ANTIG` (años de antigüedad),
 *   `NOREM` (acumulado de conceptos no remunerativos ya procesados).
 * - `tipo`: 3 = descuento; 1/2 = haber (remunerativo / no remunerativo).
 *   Neto = Σ haberes − Σ descuentos.
 */
final class LiquidacionCalculator implements Calculator
{
    public function __construct(private FormulaEvaluator $evaluator)
    {
    }

    /**
     * @param  array<string, mixed>       $empleado   con basico, antiguedad
     * @param  list<array<string, mixed>> $conceptos  ordenados (id, codigo, formula, tipo, ...)
     * @param  array<int, array<string, mixed>> $novedades  concepto_id => {cantidad, importe}
     * @return array{
     *   lineas: list<array<string, mixed>>,
     *   total_haberes: string, total_descuentos: string, neto: string
     * }
     */
    public function liquidar(array $empleado, array $conceptos, array $novedades): array
    {
        $basico     = (string) ($empleado['basico'] ?? 0);
        $antiguedad = (string) ($empleado['antiguedad'] ?? 0);

        /** @var array<string, string> $resultados código => importe (para refs NNN#) */
        $resultados = [];
        $noRem      = Decimal::zero();
        $haberes    = Decimal::zero();
        $descuentos = Decimal::zero();
        $lineas     = [];
        $item       = 0;

        foreach ($conceptos as $concepto) {
            $novedad = $novedades[$concepto['id']] ?? null;
            if ($novedad === null) {
                continue; // sólo se liquidan los conceptos con novedad cargada
            }

            $cantidad = (string) ($novedad['cantidad'] ?? 0);
            $variables = [
                'BASICO' => $basico,
                'ANTIG'  => $antiguedad,
                'CAN'    => $cantidad,
                'IMP'    => (string) ($novedad['importe'] ?? 0),
                'NOREM'  => $noRem->value(),
            ];

            $importe = $this->evaluator
                ->evaluar((string) ($concepto['formula'] ?? ''), $variables, $resultados)
                ->round(2);

            $codigo = (string) $concepto['codigo'];
            $tipo   = (int) ($concepto['tipo'] ?? 1);

            $resultados[$codigo] = $importe->value(2);

            if ($tipo === 2) {
                $noRem = $noRem->add($importe);
            }

            if ($tipo === 3) {
                $descuentos = $descuentos->add($importe);
            } else {
                $haberes = $haberes->add($importe);
            }

            $lineas[] = [
                'item'        => ++$item,
                'concepto_id' => $concepto['id'],
                'codigo'      => $codigo,
                'descripcion' => $concepto['descripcion'] ?? null,
                'formula'     => $concepto['formula'] ?? null,
                'cantidad'    => Decimal::of($cantidad)->value(2),
                'importe'     => $importe->value(2),
                'tipo'        => $tipo,
            ];
        }

        return [
            'lineas'           => $lineas,
            'total_haberes'    => $haberes->value(2),
            'total_descuentos' => $descuentos->value(2),
            'neto'             => $haberes->sub($descuentos)->value(2),
        ];
    }
}
