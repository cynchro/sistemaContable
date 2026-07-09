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

    public function test_detraccion_resta_de_la_base_solo_si_aplica(): void
    {
        // B6 (Dto 99/2019): la detracción resta de la base SOLO en las contribuciones que la aplican.
        $r = $this->calc->calcular(
            ['remunerativo' => '1000000', 'no_remunerativo' => '0'],
            [
                ['id' => 1, 'descripcion' => 'Jub (SIPA)', 'porcentaje' => '10', 'aplica_detraccion' => 'S'],
                ['id' => 2, 'descripcion' => 'Obra Social', 'porcentaje' => '6', 'aplica_detraccion' => 'N'],
            ],
            '300000', // detracción de empresa
        );

        // SIPA: base 1.000.000 − 300.000 = 700.000 → 10% = 70.000
        $this->assertSame('700000.00', $r['lineas'][0]['base_imponible']);
        $this->assertSame('300000.00', $r['lineas'][0]['detraccion']);
        $this->assertSame('70000.00', $r['lineas'][0]['importe_total']);
        // Obra social: no aplica detracción → base completa 1.000.000 → 6% = 60.000
        $this->assertSame('1000000.00', $r['lineas'][1]['base_imponible']);
        $this->assertSame('0.00', $r['lineas'][1]['detraccion']);
        $this->assertSame('60000.00', $r['lineas'][1]['importe_total']);
    }

    public function test_topes_acotan_la_base(): void
    {
        $r = $this->calc->calcular(
            ['remunerativo' => '2000000', 'no_remunerativo' => '0'],
            [['id' => 1, 'descripcion' => 'Jub', 'porcentaje' => '10', 'aplica_topes' => 'S', 'tope_max' => '900000']],
        );
        // base 2.000.000 acotada al tope máx 900.000 → 10% = 90.000
        $this->assertSame('900000.00', $r['lineas'][0]['base_imponible']);
        $this->assertSame('90000.00', $r['lineas'][0]['importe_total']);
    }

    public function test_detraccion_no_baja_la_base_de_cero(): void
    {
        $r = $this->calc->calcular(
            ['remunerativo' => '100000', 'no_remunerativo' => '0'],
            [['id' => 1, 'descripcion' => 'Jub', 'porcentaje' => '10', 'aplica_detraccion' => 'S']],
            '300000', // detracción mayor que la base
        );
        $this->assertSame('0.00', $r['lineas'][0]['base_imponible']);
        $this->assertSame('100000.00', $r['lineas'][0]['detraccion']);
        $this->assertSame('0.00', $r['lineas'][0]['importe_total']);
    }
}
