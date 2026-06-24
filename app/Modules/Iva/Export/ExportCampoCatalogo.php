<?php

namespace App\Modules\Iva\Export;

/**
 * Lista blanca de campos exportables del subdiario (ventas/compras enriquecidos por
 * ReporteIvaRepository). El exportador configurable sólo opera sobre estos campos:
 * a diferencia del legacy (EXPOTXT.EXP_TABLA/EXP_CAMPO con tabla/columna libres), acá
 * no hay SQL dinámico ni acceso a columnas arbitrarias.
 *
 * `contraparte_nombre` se normaliza en el Service a cliente_nombre (ventas) o
 * proveedor_nombre (compras).
 */
final class ExportCampoCatalogo
{
    public const TEXTO  = 'texto';
    public const NUMERO = 'numero';
    public const FECHA  = 'fecha';

    /** @var array<string, string> campo => tipo */
    private const CAMPOS = [
        'fecha'                   => self::FECHA,
        'tipo_comprobante_codigo' => self::TEXTO,
        'cod_citi'                => self::TEXTO,
        'letra'                   => self::TEXTO,
        'punto_venta'             => self::NUMERO,
        'numero'                  => self::NUMERO,
        'cuit'                    => self::TEXTO,
        'contraparte_nombre'      => self::TEXTO,
        'condicion_codigo'        => self::TEXTO,
        'provincia_nombre'        => self::TEXTO,
        'neto_gravado'            => self::NUMERO,
        'neto_no_grav'            => self::NUMERO,
        'exento'                  => self::NUMERO,
        'iva'                     => self::NUMERO,
        'percepcion'              => self::NUMERO,
        'imp_interno'             => self::NUMERO,
        'total'                   => self::NUMERO,
    ];

    public static function existe(string $campo): bool
    {
        return isset(self::CAMPOS[$campo]);
    }

    public static function tipo(string $campo): string
    {
        return self::CAMPOS[$campo] ?? self::TEXTO;
    }

    /** @return list<string> */
    public static function disponibles(): array
    {
        return array_keys(self::CAMPOS);
    }
}
