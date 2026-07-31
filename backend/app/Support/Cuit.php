<?php

namespace App\Support;

/**
 * Normalización y validación de CUIT (clave del Padrón Único de Sujetos IVA).
 * Pura, sin dependencias — el algoritmo de dígito verificador es el estándar de AFIP.
 */
final class Cuit
{
    private const MULTIPLICADORES = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];

    /** Deja solo los 11 dígitos (sin guiones ni espacios). */
    public static function normalizar(string $cuit): string
    {
        return preg_replace('/\D/', '', $cuit) ?? '';
    }

    /** 11 dígitos + dígito verificador válido según el algoritmo de AFIP. */
    public static function esValido(string $cuit): bool
    {
        $digitos = self::normalizar($cuit);

        if (strlen($digitos) !== 11) {
            return false;
        }

        $suma = 0;
        foreach (self::MULTIPLICADORES as $i => $mult) {
            $suma += ((int) $digitos[$i]) * $mult;
        }

        $verificador = 11 - ($suma % 11);
        if ($verificador === 11) {
            $verificador = 0;
        }
        if ($verificador === 10) {
            return false;
        }

        return $verificador === (int) $digitos[10];
    }
}
