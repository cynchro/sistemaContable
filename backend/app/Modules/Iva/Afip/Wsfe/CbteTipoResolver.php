<?php

namespace App\Modules\Iva\Afip\Wsfe;

use RuntimeException;

/**
 * Resuelve el código de comprobante de AFIP (CbteTipo) a partir del tipo legacy
 * (codigo de `tipos_comprobante`) + la letra (A/B/C/E/M/T). El CbteTipo NO coincide
 * con el cod_citi del catálogo, por eso se resuelve acá con la tabla oficial de AFIP.
 * Lo usan tanto la factura electrónica (WSFEv1) como el Libro IVA Digital. Combinación
 * desconocida → lanza (no se inventa un código).
 */
final class CbteTipoResolver
{
    /**
     * Tipos cuyo código AFIP depende de la letra del comprobante.
     *
     * @var array<string, array<string, int>> [tipoLegacy][letra] => CbteTipo AFIP
     */
    private const TABLA = [
        // Factura / ND / NC / Recibo
        'FA' => ['A' => 1,   'B' => 6,   'C' => 11,  'E' => 19, 'M' => 51],
        'ND' => ['A' => 2,   'B' => 7,   'C' => 12,  'E' => 20, 'M' => 52],
        'NC' => ['A' => 3,   'B' => 8,   'C' => 13,  'E' => 21, 'M' => 53, 'T' => 197],
        'RF' => ['A' => 4,   'B' => 9,   'C' => 15,  'M' => 54],
        'RE' => ['A' => 4,   'B' => 9,   'C' => 15,  'M' => 54],
        // Tique Factura (controlador fiscal)
        'TF' => ['A' => 81,  'B' => 82,  'C' => 83,  'M' => 118],
        // Factura de Crédito Electrónica MiPyME (y sus ND/NC)
        'FE' => ['A' => 201, 'B' => 206, 'C' => 211],
        'DE' => ['A' => 202, 'B' => 207, 'C' => 212],
        'CE' => ['A' => 203, 'B' => 208, 'C' => 213],
    ];

    /**
     * Tipos con código AFIP fijo: el propio tipo legacy ya identifica la clase/letra
     * (p. ej. "Nota de Crédito Tique A"), así que no depende de la letra del comprobante.
     *
     * @var array<string, int> [tipoLegacy] => CbteTipo AFIP
     */
    private const TABLA_FIJA = [
        'FT' => 195, // Factura T
        'LA' => 17,  // Liquidación de Servicios Públicos A
        'LB' => 18,  // Liquidación de Servicios Públicos B
        'CT' => 109, // Tique C
        'CZ' => 110, // Nota de Crédito Tique
        'CA' => 112, // Nota de Crédito Tique A
        'CB' => 113, // Nota de Crédito Tique B
        'CN' => 114, // Nota de Crédito Tique C
    ];

    public static function resolve(string $tipoLegacy, string $letra): int
    {
        $tipo  = strtoupper(trim($tipoLegacy));
        $letra = strtoupper(trim($letra));

        if (isset(self::TABLA_FIJA[$tipo])) {
            return self::TABLA_FIJA[$tipo];
        }

        if (!isset(self::TABLA[$tipo][$letra])) {
            throw new RuntimeException(
                "No hay CbteTipo de AFIP para tipo '{$tipo}' letra '{$letra}'."
            );
        }

        return self::TABLA[$tipo][$letra];
    }
}
