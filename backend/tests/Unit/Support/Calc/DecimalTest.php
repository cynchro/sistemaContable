<?php

namespace Tests\Unit\Support\Calc;

use App\Support\Calc\Decimal;
use InvalidArgumentException;
use Tests\Unit\UnitTestCase;

class DecimalTest extends UnitTestCase
{
    public function test_suma_y_resta_exactas(): void
    {
        $this->assertSame('0.3', Decimal::of('0.1')->add(Decimal::of('0.2'))->value());
        $this->assertSame('999999999999999.99', Decimal::of('1000000000000000.00')->sub(Decimal::of('0.01'))->value(2));
    }

    public function test_multiplicacion_y_division(): void
    {
        $this->assertSame('150', Decimal::of('15')->mul(Decimal::of('10'))->value());
        $this->assertSame('3.33', Decimal::of('10')->div(Decimal::of('3'))->value(2));
    }

    public function test_division_por_cero_lanza(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Decimal::of('10')->div(Decimal::zero());
    }

    public function test_porcentaje_iva_21(): void
    {
        // Caso típico de discriminación: neto gravado al 21% → IVA.
        $iva = Decimal::of('1000.00')->percentage(Decimal::of('21.000'));
        $this->assertSame('210.00', $iva->value(2));
    }

    public function test_porcentaje_iva_105_con_redondeo(): void
    {
        $iva = Decimal::of('333.33')->percentage(Decimal::of('10.500'));
        // 333.33 * 0.105 = 34.99965 → 35.00
        $this->assertSame('35.00', $iva->value(2));
    }

    public function test_redondeo_half_up_aleja_del_cero(): void
    {
        $this->assertSame('2.46', Decimal::of('2.455')->value(2));
        $this->assertSame('-2.46', Decimal::of('-2.455')->value(2));
        $this->assertSame('2.45', Decimal::of('2.454')->value(2));
    }

    public function test_truncate(): void
    {
        $this->assertSame('2.45', Decimal::of('2.459')->round(2, Decimal::ROUND_TRUNCATE)->value(2));
    }

    public function test_value_fija_escala_para_persistir(): void
    {
        $this->assertSame('5.00', Decimal::of('5')->value(2));
        $this->assertSame('21.000', Decimal::of('21')->value(3));
        $this->assertSame('5', Decimal::of('5.00')->value());
    }

    public function test_signo_y_comparacion(): void
    {
        $this->assertTrue(Decimal::of('0.00')->isZero());
        $this->assertTrue(Decimal::of('-1')->isNegative());
        $this->assertTrue(Decimal::of('1')->isPositive());
        $this->assertSame(1, Decimal::of('2')->compareTo(Decimal::of('1')));
        $this->assertSame(-1, Decimal::of('1')->compareTo(Decimal::of('2')));
        $this->assertTrue(Decimal::of('1.50')->equals(Decimal::of('1.5')));
    }

    public function test_negate_y_abs(): void
    {
        $this->assertSame('-5', Decimal::of('5')->negate()->value());
        $this->assertSame('5', Decimal::of('-5')->abs()->value());
    }

    public function test_suma_ponderada_por_signo_de_comprobante(): void
    {
        // Regla del legacy: total de período = Σ(importe · signo). NC resta.
        $factura = Decimal::of('1210.00')->mul(Decimal::of('1'));   // signo +1
        $notaCr  = Decimal::of('500.00')->mul(Decimal::of('-1'));   // signo -1
        $this->assertSame('710.00', $factura->add($notaCr)->value(2));
    }

    public function test_valor_invalido_lanza(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Decimal::of('abc');
    }
}
