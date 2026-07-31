<?php

namespace App\Modules\Iva\Calc;

use App\Support\Calc\Decimal;
use App\Support\Calc\Contracts\Calculator;

/**
 * Motor de alertas estadísticas v1 (documento "Satélite Visual IVA" §7): compara el total del
 * último período de un contribuyente contra el promedio de sus períodos anteriores, tanto para
 * compras como para ventas. El documento deja la mecánica sin cerrar ("falta definir el umbral
 * de desvío... se recomienda resolverlo antes de programar"); esta v1 usa un umbral por defecto
 * documentado como SUPUESTO — ver preguntas.md, a confirmar con el contador (mismo patrón que
 * el resto de las decisiones de dominio de este proyecto).
 *
 * Sin estado ni acceso a datos: recibe el total actual + los totales históricos ya resueltos
 * por el Service.
 */
final class AlertaEstadisticaCalculator implements Calculator
{
    /** Supuesto v1 (30% de desvío) — pendiente de confirmar con el contador, ver preguntas.md. */
    public const UMBRAL_DESVIO = '0.30';

    /** Mínimo de períodos históricos para evaluar (evita falsos positivos con poco historial). */
    public const MIN_PERIODOS_HISTORIAL = 3;

    /**
     * @param  list<string> $historicos totales de los períodos anteriores al actual
     * @return array{promedio: string, desvio_pct: string, alerta: bool}|null null si no hay
     *         historial suficiente o el promedio es cero (sin base de comparación válida).
     */
    public function evaluar(string $actual, array $historicos): ?array
    {
        if (count($historicos) < self::MIN_PERIODOS_HISTORIAL) {
            return null;
        }

        $suma = Decimal::zero();
        foreach ($historicos as $h) {
            $suma = $suma->add(Decimal::of($h));
        }
        $promedio = $suma->div(Decimal::of((string) count($historicos)));

        if ($promedio->isZero()) {
            return null;
        }

        $desvio = Decimal::of($actual)->sub($promedio)->div($promedio)->abs();

        return [
            'promedio'   => $promedio->value(2),
            'desvio_pct' => $desvio->mul(Decimal::of('100'))->value(2),
            'alerta'     => $desvio->compareTo(Decimal::of(self::UMBRAL_DESVIO)) > 0,
        ];
    }
}
