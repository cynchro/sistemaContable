<?php

namespace Tests\Unit\Modules\Iva;

use App\Modules\Iva\Calc\LibroIvaCalculator;
use Tests\Unit\UnitTestCase;

class LibroIvaCalculatorTest extends UnitTestCase
{
    private LibroIvaCalculator $calc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calc = new LibroIvaCalculator();
    }

    public function test_totales_simples(): void
    {
        $r = $this->calc->totalesPeriodo(
            ventasTotal: [['signo' => 1, 'importe' => '1210.00']],
            ventasIva: [['signo' => 1, 'importe' => '210.00']],
            comprasTotal: [['signo' => 1, 'importe' => '605.00']],
            comprasIva: [['signo' => 1, 'importe' => '105.00']],
        );

        $this->assertSame('1210.00', $r['total_ventas']);
        $this->assertSame('605.00', $r['total_compras']);
        $this->assertSame('210.00', $r['iva_ventas']);
        $this->assertSame('105.00', $r['iva_compras']);
        $this->assertSame('105.00', $r['saldo_iva']); // 210 - 105 a pagar
    }

    public function test_nota_de_credito_resta_por_signo(): void
    {
        // Factura 1210 (signo +1) y NC 210 (signo -1) → ventas netas 1000.
        $r = $this->calc->totalesPeriodo(
            ventasTotal: [['signo' => 1, 'importe' => '1210.00'], ['signo' => -1, 'importe' => '210.00']],
            ventasIva: [['signo' => 1, 'importe' => '210.00'], ['signo' => -1, 'importe' => '36.45']],
            comprasTotal: [],
            comprasIva: [],
        );

        $this->assertSame('1000.00', $r['total_ventas']);
        $this->assertSame('173.55', $r['iva_ventas']); // 210 - 36.45
        $this->assertSame('0.00', $r['total_compras']);
        $this->assertSame('173.55', $r['saldo_iva']);
    }

    public function test_saldo_a_favor_cuando_credito_supera_debito(): void
    {
        $r = $this->calc->totalesPeriodo(
            ventasTotal: [['signo' => 1, 'importe' => '1000.00']],
            ventasIva: [['signo' => 1, 'importe' => '100.00']],
            comprasTotal: [['signo' => 1, 'importe' => '2000.00']],
            comprasIva: [['signo' => 1, 'importe' => '250.00']],
        );

        $this->assertSame('-150.00', $r['saldo_iva']); // 100 - 250 a favor
    }

    public function test_periodo_vacio(): void
    {
        $r = $this->calc->totalesPeriodo([], [], [], []);

        $this->assertSame('0.00', $r['total_ventas']);
        $this->assertSame('0.00', $r['iva_compras']);
        $this->assertSame('0.00', $r['saldo_iva']);
    }
}
