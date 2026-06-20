<?php

namespace Tests\Unit\Modules\Iva;

use App\Modules\Iva\Calc\IvaComprobanteCalculator;
use Tests\Unit\UnitTestCase;

class IvaComprobanteCalculatorTest extends UnitTestCase
{
    private IvaComprobanteCalculator $calc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calc = new IvaComprobanteCalculator();
    }

    public function test_una_linea_al_21(): void
    {
        $r = $this->calc->calcular([], [
            ['neto_gravado' => '1000.00', 'iva_alicuota' => '21.000'],
        ]);

        $this->assertSame('210.00', $r['iva']);
        $this->assertSame('1000.00', $r['neto_gravado']);
        $this->assertSame('1210.00', $r['total']);
        $this->assertSame('210.00', $r['lineas'][0]['iva_importe']);
    }

    public function test_dos_alicuotas(): void
    {
        $r = $this->calc->calcular([], [
            ['neto_gravado' => '1000.00', 'iva_alicuota' => '21.000'],
            ['neto_gravado' => '500.00',  'iva_alicuota' => '10.500'],
        ]);

        $this->assertSame('1500.00', $r['neto_gravado']);
        $this->assertSame('262.50', $r['iva']); // 210 + 52.50
        $this->assertSame('1762.50', $r['total']);
    }

    public function test_suma_importes_redondeados_por_linea(): void
    {
        // 333.33 * 21% = 69.9993 → 70.00 por línea; dos líneas → 140.00
        $r = $this->calc->calcular([], [
            ['neto_gravado' => '333.33', 'iva_alicuota' => '21.000'],
            ['neto_gravado' => '333.33', 'iva_alicuota' => '21.000'],
        ]);

        $this->assertSame('70.00', $r['lineas'][0]['iva_importe']);
        $this->assertSame('140.00', $r['iva']);
        $this->assertSame('806.66', $r['total']); // 666.66 neto + 140 iva
    }

    public function test_incluye_no_gravado_exento_e_impuesto_interno(): void
    {
        $r = $this->calc->calcular(
            ['neto_no_grav' => '100.00', 'exento' => '50.00', 'imp_interno' => '30.00'],
            [['neto_gravado' => '1000.00', 'iva_alicuota' => '21.000']],
        );

        // 100 + 50 + 30 + 1000 + 210 = 1390
        $this->assertSame('1390.00', $r['total']);
    }

    public function test_iva_inc(): void
    {
        $r = $this->calc->calcular([], [
            ['neto_gravado' => '1000.00', 'iva_alicuota' => '21.000', 'iva_inc_alicuota' => '3.000'],
        ]);

        $this->assertSame('210.00', $r['iva']);
        $this->assertSame('30.00', $r['iva_inc']);
        $this->assertSame('1240.00', $r['total']); // 1000 + 210 + 30
    }

    public function test_sin_lineas_da_cero(): void
    {
        $r = $this->calc->calcular([], []);

        $this->assertSame('0.00', $r['neto_gravado']);
        $this->assertSame('0.00', $r['iva']);
        $this->assertSame('0.00', $r['total']);
    }

    public function test_percepciones_integran_el_total(): void
    {
        // 1000 neto + 210 IVA = 1210; + percepciones 37.50 + 7.50 = 1255.00
        $r = $this->calc->calcular(
            [],
            [['neto_gravado' => '1000.00', 'iva_alicuota' => '21.000']],
            [['importe' => '37.50'], ['importe' => '7.50']],
        );

        $this->assertSame('45.00', $r['percepciones']);
        $this->assertSame('1255.00', $r['total']);
    }
}
