<?php

namespace App\Modules\Iva\Export;

/**
 * Código de jurisdicción del Convenio Multilateral (COMARB / SIFERE) por provincia.
 *
 * Los códigos 901-924 son una lista regulatoria estable (Comisión Arbitral). Se resuelve
 * por nombre de provincia (nuestro catálogo `provincias` no guarda el código). Confirmado
 * contra el ejemplo real del contador: Salta = 917; y su indicación: Catamarca = 903.
 */
final class JurisdiccionSifere
{
    /** @var array<string, string> nombre normalizado => código COMARB */
    private const CODIGOS = [
        'CAPITAL FEDERAL'          => '901',
        'CIUDAD DE BUENOS AIRES'   => '901',
        'BUENOS AIRES'             => '902',
        'CATAMARCA'                => '903',
        'CORDOBA'                  => '904',
        'CORRIENTES'               => '905',
        'CHACO'                    => '906',
        'CHUBUT'                   => '907',
        'ENTRE RIOS'               => '908',
        'FORMOSA'                  => '909',
        'JUJUY'                    => '910',
        'LA PAMPA'                 => '911',
        'LA RIOJA'                 => '912',
        'MENDOZA'                  => '913',
        'MISIONES'                 => '914',
        'NEUQUEN'                  => '915',
        'RIO NEGRO'                => '916',
        'SALTA'                    => '917',
        'SAN JUAN'                 => '918',
        'SAN LUIS'                 => '919',
        'SANTA CRUZ'               => '920',
        'SANTA FE'                 => '921',
        'SANTIAGO DEL ESTERO'      => '922',
        'TUCUMAN'                  => '923',
        'TIERRA DEL FUEGO'         => '924',
    ];

    /** Devuelve el código COMARB de la provincia, o null si no la conoce. */
    public static function codigo(string $provincia): ?string
    {
        return self::CODIGOS[self::normalizar($provincia)] ?? null;
    }

    /** Mayúsculas sin tildes ni espacios extra, para comparar por nombre. */
    private static function normalizar(string $s): string
    {
        $s = strtr($s, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
                        'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'ñ' => 'n', 'Ñ' => 'N']);

        return trim(preg_replace('/\s+/', ' ', mb_strtoupper($s)) ?? '');
    }
}
