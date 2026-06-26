<?php

namespace Tests\Unit\Modules\Iva;

use App\Exceptions\ValidationException;
use App\Modules\Iva\Export\DjIvaSimpleWriter;
use Tests\Unit\UnitTestCase;

/**
 * Valida el formato de los 4 CSV de la DJ IVA Simple (apertura de otros conceptos):
 * separador ';', decimal ',', recorte de ceros, CRLF, mapeo de alícuota y de tipo de
 * sujeto comprador, y los supuestos de v1 (tipo op 1 sin bienes de uso, ODP 0,
 * crédito fiscal concepto 1).
 */
class DjIvaSimpleWriterTest extends UnitTestCase
{
    private DjIvaSimpleWriter $writer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->writer = new DjIvaSimpleWriter();
    }

    public function test_debito_fiscal_agrupa_por_sujeto_y_alicuota(): void
    {
        $gravado = [
            ['condicion_iva_id' => 1, 'alicuota' => '21.000', 'neto' => '100', 'iva' => '21'],     // RI
            ['condicion_iva_id' => 3, 'alicuota' => '21.000', 'neto' => '200', 'iva' => '42'],     // Monotributo
            ['condicion_iva_id' => 5, 'alicuota' => '21.000', 'neto' => '300', 'iva' => '63'],     // Consumidor Final
            ['condicion_iva_id' => 1, 'alicuota' => '10.500', 'neto' => '50',  'iva' => '5.25'],   // RI 10,5%
        ];

        $out = $this->writer->debitoFiscal('620100', $gravado, [], '1234.56');

        $this->assertSame(
            "620100;1;1;5;100;21;0;\r\n" .
            "620100;1;2;5;200;42;0;\r\n" .
            "620100;1;3;5;300;63;0;\r\n" .
            "620100;1;1;4;50;5,25;0;\r\n" .
            "620100;3;;;;;;1234,56\r\n",
            $out,
        );
    }

    public function test_debito_fiscal_excluye_exportacion_y_sin_no_gravado(): void
    {
        $gravado = [
            ['condicion_iva_id' => 9, 'alicuota' => '21.000', 'neto' => '100', 'iva' => '21'], // exterior → excluido
            ['condicion_iva_id' => 1, 'alicuota' => '21.000', 'neto' => '500', 'iva' => '105'],
        ];

        $out = $this->writer->debitoFiscal('620100', $gravado, [], '0');

        $this->assertSame("620100;1;1;5;500;105;0;\r\n", $out);
    }

    public function test_debito_fiscal_bienes_de_uso_van_tipo_op_2(): void
    {
        $normal  = [['condicion_iva_id' => 1, 'alicuota' => '21.000', 'neto' => '1000', 'iva' => '210']];
        $bienUso = [['condicion_iva_id' => 1, 'alicuota' => '21.000', 'neto' => '500', 'iva' => '105']];

        $out = $this->writer->debitoFiscal('620100', $normal, $bienUso, '0');

        $this->assertSame(
            "620100;1;1;5;1000;210;0;\r\n" .   // venta común → tipo op 1
            "620100;2;1;5;500;105;0;\r\n",     // bien de uso → tipo op 2
            $out,
        );
    }

    public function test_credito_fiscal_usa_el_concepto_de_la_compra(): void
    {
        $out = $this->writer->creditoFiscal([
            ['concepto' => 3, 'alicuota' => '21.000', 'neto' => '100', 'iva' => '21', 'cf' => '21'], // servicios
            ['concepto' => 4, 'alicuota' => '21.000', 'neto' => '200', 'iva' => '42', 'cf' => '42'], // inv. BU
            ['alicuota' => '21.000', 'neto' => '50', 'iva' => '10.5', 'cf' => '10.5'], // sin concepto → 1
        ]);

        $this->assertSame(
            "3;5;100;21;21\r\n" .
            "4;5;200;42;42\r\n" .
            "1;5;50;10,5;10,5\r\n",
            $out,
        );
    }

    public function test_restitucion_debito_usa_tipo_op_1_y_2(): void
    {
        $gravado = [['condicion_iva_id' => 1, 'alicuota' => '21.000', 'neto' => '100', 'iva' => '21']];

        $out = $this->writer->restitucionDebito('530010', $gravado, '1234.56');

        $this->assertSame(
            "530010;1;1;5;100;21;\r\n" .
            "530010;2;;;;;1234,56\r\n",
            $out,
        );
    }

    public function test_credito_fiscal_concepto_1_sin_actividad(): void
    {
        $gravado = [
            ['alicuota' => '21.000', 'neto' => '100', 'iva' => '21',   'cf' => '21'],
            ['alicuota' => '10.500', 'neto' => '50',  'iva' => '5.25', 'cf' => '5.25'],
            ['alicuota' => '21.000', 'neto' => '200', 'iva' => '42',   'cf' => '10'],
        ];

        $out = $this->writer->creditoFiscal($gravado);

        $this->assertSame(
            "1;5;100;21;21\r\n" .
            "1;4;50;5,25;5,25\r\n" .
            "1;5;200;42;10\r\n",
            $out,
        );
    }

    public function test_restitucion_credito_cuatro_campos(): void
    {
        $gravado = [['alicuota' => '21.000', 'neto' => '100', 'iva' => '21', 'cf' => '21']];

        $out = $this->writer->restitucionCredito($gravado);

        $this->assertSame("1;5;100;21\r\n", $out);
    }

    public function test_codigos_de_alicuota(): void
    {
        $caso = fn (string $alic) => $this->writer->creditoFiscal(
            [['alicuota' => $alic, 'neto' => '100', 'iva' => '0', 'cf' => '0']],
        );

        $this->assertStringStartsWith('1;3;', $caso('0.000'));    // 0%
        $this->assertStringStartsWith('1;9;', $caso('2.500'));    // 2,5%
        $this->assertStringStartsWith('1;8;', $caso('5.000'));    // 5%
        $this->assertStringStartsWith('1;4;', $caso('10.500'));   // 10,5%
        $this->assertStringStartsWith('1;5;', $caso('21.000'));   // 21%
        $this->assertStringStartsWith('1;6;', $caso('27.000'));   // 27%
    }

    public function test_alicuota_desconocida_lanza(): void
    {
        $this->expectException(ValidationException::class);
        $this->writer->creditoFiscal([['alicuota' => '13.000', 'neto' => '100', 'iva' => '0', 'cf' => '0']]);
    }
}
