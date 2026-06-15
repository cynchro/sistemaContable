<?php

namespace Tests\Unit\Modules\Sueldos;

use App\Modules\Sueldos\Calc\FormulaEvaluator;
use InvalidArgumentException;
use Tests\Unit\UnitTestCase;

/**
 * Reproduce fórmulas reales de CONCEPTOS del legacy Vsueldos (sueldos_data.sql)
 * para validar el evaluador 1:1.
 */
class FormulaEvaluatorTest extends UnitTestCase
{
    private FormulaEvaluator $eval;

    protected function setUp(): void
    {
        parent::setUp();
        $this->eval = new FormulaEvaluator();
    }

    public function test_suspension_negativa(): void
    {
        // -((BASICO/30)*CAN)
        $r = $this->eval->evaluar('-((BASICO/30)*CAN)', ['BASICO' => '900000', 'CAN' => 2]);
        $this->assertSame('-60000.00', $r->value(2));
    }

    public function test_licencia_basico_sobre_dias(): void
    {
        $r = $this->eval->evaluar('BASICO/25*CAN', ['BASICO' => '850000', 'CAN' => 1]);
        $this->assertSame('34000.00', $r->value(2));
    }

    public function test_variable_imp_sola(): void
    {
        $r = $this->eval->evaluar('IMP', ['IMP' => '12345.67']);
        $this->assertSame('12345.67', $r->value(2));
    }

    public function test_referencias_a_conceptos(): void
    {
        // (001#+002#+003#)/25*CAN
        $r = $this->eval->evaluar(
            '(001#+002#+003#)/25*CAN',
            ['CAN' => 5],
            ['001' => '100', '002' => '200', '003' => '300'],
        );
        $this->assertSame('120.00', $r->value(2));
    }

    public function test_antiguedad_porcentual(): void
    {
        $r = $this->eval->evaluar('IMP*(ANTIG/100)', ['IMP' => '1000', 'ANTIG' => '10']);
        $this->assertSame('100.00', $r->value(2));
    }

    public function test_sac_con_literales_y_antiguedad(): void
    {
        // (BASICO+(BASICO*(ANTIG/100))+750)/365*CAN
        $r = $this->eval->evaluar(
            '(BASICO+(BASICO*(ANTIG/100))+750)/365*CAN',
            ['BASICO' => '365000', 'ANTIG' => '0', 'CAN' => 1],
        );
        $this->assertSame('1002.05', $r->value(2)); // 365750/365
    }

    public function test_rango_de_conceptos_suma(): void
    {
        // 001#...003#  → suma de 001,002,003 (excluye 004)
        $r = $this->eval->evaluar(
            '001#...003#',
            [],
            ['001' => '10', '002' => '20', '003' => '30', '004' => '40'],
        );
        $this->assertSame('60.00', $r->value(2));
    }

    public function test_concepto_no_liquidado_vale_cero(): void
    {
        $r = $this->eval->evaluar('005#+001#', [], ['001' => '10']);
        $this->assertSame('10.00', $r->value(2));
    }

    public function test_literal_decimal(): void
    {
        $r = $this->eval->evaluar('IMP*0.083333', ['IMP' => '1000']);
        $this->assertSame('83.333', $r->value(3));
    }

    public function test_division_por_cero_lanza(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->eval->evaluar('IMP/0', ['IMP' => '100']);
    }

    public function test_variable_desconocida_lanza(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->eval->evaluar('SUELDO_INEXISTENTE*2', []);
    }

    public function test_caracter_invalido_lanza(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->eval->evaluar('BASICO & 2', ['BASICO' => '1']);
    }

    public function test_formula_vacia_da_cero(): void
    {
        $this->assertSame('0.00', $this->eval->evaluar('', [])->value(2));
    }
}
