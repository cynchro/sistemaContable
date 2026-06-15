<?php

namespace Tests\Unit\Modules\Sueldos;

use App\Modules\Sueldos\Calc\AntiguedadCalculator;
use Tests\Unit\UnitTestCase;

class AntiguedadCalculatorTest extends UnitTestCase
{
    private AntiguedadCalculator $calc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calc = new AntiguedadCalculator();
    }

    public function test_anios_cumplidos(): void
    {
        $this->assertSame(10, $this->calc->aniosCumplidos('2016-01-15', '2026-06-15'));
    }

    public function test_aun_no_cumple_el_anio(): void
    {
        // 5 años menos un día → 4 años cumplidos.
        $this->assertSame(4, $this->calc->aniosCumplidos('2021-06-15', '2026-06-14'));
    }

    public function test_ingreso_posterior_al_corte_da_cero(): void
    {
        $this->assertSame(0, $this->calc->aniosCumplidos('2027-01-01', '2026-06-15'));
    }

    public function test_fechas_nulas_dan_cero(): void
    {
        $this->assertSame(0, $this->calc->aniosCumplidos(null, '2026-06-15'));
        $this->assertSame(0, $this->calc->aniosCumplidos('2020-01-01', null));
    }
}
