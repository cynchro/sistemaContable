<?php

namespace App\Modules\Iva\Afip\Wsfe;

/**
 * Datos ya resueltos (catálogos + numeración) necesarios para armar el FeCAEReq de
 * una venta: punto de venta, número asignado, CbteTipo y DocTipo AFIP, condición del
 * receptor (codigo legacy, lo mapea el resolver) y moneda.
 */
final class FacturaContexto
{
    public function __construct(
        public readonly int $ptoVta,
        public readonly int $numero,
        public readonly int $cbteTipo,
        public readonly int $docTipo,
        public readonly string $condicionCodigo,
        public readonly string $monId = 'PES',
        public readonly float $monCotiz = 1.0,
    ) {
    }
}
