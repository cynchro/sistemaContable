<?php

namespace Tests\Unit\Modules\Iva;

use App\Modules\Iva\Calc\DeclaracionIvaCalculator;
use Tests\Unit\UnitTestCase;

class DeclaracionIvaCalculatorTest extends UnitTestCase
{
    private DeclaracionIvaCalculator $calc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calc = new DeclaracionIvaCalculator();
    }

    public function test_saldo_usa_credito_computable_no_iva_total(): void
    {
        $ventas = [
            ['neto_gravado' => '1000.00', 'iva' => '210.00'],
            ['neto_gravado' => '400.00', 'iva' => '42.00'],
        ];
        $compras = [
            // IVA 200 pero solo 150 computable.
            ['neto_gravado' => '952.38', 'iva' => '200.00', 'cf_computable' => '150.00'],
        ];

        $r = $this->calc->consolidar($ventas, $compras);

        $this->assertSame('1400.00', $r['debito_neto']);
        $this->assertSame('252.00', $r['debito_iva']);
        $this->assertSame('200.00', $r['credito_iva']);
        $this->assertSame('150.00', $r['credito_computable']);
        // Saldo = débito 252 − crédito computable 150 = 102 (no 252 - 200).
        $this->assertSame('102.00', $r['saldo_tecnico']);
    }

    public function test_saldo_a_favor(): void
    {
        $r = $this->calc->consolidar(
            [['neto_gravado' => '100.00', 'iva' => '21.00']],
            [['neto_gravado' => '1000.00', 'iva' => '210.00', 'cf_computable' => '210.00']],
        );

        $this->assertSame('-189.00', $r['saldo_tecnico']); // 21 - 210
    }

    public function test_periodo_vacio(): void
    {
        $r = $this->calc->consolidar([], []);

        $this->assertSame('0.00', $r['debito_iva']);
        $this->assertSame('0.00', $r['credito_computable']);
        $this->assertSame('0.00', $r['saldo_tecnico']);
    }
}
