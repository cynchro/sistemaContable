<?php

namespace App\Modules\Iva\Afip\Wsfe;

use RuntimeException;

/**
 * Mapea la condición frente al IVA del receptor (codigo de `condiciones_iva`) al
 * CondicionIVAReceptorId de WSFEv1 (RG 5616, obligatorio). Solo se mapean las
 * condiciones con equivalencia oficial inequívoca; el resto lanza (no se inventa).
 */
final class CondicionReceptorResolver
{
    /** @var array<string, int> codigo condicion (legacy) => CondicionIVAReceptorId AFIP */
    private const TABLA = [
        'RI' => 1,   // IVA Responsable Inscripto
        'EX' => 4,   // IVA Sujeto Exento
        'CF' => 5,   // Consumidor Final
        'MO' => 6,   // Responsable Monotributo
        'CE' => 9,   // Cliente del Exterior
    ];

    public static function id(string $condicionCodigo): int
    {
        $codigo = strtoupper(trim($condicionCodigo));

        if (!isset(self::TABLA[$codigo])) {
            throw new RuntimeException(
                "No hay CondicionIVAReceptorId AFIP para la condición '{$codigo}'."
            );
        }

        return self::TABLA[$codigo];
    }
}
