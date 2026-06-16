<?php

namespace Tests\Unit\Modules\Sueldos;

use App\Modules\Sueldos\Calc\ContribucionCalculator;
use Tests\Unit\UnitTestCase;

class ContribucionCalculatorTest extends UnitTestCase
{
    private ContribucionCalculator $calc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calc = new ContribucionCalculator();
    }

    public function test_porcentaje_sobre_base_remunerativa(): void
    {
        $r = $this->calc->calcular(
            ['remunerativo' => '1000000', 'no_remunerativo' => '200000'],
            [
                ['id' => 1, 'descripcion' => 'Jub', 'porcentaje' => '12.350'],
                ['id' => 2, 'descripcion' => 'ART', 'importe_fijo' => '1624'],
            ],
        );

        $this->assertSame('123500.00', $r['lineas'][0]['importe_total']); // 1.000.000 * 12,35%
        $this->assertSame('1000000.00', $r['lineas'][0]['base_imponible']);
        $this->assertSame('1624.00', $r['lineas'][1]['importe_total']);    // importe fijo
        $this->assertSame('125124.00', $r['total']);
    }

    public function test_incluye_no_remunerativo_en_la_base(): void
    {
        $r = $this->calc->calcular(
            ['remunerativo' => '1000000', 'no_remunerativo' => '200000'],
            [['id' => 1, 'descripcion' => 'OS', 'porcentaje' => '6', 'importe_fijo' => '0', 'incluye_norem' => 'S']],
        );

        $this->assertSame('1200000.00', $r['lineas'][0]['base_imponible']);
        $this->assertSame('72000.00', $r['lineas'][0]['importe_total']); // 1.200.000 * 6%
    }

    public function test_sin_contribuciones(): void
    {
        $r = $this->calc->calcular(['remunerativo' => '1000', 'no_remunerativo' => '0'], []);
        $this->assertSame([], $r['lineas']);
        $this->assertSame('0.00', $r['total']);
    }
}
