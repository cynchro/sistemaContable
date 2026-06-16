<?php

namespace App\Modules\Iva\Afip\Wsfe;

use RuntimeException;

/**
 * Resuelve el código de comprobante de WSFEv1 (CbteTipo) a partir del tipo legacy
 * (codigo de `tipos_comprobante`) + la letra (A/B/C/M). El CbteTipo de factura
 * electrónica NO coincide con el cod_citi del catálogo, por eso se resuelve acá con
 * la tabla oficial de AFIP. Combinación desconocida → lanza (no se inventa un código).
 */
final class CbteTipoResolver
{
    /** @var array<string, array<string, int>> [tipoLegacy][letra] => CbteTipo AFIP */
    private const TABLA = [
        // Factura
        'FA' => ['A' => 1, 'B' => 6, 'C' => 11, 'M' => 51],
        // Nota de débito
        'ND' => ['A' => 2, 'B' => 7, 'C' => 12, 'M' => 52],
        // Nota de crédito
        'NC' => ['A' => 3, 'B' => 8, 'C' => 13, 'M' => 53],
        // Recibo
        'RF' => ['A' => 4, 'B' => 9, 'C' => 15, 'M' => 54],
        'RE' => ['A' => 4, 'B' => 9, 'C' => 15, 'M' => 54],
    ];

    public static function resolve(string $tipoLegacy, string $letra): int
    {
        $tipo  = strtoupper(trim($tipoLegacy));
        $letra = strtoupper(trim($letra));

        if (!isset(self::TABLA[$tipo][$letra])) {
            throw new RuntimeException(
                "No hay CbteTipo de WSFEv1 para tipo '{$tipo}' letra '{$letra}'."
            );
        }

        return self::TABLA[$tipo][$letra];
    }
}
