<?php

namespace App\Modules\Iva\Calc;

use App\Support\Calc\Decimal;

/**
 * Agrega los movimientos del Mayor en una cascada de grupos con subtotales (R2 del contador).
 * Puro: recibe las filas planas de {@see \App\Modules\Iva\Repositories\ReporteMayorRepository}
 * y una lista ordenada de dimensiones de agrupación, y devuelve el árbol con subtotales +
 * el total general. Sin estado ni acceso a datos.
 *
 * Dimensiones soportadas: 'cuenta' (por cuenta de mayor), 'proveedor' (sujeto+CUIT),
 * 'provincia'. Ejemplo: agrupar=['cuenta','proveedor'] → "Combustible → YPF → comprobantes".
 */
final class MayorReporteCalculator
{
    private const DIMENSIONES = ['cuenta', 'proveedor', 'provincia'];

    /**
     * @param  list<array<string, mixed>> $movimientos filas planas (neto/iva con signo)
     * @param  list<string>               $agrupar     dimensiones en orden (subconjunto de DIMENSIONES)
     * @return array{grupos: list<array<string, mixed>>, totales: array{neto: string, iva: string, cantidad: int}}
     */
    public function agrupar(array $movimientos, array $agrupar): array
    {
        $agrupar = array_values(array_filter($agrupar, static fn ($d) => in_array($d, self::DIMENSIONES, true)));

        return [
            'grupos'  => $this->construir($movimientos, $agrupar),
            'totales' => $this->totales($movimientos),
        ];
    }

    /**
     * @param  list<array<string, mixed>> $movimientos
     * @param  list<string>               $agrupar
     * @return list<array<string, mixed>>
     */
    private function construir(array $movimientos, array $agrupar): array
    {
        if ($agrupar === []) {
            // Hoja: los comprobantes tal cual (renglón por movimiento).
            return array_map(fn (array $m) => $this->hoja($m), $movimientos);
        }

        $dim  = $agrupar[0];
        $resto = array_slice($agrupar, 1);

        // Preserva el orden de aparición (los movimientos vienen ordenados por fecha).
        $buckets = [];
        $orden   = [];
        foreach ($movimientos as $m) {
            [$clave, $etiqueta] = $this->clave($dim, $m);
            if (!isset($buckets[$clave])) {
                $buckets[$clave] = ['etiqueta' => $etiqueta, 'items' => []];
                $orden[]         = $clave;
            }
            $buckets[$clave]['items'][] = $m;
        }

        $grupos = [];
        foreach ($orden as $clave) {
            $items    = $buckets[$clave]['items'];
            $subtotal = $this->totales($items);
            $grupos[] = [
                'dimension' => $dim,
                'clave'     => $clave,
                'etiqueta'  => $buckets[$clave]['etiqueta'],
                'subtotal'  => $subtotal,
                'hijos'     => $this->construir($items, $resto),
            ];
        }

        return $grupos;
    }

    /**
     * Clave y etiqueta de un movimiento para una dimensión.
     *
     * @param  array<string, mixed> $m
     * @return array{0: string, 1: string}
     */
    private function clave(string $dim, array $m): array
    {
        return match ($dim) {
            'cuenta' => [
                'cuenta:' . ($m['cuenta_id'] ?? '0'),
                $m['cuenta_id'] !== null
                    ? trim(($m['cuenta_codigo'] ? $m['cuenta_codigo'] . ' — ' : '') . (string) $m['cuenta_nombre'])
                    : '(sin cuenta imputada)',
            ],
            'proveedor' => [
                'suj:' . ($m['cuit'] ?: ($m['sujeto'] ?? '')),
                (string) ($m['sujeto'] ?? '') . ($m['cuit'] ? ' (' . $m['cuit'] . ')' : ''),
            ],
            'provincia' => [
                'prov:' . ($m['provincia_id'] ?? '0'),
                (string) ($m['provincia_nombre'] ?? '(sin provincia)'),
            ],
            default => ['x', ''],
        };
    }

    /**
     * @param  array<string, mixed> $m
     * @return array<string, mixed>
     */
    private function hoja(array $m): array
    {
        return [
            'fecha'       => $m['fecha'] ?? null,
            'origen'      => $m['origen'] ?? null,
            'comprobante' => trim(
                (string) ($m['cbte_codigo'] ?? '') . ' ' . (string) ($m['letra'] ?? '')
                . ' ' . (string) ($m['punto_venta'] ?? '') . '-' . (string) ($m['numero'] ?? '')
            ),
            'sujeto'    => $m['sujeto'] ?? null,
            'cuit'      => $m['cuit'] ?? null,
            'provincia' => $m['provincia_nombre'] ?? null,
            'cuenta'    => $m['cuenta_nombre'] ?? null,
            'neto'      => Decimal::of($m['neto'] ?? 0)->value(2),
            'iva'       => Decimal::of($m['iva'] ?? 0)->value(2),
        ];
    }

    /**
     * @param  list<array<string, mixed>> $movimientos
     * @return array{neto: string, iva: string, cantidad: int}
     */
    private function totales(array $movimientos): array
    {
        $neto = Decimal::zero();
        $iva  = Decimal::zero();
        foreach ($movimientos as $m) {
            $neto = $neto->add(Decimal::of($m['neto'] ?? 0));
            $iva  = $iva->add(Decimal::of($m['iva'] ?? 0));
        }

        return ['neto' => $neto->value(2), 'iva' => $iva->value(2), 'cantidad' => count($movimientos)];
    }
}
