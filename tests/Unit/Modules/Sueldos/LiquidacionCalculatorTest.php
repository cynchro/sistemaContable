<?php

namespace Tests\Unit\Modules\Sueldos;

use App\Modules\Sueldos\Calc\FormulaEvaluator;
use App\Modules\Sueldos\Calc\LiquidacionCalculator;
use Tests\Unit\UnitTestCase;

class LiquidacionCalculatorTest extends UnitTestCase
{
    private LiquidacionCalculator $calc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calc = new LiquidacionCalculator(new FormulaEvaluator());
    }

    public function test_liquidacion_basico_antiguedad_y_descuento(): void
    {
        $empleado = ['basico' => '900000', 'antiguedad' => 10];
        $conceptos = [
            ['id' => 1, 'codigo' => '001', 'descripcion' => 'Básico', 'formula' => 'BASICO/30*CAN', 'tipo' => 1],
            ['id' => 2, 'codigo' => '110', 'descripcion' => 'Antig', 'formula' => 'BASICO*(ANTIG/100)', 'tipo' => 1],
            ['id' => 3, 'codigo' => '500', 'descripcion' => 'Jub', 'formula' => '(001#+110#)*0.11', 'tipo' => 3],
        ];
        $novedades = [
            1 => ['cantidad' => 30, 'importe' => 0],
            2 => ['cantidad' => 1, 'importe' => 0],
            3 => ['cantidad' => 1, 'importe' => 0],
        ];

        $r = $this->calc->liquidar($empleado, $conceptos, $novedades);

        $this->assertCount(3, $r['lineas']);
        $this->assertSame('900000.00', $r['lineas'][0]['importe']); // 900000/30*30
        $this->assertSame('90000.00', $r['lineas'][1]['importe']);  // 900000*10/100
        $this->assertSame('108900.00', $r['lineas'][2]['importe']); // (900000+90000)*0.11
        $this->assertSame('990000.00', $r['total_haberes']);
        $this->assertSame('108900.00', $r['total_descuentos']);
        $this->assertSame('881100.00', $r['neto']);
    }

    public function test_solo_liquida_conceptos_con_novedad(): void
    {
        $conceptos = [
            ['id' => 1, 'codigo' => '001', 'formula' => 'BASICO', 'tipo' => 1],
            ['id' => 2, 'codigo' => '002', 'formula' => 'BASICO', 'tipo' => 1],
        ];
        // Sólo el concepto 1 tiene novedad.
        $r = $this->calc->liquidar(['basico' => '100', 'antiguedad' => 0], $conceptos, [1 => ['cantidad' => 1]]);

        $this->assertCount(1, $r['lineas']);
        $this->assertSame('001', $r['lineas'][0]['codigo']);
    }

    public function test_acumulador_norem(): void
    {
        $conceptos = [
            ['id' => 1, 'codigo' => '300', 'formula' => 'IMP', 'tipo' => 2], // no remunerativo
            ['id' => 2, 'codigo' => '600', 'formula' => 'NOREM', 'tipo' => 1],
        ];
        $novedades = [
            1 => ['cantidad' => 1, 'importe' => '50000'],
            2 => ['cantidad' => 1, 'importe' => 0],
        ];

        $r = $this->calc->liquidar(['basico' => '0', 'antiguedad' => 0], $conceptos, $novedades);

        $this->assertSame('50000.00', $r['lineas'][0]['importe']);
        // El segundo concepto lee NOREM = lo acumulado por el no remunerativo.
        $this->assertSame('50000.00', $r['lineas'][1]['importe']);
    }

    public function test_periodo_sin_novedades_da_neto_cero(): void
    {
        $conceptos = [['id' => 1, 'codigo' => '001', 'formula' => 'BASICO', 'tipo' => 1]];
        $r = $this->calc->liquidar(['basico' => '100'], $conceptos, []);

        $this->assertSame([], $r['lineas']);
        $this->assertSame('0.00', $r['neto']);
    }
}
