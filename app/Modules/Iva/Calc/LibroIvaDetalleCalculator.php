<?php

namespace App\Modules\Iva\Calc;

use App\Support\Calc\Decimal;
use App\Support\Calc\Contracts\Calculator;

/**
 * Agrupa los renglones de discriminación por (condición IVA, alícuota) sumando los
 * importes ponderados por el signo del comprobante. Es la base del libro IVA
 * detallado y de las DDJJ (replica las vistas VIEW_VENTAS y VIEW_COMPRAS_OPERACION
 * del legacy).
 *
 * Pura: recibe filas ya traídas y agregadas por signo desde el repositorio, y
 * devuelve los subtotales firmados por alícuota.
 */
final class LibroIvaDetalleCalculator implements Calculator
{
    /**
     * @param  list<array<string, mixed>> $rows     filas con condicion_iva_id, alicuota, signo y los $sumCols
     * @param  list<string>               $sumCols  columnas de importe a sumar (p. ej. neto_gravado, iva)
     * @return list<array<string, mixed>> un item por (condicion_iva_id, alicuota) con los $sumCols a 2 decimales
     */
    public function agruparPorAlicuota(array $rows, array $sumCols): array
    {
        /** @var array<string, array<string, mixed>> $grupos */
        $grupos = [];

        foreach ($rows as $row) {
            $cond = $row['condicion_iva_id'] ?? null;
            $alic = $row['alicuota'] ?? null;
            $key  = ($cond ?? 'x') . '|' . ($alic ?? 'x');

            if (!isset($grupos[$key])) {
                $grupos[$key] = ['condicion_iva_id' => $cond, 'alicuota' => $alic];
                foreach ($sumCols as $col) {
                    $grupos[$key][$col] = Decimal::zero();
                }
            }

            $signo = Decimal::of($row['signo'] ?? 1);
            foreach ($sumCols as $col) {
                $grupos[$key][$col] = $grupos[$key][$col]->add(Decimal::of($row[$col] ?? 0)->mul($signo));
            }
        }

        $out = [];
        foreach ($grupos as $grupo) {
            $item = ['condicion_iva_id' => $grupo['condicion_iva_id'], 'alicuota' => $grupo['alicuota']];
            foreach ($sumCols as $col) {
                /** @var Decimal $acumulado */
                $acumulado = $grupo[$col];
                $item[$col] = $acumulado->value(2);
            }
            $out[] = $item;
        }

        return $out;
    }
}
