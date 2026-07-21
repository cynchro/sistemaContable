<?php

namespace App\Modules\Iva\Afip\Wsfe;

use RuntimeException;

/**
 * Mapea una alícuota de IVA (porcentaje) al Id de alícuota de WSFEv1
 * (FEParamGetTiposIva). Alícuota no contemplada → lanza.
 */
final class AlicuotaIvaResolver
{
    /** @var array<int|string, int> alícuota normalizada => Id AFIP (PHP castea claves numéricas a int) */
    private const TABLA = [
        '0'     => 3,
        '10.5'  => 4,
        '21'    => 5,
        '27'    => 6,
        '5'     => 8,
        '2.5'   => 9,
    ];

    public static function id(int|float|string $alicuota): int
    {
        // Normaliza "21.000" / 21.0 / "21" → "21"; "10.500" → "10.5".
        $key = rtrim(rtrim(number_format((float) $alicuota, 3, '.', ''), '0'), '.');

        if (!isset(self::TABLA[$key])) {
            throw new RuntimeException("No hay Id de alícuota de IVA AFIP para '{$alicuota}'.");
        }

        return self::TABLA[$key];
    }

    /**
     * Inverso de `id()`: Id de alícuota AFIP → porcentaje. Usado al leer respuestas de
     * AFIP (p. ej. FECompConsultar) que traen el Id en vez del porcentaje. Id desconocido
     * → null (no se inventa un porcentaje).
     */
    public static function porcentaje(int $id): ?float
    {
        static $inversa = null;
        $inversa ??= array_flip(self::TABLA);

        return isset($inversa[$id]) ? (float) $inversa[$id] : null;
    }
}
