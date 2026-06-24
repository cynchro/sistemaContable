<?php

namespace Tests\Unit\Support\Csv;

use App\Support\Csv\CsvWriter;
use Tests\Unit\UnitTestCase;

class CsvWriterTest extends UnitTestCase
{
    public function test_encabezados_y_filas(): void
    {
        $csv = CsvWriter::generar(
            ['fecha' => 'Fecha', 'nombre' => 'Nombre', 'total' => 'Total'],
            [
                ['fecha' => '2026-01-10', 'nombre' => 'ACME', 'total' => '1210.00'],
                ['fecha' => '2026-01-11', 'nombre' => 'Otro', 'total' => '500.00'],
            ],
        );

        $this->assertSame(
            "Fecha,Nombre,Total\r\n2026-01-10,ACME,1210.00\r\n2026-01-11,Otro,500.00\r\n",
            $csv,
        );
    }

    public function test_escapa_delimitador_comillas_y_saltos(): void
    {
        $csv = CsvWriter::generar(
            ['nombre' => 'Nombre'],
            [['nombre' => 'Pérez, Juan "el contador"']],
        );

        $this->assertSame("Nombre\r\n\"Pérez, Juan \"\"el contador\"\"\"\r\n", $csv);
    }

    public function test_delimitador_personalizado_y_nulos(): void
    {
        $csv = CsvWriter::generar(
            ['a' => 'A', 'b' => 'B'],
            [['a' => '1', 'b' => null]],
            ';',
        );

        $this->assertSame("A;B\r\n1;\r\n", $csv);
    }

    public function test_clave_faltante_queda_vacia(): void
    {
        $csv = CsvWriter::generar(['a' => 'A', 'b' => 'B'], [['a' => 'x']]);

        $this->assertSame("A,B\r\nx,\r\n", $csv);
    }
}
