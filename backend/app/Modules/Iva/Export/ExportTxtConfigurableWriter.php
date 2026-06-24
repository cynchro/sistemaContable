<?php

namespace App\Modules\Iva\Export;

use App\Support\Calc\Decimal;
use DateTimeImmutable;

/**
 * Genera el TXT del subdiario según un formato definido por el estudio (delimitado o
 * de ancho fijo). Lógica pura: recibe la definición del formato y las filas ya
 * consultadas, y devuelve el texto.
 *
 * Cada campo del formato referencia un campo del {@see ExportCampoCatalogo} y trae su
 * formato (longitud/relleno/alineación para fijo, decimales/sep_decimal para números,
 * formato_fecha para fechas).
 */
final class ExportTxtConfigurableWriter
{
    /**
     * @param array{tipo: string, separador: string, eol: string, campos: list<array<string, mixed>>} $formato
     * @param list<array<string, mixed>> $filas
     */
    public function generar(array $formato, array $filas): string
    {
        $esFijo = $formato['tipo'] === 'fijo';
        $sep    = (string) $formato['separador'];
        $eol    = $formato['eol'] === 'crlf' ? "\r\n" : "\n";
        $campos = $formato['campos'];

        $lineas = array_map(
            fn (array $fila): string => $this->linea($fila, $campos, $esFijo, $sep),
            $filas,
        );

        return $lineas === [] ? '' : implode($eol, $lineas) . $eol;
    }

    /**
     * @param array<string, mixed>       $fila
     * @param list<array<string, mixed>> $campos
     */
    private function linea(array $fila, array $campos, bool $esFijo, string $sep): string
    {
        $valores = array_map(
            fn (array $campo): string => $this->valor($fila, $campo, $esFijo),
            $campos,
        );

        return $esFijo ? implode('', $valores) : implode($sep, $valores);
    }

    /**
     * @param array<string, mixed> $fila
     * @param array<string, mixed> $campo
     */
    private function valor(array $fila, array $campo, bool $esFijo): string
    {
        $nombre = (string) ($campo['campo'] ?? '');
        $crudo  = $fila[$nombre] ?? '';

        $texto = match (ExportCampoCatalogo::tipo($nombre)) {
            ExportCampoCatalogo::FECHA  => $this->fecha((string) $crudo, (string) ($campo['formato_fecha'] ?? 'Y-m-d')),
            ExportCampoCatalogo::NUMERO => $this->numero(
                (string) $crudo,
                (int) ($campo['decimales'] ?? 2),
                (string) ($campo['sep_decimal'] ?? '.'),
            ),
            default => (string) $crudo,
        };

        return $esFijo ? $this->ajustar($texto, $campo) : $texto;
    }

    private function fecha(string $valor, string $formato): string
    {
        if ($valor === '') {
            return '';
        }
        $fecha = DateTimeImmutable::createFromFormat('Y-m-d', substr($valor, 0, 10));

        return $fecha === false ? $valor : $fecha->format($formato);
    }

    private function numero(string $valor, int $decimales, string $sepDecimal): string
    {
        // Decimal exacto (bcmath); sin separador de miles.
        $formateado = Decimal::of($valor === '' ? '0' : $valor)->value(max(0, $decimales));

        return $sepDecimal === '.' ? $formateado : str_replace('.', $sepDecimal, $formateado);
    }

    /** Ajuste a longitud fija: recorta si excede, rellena según alineación si falta. */
    private function ajustar(string $texto, array $campo): string
    {
        $longitud = (int) ($campo['longitud'] ?? 0);
        if ($longitud <= 0) {
            return $texto;
        }

        if (mb_strlen($texto) > $longitud) {
            return mb_substr($texto, 0, $longitud);
        }

        $relleno = (string) ($campo['relleno'] ?? ' ');
        $relleno = $relleno === '' ? ' ' : mb_substr($relleno, 0, 1);
        $tipo    = ($campo['alinea'] ?? 'izquierda') === 'derecha' ? STR_PAD_LEFT : STR_PAD_RIGHT;
        $faltan  = $longitud - mb_strlen($texto);
        $pad     = str_repeat($relleno, $faltan);

        return $tipo === STR_PAD_LEFT ? $pad . $texto : $texto . $pad;
    }
}
