<?php

namespace Tests\Unit\Modules\Iva;

use App\Modules\Iva\Calc\IvaSimpleCalculator;
use Tests\Unit\UnitTestCase;

class IvaSimpleCalculatorTest extends UnitTestCase
{
    private IvaSimpleCalculator $calc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calc = new IvaSimpleCalculator();
    }

    /**
     * Caso real de la F.2051 presentada (imagenes/pregunta4.jpeg):
     * crédito y saldo técnico anterior superan al débito → todo queda a favor del
     * contribuyente, y las retenciones/percepciones engrosan la libre disponibilidad.
     */
    public function test_caso_real_f2051_a_favor_del_contribuyente(): void
    {
        $r = $this->calc->calcular(
            '3232369.72',   // débito fiscal
            '4427894.85',   // crédito fiscal computable
            '12190904.63',  // saldo técnico a favor anterior
            '214723.84',    // saldo libre disponibilidad anterior (neto de usos)
            '63685.95',     // retenciones, percepciones y pagos a cuenta
        );

        $det = $r['determinacion_impuesto'];
        $this->assertSame('0.00', $det['saldo_tecnico_a_favor_arca']);
        $this->assertSame('13386429.76', $det['saldo_tecnico_a_favor_contribuyente']);

        $pos = $r['posicion_mensual'];
        $this->assertSame('0.00', $pos['saldo_a_pagar']);
        $this->assertSame('278409.79', $pos['saldo_libre_disponibilidad_periodo']);
    }

    public function test_saldo_a_pagar_cuando_el_debito_supera_al_credito(): void
    {
        // Débito 1000 − crédito 300, sin arrastres → 700 a favor de ARCA = a pagar.
        $r = $this->calc->calcular('1000.00', '300.00');

        $this->assertSame('700.00', $r['determinacion_impuesto']['saldo_tecnico_a_favor_arca']);
        $this->assertSame('0.00', $r['determinacion_impuesto']['saldo_tecnico_a_favor_contribuyente']);
        $this->assertSame('700.00', $r['posicion_mensual']['saldo_a_pagar']);
        $this->assertSame('0.00', $r['posicion_mensual']['saldo_libre_disponibilidad_periodo']);
    }

    public function test_retenciones_y_libre_disponibilidad_reducen_el_saldo_a_pagar(): void
    {
        // 700 a favor de ARCA, menos 200 de libre disp. anterior y 150 de retenciones → 350 a pagar.
        $r = $this->calc->calcular('1000.00', '300.00', '0', '200.00', '150.00');

        $this->assertSame('350.00', $r['posicion_mensual']['saldo_a_pagar']);
        $this->assertSame('0.00', $r['posicion_mensual']['saldo_libre_disponibilidad_periodo']);
    }

    public function test_excedente_de_pagos_a_cuenta_genera_libre_disponibilidad(): void
    {
        // 700 a favor de ARCA, menos 1000 de retenciones → quedan 300 de libre disponibilidad.
        $r = $this->calc->calcular('1000.00', '300.00', '0', '0', '1000.00');

        $this->assertSame('0.00', $r['posicion_mensual']['saldo_a_pagar']);
        $this->assertSame('300.00', $r['posicion_mensual']['saldo_libre_disponibilidad_periodo']);
    }
}
