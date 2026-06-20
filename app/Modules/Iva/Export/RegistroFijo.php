<?php

namespace App\Modules\Iva\Export;

use RuntimeException;
use App\Support\Calc\Decimal;

/**
 * Constructor de un registro de ancho fijo para el Libro IVA Digital (Portal IVA).
 * Acumula campos y al cerrar valida que el largo total coincida con el del diseño
 * de registro de ARCA (`disenio_registro_IVA_digital.pdf`).
 *
 * Convenciones del formato (validadas contra los TXT de ejemplo reales):
 *  - textos: en MAYÚSCULAS, sin acentos, alineados a la izquierda y completados con
 *    espacios a la derecha (truncados si exceden);
 *  - números (ids/CUIT/punto de venta/número): solo dígitos, alineados a la derecha y
 *    completados con ceros a la izquierda;
 *  - importes: 13 enteros + 2 decimales SIN punto decimal, ceros a la izquierda (15);
 *    siempre positivos (el signo lo da el tipo de comprobante);
 *  - tipo de cambio: 4 enteros + 6 decimales sin punto (10);
 *  - fechas: AAAAMMDD (8 ceros si no hay).
 */
final class RegistroFijo
{
    private string $buffer = '';

    /** Texto a la izquierda, mayúsculas sin acentos, relleno con espacios a la derecha. */
    public function texto(?string $valor, int $largo): self
    {
        $v = strtoupper($this->sinAcentos((string) $valor));
        $v = substr($v, 0, $largo);

        return $this->raw(str_pad($v, $largo, ' ', STR_PAD_RIGHT));
    }

    /** Número (solo dígitos) a la derecha, relleno con ceros a la izquierda. */
    public function entero(int|string|null $valor, int $largo): self
    {
        $digitos = preg_replace('/\D/', '', (string) $valor) ?? '';
        $digitos = substr($digitos, -$largo);

        return $this->raw(str_pad($digitos, $largo, '0', STR_PAD_LEFT));
    }

    /** Importe 13+2 (o el largo indicado) sin punto, positivo, ceros a la izquierda. */
    public function importe(int|float|string|null $valor, int $enteros = 13, int $decimales = 2): self
    {
        $largo  = $enteros + $decimales;
        $sinPunto = str_replace('.', '', Decimal::of((string) ($valor ?? 0))->abs()->value($decimales));
        $sinPunto = substr($sinPunto, -$largo);

        return $this->raw(str_pad($sinPunto, $largo, '0', STR_PAD_LEFT));
    }

    /** Tipo de cambio: 4 enteros + 6 decimales sin punto (10). */
    public function cambio(int|float|string|null $valor): self
    {
        return $this->importe($valor === null || $valor === '' ? '1' : $valor, 4, 6);
    }

    /** Fecha AAAAMMDD desde 'Y-m-d' (8 ceros si viene vacía). */
    public function fecha(?string $valor): self
    {
        $ts = $valor === null || $valor === '' ? false : strtotime($valor);

        return $this->raw($ts === false ? '00000000' : date('Ymd', $ts));
    }

    public function raw(string $valor): self
    {
        $this->buffer .= $valor;

        return $this;
    }

    /** Cierra el registro validando el largo exacto contra el diseño de ARCA. */
    public function build(int $largoEsperado): string
    {
        if (strlen($this->buffer) !== $largoEsperado) {
            throw new RuntimeException(sprintf(
                'Registro Libro IVA Digital con largo %d, se esperaba %d.',
                strlen($this->buffer),
                $largoEsperado,
            ));
        }

        return $this->buffer;
    }

    private function sinAcentos(string $v): string
    {
        return strtr($v, [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N',
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ]);
    }
}
