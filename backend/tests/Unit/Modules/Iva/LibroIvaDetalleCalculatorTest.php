<?php

namespace Tests\Unit\Modules\Iva;

use App\Modules\Iva\Calc\LibroIvaDetalleCalculator;
use Tests\Unit\UnitTestCase;

class LibroIvaDetalleCalculatorTest extends UnitTestCase
{
    private LibroIvaDetalleCalculator $calc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calc = new LibroIvaDetalleCalculator();
    }

    public function test_agrupa_por_condicion_y_alicuota(): void
    {
        $rows = [
            ['condicion_iva_id' => 1, 'alicuota' => '21.000', 'signo' => 1,
             'neto_gravado' => '1000.00', 'iva' => '210.00'],
            ['condicion_iva_id' => 1, 'alicuota' => '10.500', 'signo' => 1,
             'neto_gravado' => '500.00', 'iva' => '52.50'],
        ];

        $out = $this->calc->agruparPorAlicuota($rows, ['neto_gravado', 'iva']);

        $this->assertCount(2, $out);
        $this->assertSame('21.000', $out[0]['alicuota']);
        $this->assertSame('1000.00', $out[0]['neto_gravado']);
        $this->assertSame('210.00', $out[0]['iva']);
    }

    public function test_misma_alicuota_se_consolida_y_nc_resta(): void
    {
        $rows = [
            ['condicion_iva_id' => 1, 'alicuota' => '21.000', 'signo' => 1,
             'neto_gravado' => '1000.00', 'iva' => '210.00'],
            ['condicion_iva_id' => 1, 'alicuota' => '21.000', 'signo' => -1,
             'neto_gravado' => '200.00', 'iva' => '42.00'],
        ];

        $out = $this->calc->agruparPorAlicuota($rows, ['neto_gravado', 'iva']);

        $this->assertCount(1, $out);
        $this->assertSame('800.00', $out[0]['neto_gravado']); // 1000 - 200
        $this->assertSame('168.00', $out[0]['iva']);          // 210 - 42
    }

    public function test_columna_extra_cf_computable(): void
    {
        $rows = [
            ['condicion_iva_id' => 2, 'alicuota' => '21.000', 'signo' => 1,
             'neto_gravado' => '500.00', 'iva' => '105.00', 'cf_computable' => '105.00'],
        ];

        $out = $this->calc->agruparPorAlicuota($rows, ['neto_gravado', 'iva', 'cf_computable']);

        $this->assertSame('105.00', $out[0]['cf_computable']);
        $this->assertSame(2, $out[0]['condicion_iva_id']);
    }

    public function test_sin_filas(): void
    {
        $this->assertSame([], $this->calc->agruparPorAlicuota([], ['neto_gravado', 'iva']));
    }
}
