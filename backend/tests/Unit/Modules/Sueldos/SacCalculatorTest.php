<?php

namespace Tests\Unit\Modules\Sueldos;

use Tests\Unit\UnitTestCase;
use App\Modules\Sueldos\Calc\SacCalculator;

class SacCalculatorTest extends UnitTestCase
{
    private SacCalculator $calc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calc = new SacCalculator();
    }

    public function test_sac_es_la_mitad_de_la_mejor_remuneracion(): void
    {
        $this->assertSame('50000.00', $this->calc->calcular('100000'));
        $this->assertSame('50000.00', $this->calc->calcular('100000', 180, 180));
        $this->assertSame('0.00', $this->calc->calcular('0'));
    }

    public function test_proporcional_por_dias_trabajados(): void
    {
        // Medio semestre trabajado → mitad del SAC.
        $this->assertSame('25000.00', $this->calc->calcular('100000', 90, 180));
        // Un tercio.
        $this->assertSame('16666.67', $this->calc->calcular('100000', 60, 180));
    }
}
