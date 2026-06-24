<?php

namespace App\Modules\Iva\Afip\Wsfe;

/**
 * Mapea la condición frente al IVA del receptor (codigo de `condiciones_iva`) al
 * CondicionIVAReceptorId de WSFEv1 (RG 5616, obligatorio).
 *
 * Por indicación del contador (preguntas.md / respuestas.md A8): cuando no se tiene
 * información de la condición del receptor (código vacío, "No Disponible" o cualquier
 * código sin equivalencia) se emite por defecto como **Consumidor Final**. "Responsable
 * No Inscripto" fue eliminado por AFIP y ya no debería aparecer.
 */
final class CondicionReceptorResolver
{
    /** Consumidor Final: default cuando no se tiene información del receptor (A8). */
    public const CONSUMIDOR_FINAL = 5;

    /** @var array<string, int> codigo condicion (legacy) => CondicionIVAReceptorId AFIP */
    private const TABLA = [
        'RI' => 1,                       // IVA Responsable Inscripto
        'EX' => 4,                       // IVA Sujeto Exento
        'CF' => self::CONSUMIDOR_FINAL,  // Consumidor Final
        'MO' => 6,                       // Responsable Monotributo
        'CE' => 9,                       // Cliente del Exterior
        'ND' => self::CONSUMIDOR_FINAL,  // No Disponible (sin datos en AFIP) => Consumidor Final
    ];

    public static function id(string $condicionCodigo): int
    {
        $codigo = strtoupper(trim($condicionCodigo));

        return self::TABLA[$codigo] ?? self::CONSUMIDOR_FINAL;
    }
}
