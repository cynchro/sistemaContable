<?php

namespace Tests\Unit\Modules\Honorarios;

use App\Modules\Honorarios\Calc\HonorarioCalculator;
use Tests\Unit\UnitTestCase;

class HonorarioCalculatorTest extends UnitTestCase
{
    private HonorarioCalculator $calc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calc = new HonorarioCalculator();
    }

    public function test_importe_uc_por_valor_por_factor_por_cantidad(): void
    {
        // valor_uc = 1000; línea: 10 UC, factor 1.5, cantidad 2 → 10*1000*1.5*2 = 30000
        $r = $this->calc->calcular('1000', [
            ['uc' => '10', 'factor' => '1.5', 'cantidad' => '2'],
        ]);

        $this->assertSame('30000.00', $r['lineas'][0]['importe']);
        $this->assertSame('30000.00', $r['total']);
    }

    public function test_varias_lineas_suman(): void
    {
        $r = $this->calc->calcular('500', [
            ['uc' => '4', 'factor' => '1', 'cantidad' => '1'],   // 2000
            ['uc' => '6', 'factor' => '2', 'cantidad' => '1'],   // 6000
        ]);

        $this->assertSame('2000.00', $r['lineas'][0]['importe']);
        $this->assertSame('6000.00', $r['lineas'][1]['importe']);
        $this->assertSame('8000.00', $r['total']);
    }

    public function test_defaults_factor_y_cantidad(): void
    {
        // sin factor ni cantidad → 1
        $r = $this->calc->calcular('1000', [['uc' => '3']]);
        $this->assertSame('3000.00', $r['lineas'][0]['importe']);
    }

    public function test_sin_lineas(): void
    {
        $r = $this->calc->calcular('1000', []);
        $this->assertSame([], $r['lineas']);
        $this->assertSame('0.00', $r['total']);
    }
}
