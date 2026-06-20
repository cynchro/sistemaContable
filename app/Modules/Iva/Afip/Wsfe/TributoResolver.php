<?php

namespace App\Modules\Iva\Afip\Wsfe;

use RuntimeException;

/**
 * Mapea la clasificación interna de una percepción (`tipos_retencion.tipo_rg3685`)
 * al Id de tributo de WSFEv1 (FEParamGetTiposTributos), para emitirla como
 * `Tributos → Tributo[]` en el FeCAEReq (las percepciones integran el total —
 * respuestas.md A1).
 *
 * tipo_rg3685 (legacy):           Tributo AFIP:
 *   1 Percepción IVA          →   6 Percepción de IVA
 *   2 Percepciones Nacionales →   1 Impuestos nacionales
 *   3 Percepción IIBB         →   7 Percepciones de Ingresos Brutos
 *   4 Percepciones Municipales→   8 Percepciones Municipales
 *   5 No categorizados        →   9 Otras percepciones
 *
 * Clasificación desconocida → lanza (no inventamos un tributo).
 */
final class TributoResolver
{
    /** @var array<int, array{id: int, desc: string}> */
    private const TABLA = [
        1 => ['id' => 6, 'desc' => 'Percepción de IVA'],
        2 => ['id' => 1, 'desc' => 'Impuestos nacionales'],
        3 => ['id' => 7, 'desc' => 'Percepciones de Ingresos Brutos'],
        4 => ['id' => 8, 'desc' => 'Percepciones Municipales'],
        5 => ['id' => 9, 'desc' => 'Otras percepciones'],
    ];

    /** @return array{id: int, desc: string} */
    public static function fromRg3685(int $tipoRg3685): array
    {
        if (!isset(self::TABLA[$tipoRg3685])) {
            throw new RuntimeException(
                "No hay tributo AFIP para la clasificación de percepción (tipo_rg3685={$tipoRg3685})."
            );
        }

        return self::TABLA[$tipoRg3685];
    }
}
