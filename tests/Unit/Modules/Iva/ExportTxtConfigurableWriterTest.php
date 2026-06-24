<?php

namespace Tests\Unit\Modules\Iva;

use Tests\Unit\UnitTestCase;
use App\Modules\Iva\Export\ExportTxtConfigurableWriter;

class ExportTxtConfigurableWriterTest extends UnitTestCase
{
    public function test_delimitado_con_fecha_y_numero(): void
    {
        $writer = new ExportTxtConfigurableWriter();

        $formato = [
            'tipo' => 'delimitado', 'separador' => ';', 'eol' => 'lf',
            'campos' => [
                ['campo' => 'fecha', 'formato_fecha' => 'd/m/Y'],
                ['campo' => 'total', 'decimales' => 2, 'sep_decimal' => ','],
            ],
        ];
        $filas = [
            ['fecha' => '2026-01-10', 'total' => '1210.5'],
            ['fecha' => '2026-01-20', 'total' => '99'],
        ];

        $this->assertSame("10/01/2026;1210,50\n20/01/2026;99,00\n", $writer->generar($formato, $filas));
    }

    public function test_ancho_fijo_con_relleno_y_alineacion(): void
    {
        $writer = new ExportTxtConfigurableWriter();

        $formato = [
            'tipo' => 'fijo', 'separador' => '', 'eol' => 'crlf',
            'campos' => [
                ['campo' => 'letra', 'longitud' => 3, 'relleno' => ' ', 'alinea' => 'izquierda'],
                ['campo' => 'total', 'longitud' => 10, 'relleno' => '0', 'alinea' => 'derecha', 'decimales' => 2],
            ],
        ];
        $filas = [['letra' => 'A', 'total' => '1210.5']];

        // 'A  ' (3, relleno derecha) + '0001210.50' (10, relleno izquierda) + CRLF.
        $this->assertSame("A  0001210.50\r\n", $writer->generar($formato, $filas));
    }

    public function test_ancho_fijo_trunca_si_excede(): void
    {
        $writer = new ExportTxtConfigurableWriter();

        $formato = [
            'tipo' => 'fijo', 'separador' => '', 'eol' => 'lf',
            'campos' => [['campo' => 'contraparte_nombre', 'longitud' => 5, 'alinea' => 'izquierda']],
        ];
        $filas = [['contraparte_nombre' => 'PROVEEDOR LARGO SA']];

        $this->assertSame("PROVE\n", $writer->generar($formato, $filas));
    }

    public function test_sin_filas_devuelve_vacio(): void
    {
        $writer = new ExportTxtConfigurableWriter();
        $formato = ['tipo' => 'delimitado', 'separador' => ';', 'eol' => 'lf', 'campos' => []];

        $this->assertSame('', $writer->generar($formato, []));
    }
}
