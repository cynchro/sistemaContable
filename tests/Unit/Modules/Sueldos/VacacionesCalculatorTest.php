<?php

namespace Tests\Unit\Modules\Sueldos;

use Tests\Unit\UnitTestCase;
use App\Modules\Sueldos\Calc\VacacionesCalculator;

class VacacionesCalculatorTest extends UnitTestCase
{
    private VacacionesCalculator $calc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calc = new VacacionesCalculator();
    }

    public function test_dias_segun_antiguedad(): void
    {
        $this->assertSame(14, $this->calc->diasPorAntiguedad(0));
        $this->assertSame(14, $this->calc->diasPorAntiguedad(5));
        $this->assertSame(21, $this->calc->diasPorAntiguedad(6));
        $this->assertSame(21, $this->calc->diasPorAntiguedad(10));
        $this->assertSame(28, $this->calc->diasPorAntiguedad(11));
        $this->assertSame(28, $this->calc->diasPorAntiguedad(20));
        $this->assertSame(35, $this->calc->diasPorAntiguedad(21));
    }

    public function test_valor_dia_e_importe(): void
    {
        // valor día = 25.000 / 25 = 1.000; 14 días → 14.000.
        $this->assertSame('1000.00', $this->calc->valorDia('25000'));
        $this->assertSame('14000.00', $this->calc->importe('25000', 14));
    }
}
