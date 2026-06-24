<?php

namespace App\Support\Csv;

/**
 * Generador de CSV/TXT delimitado (RFC 4180), puro y reutilizable.
 *
 * El delimitador es configurable (',' CSV, ';' o tab para variantes). Los valores
 * se entrecomillan sólo si contienen el delimitador, comillas o saltos de línea, y
 * las comillas internas se duplican. Fin de línea CRLF (compatible con Excel).
 */
final class CsvWriter
{
    /**
     * @param  array<string, string>      $columnas  mapa clave => encabezado, en orden
     * @param  list<array<string, mixed>> $filas     cada fila se extrae por las claves de $columnas
     */
    public static function generar(
        array $columnas,
        array $filas,
        string $delimitador = ',',
        string $eol = "\r\n",
    ): string {
        $lineas = [self::linea(array_values($columnas), $delimitador)];

        foreach ($filas as $fila) {
            $valores = [];
            foreach (array_keys($columnas) as $clave) {
                $valores[] = self::escalar($fila[$clave] ?? '');
            }
            $lineas[] = self::linea($valores, $delimitador);
        }

        return implode($eol, $lineas) . $eol;
    }

    /**
     * @param list<string> $valores
     */
    private static function linea(array $valores, string $delimitador): string
    {
        return implode($delimitador, array_map(
            static fn (string $v) => self::escapar($v, $delimitador),
            $valores,
        ));
    }

    private static function escapar(string $valor, string $delimitador): string
    {
        $necesitaComillas = str_contains($valor, $delimitador)
            || str_contains($valor, '"')
            || str_contains($valor, "\n")
            || str_contains($valor, "\r");

        if (!$necesitaComillas) {
            return $valor;
        }

        return '"' . str_replace('"', '""', $valor) . '"';
    }

    private static function escalar(mixed $valor): string
    {
        if ($valor === null) {
            return '';
        }

        if (is_bool($valor)) {
            return $valor ? '1' : '0';
        }

        return (string) $valor;
    }
}
