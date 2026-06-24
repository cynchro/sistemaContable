<?php

namespace Tests\Unit\Modules\Iva;

use App\Modules\Iva\Calc\PercepcionCalculator;
use Tests\Unit\UnitTestCase;

/**
 * Cálculo de percepciones por estrategia de base (respuestas.md A2, validado contra la
 * factura de ejemplo: Perc. IVA 3% s/neto y Perc. IIBB Catamarca 2,5% s/neto).
 */
class PercepcionCalculatorTest extends UnitTestCase
{
    private PercepcionCalculator $calc;

    /** @var list<array<string, string>> Dos líneas: 1000 al 21% y 500 al 10,5%. */
    private array $lineas;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calc = new PercepcionCalculator();
        $this->lineas = [
            ['neto_gravado' => '1000.00', 'iva_alicuota' => '21.000'],
            ['neto_gravado' => '500.00',  'iva_alicuota' => '10.500'],
        ];
    }

    public function test_base_neto_gravado(): void
    {
        // IIBB Catamarca 2,5% sobre el neto total (1500) = 37.50
        $r = $this->calc->calcular([], $this->lineas, ['base_calculo' => 'neto_gravado', 'alicuota' => '2.5']);

        $this->assertSame('1500.00', $r['base']);
        $this->assertSame('37.50', $r['importe']);
    }

    public function test_base_neto_mas_impuesto_interno(): void
    {
        // Neto 1500 + imp. interno 200 = 1700; al 2,5% = 42.50
        $r = $this->calc->calcular(
            ['imp_interno' => '200.00'],
            $this->lineas,
            ['base_calculo' => 'neto_mas_imp_interno', 'alicuota' => '2.5'],
        );

        $this->assertSame('1700.00', $r['base']);
        $this->assertSame('42.50', $r['importe']);
    }

    public function test_percepcion_iva_por_tramos(): void
    {
        // 3% sobre 1000 (al 21%) + 1,5% sobre 500 (al 10,5%) = 30 + 7.50 = 37.50
        $r = $this->calc->calcular([], $this->lineas, ['base_calculo' => 'iva_percepcion']);

        $this->assertSame('37.50', $r['importe']);
    }

    public function test_importe_explicito_tiene_prioridad(): void
    {
        $r = $this->calc->calcular([], $this->lineas, [
            'base_calculo' => 'neto_gravado', 'alicuota' => '2.5', 'importe' => '99.99',
        ]);

        $this->assertSame('99.99', $r['importe']);
    }

    public function test_base_explicita_pisa_la_estrategia(): void
    {
        // Base informada 1000 (no el neto 1500) × 3% = 30.00
        $r = $this->calc->calcular([], $this->lineas, [
            'base_calculo' => 'neto_gravado', 'alicuota' => '3', 'base' => '1000.00',
        ]);

        $this->assertSame('1000.00', $r['base']);
        $this->assertSame('30.00', $r['importe']);
    }
}
