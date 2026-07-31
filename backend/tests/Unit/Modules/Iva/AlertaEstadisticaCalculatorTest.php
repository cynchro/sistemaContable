<?php

namespace Tests\Unit\Modules\Iva;

use App\Modules\Iva\Calc\AlertaEstadisticaCalculator;
use Tests\Unit\UnitTestCase;

class AlertaEstadisticaCalculatorTest extends UnitTestCase
{
    private AlertaEstadisticaCalculator $calc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calc = new AlertaEstadisticaCalculator();
    }

    public function test_sin_historial_suficiente_no_evalua(): void
    {
        $this->assertNull($this->calc->evaluar('1000.00', []));
        $this->assertNull($this->calc->evaluar('1000.00', ['1000.00', '1000.00']));
    }

    public function test_promedio_cero_no_evalua(): void
    {
        $this->assertNull($this->calc->evaluar('0.00', ['0.00', '0.00', '0.00']));
    }

    public function test_desvio_dentro_del_umbral_no_dispara_alerta(): void
    {
        // Promedio 1000, actual 1200 → 20% de desvío, por debajo del umbral (30%).
        $r = $this->calc->evaluar('1200.00', ['1000.00', '1000.00', '1000.00']);
        $this->assertNotNull($r);
        $this->assertSame('1000.00', $r['promedio']);
        $this->assertSame('20.00', $r['desvio_pct']);
        $this->assertFalse($r['alerta']);
    }

    public function test_desvio_por_encima_del_umbral_dispara_alerta(): void
    {
        // Promedio 1000, actual 1500 → 50% de desvío, por encima del umbral (30%): posible bien
        // de uso o movimiento inusual (documento §7).
        $r = $this->calc->evaluar('1500.00', ['1000.00', '1000.00', '1000.00']);
        $this->assertNotNull($r);
        $this->assertSame('50.00', $r['desvio_pct']);
        $this->assertTrue($r['alerta']);
    }

    public function test_desvio_hacia_abajo_tambien_dispara_alerta(): void
    {
        // Una caída fuerte (ej. -60%) es igual de "fuera de lo habitual" que una suba.
        $r = $this->calc->evaluar('400.00', ['1000.00', '1000.00', '1000.00']);
        $this->assertNotNull($r);
        $this->assertSame('60.00', $r['desvio_pct']);
        $this->assertTrue($r['alerta']);
    }

    public function test_promedio_con_historial_dispar(): void
    {
        $r = $this->calc->evaluar('1000.00', ['500.00', '1000.00', '1500.00']);
        $this->assertNotNull($r);
        $this->assertSame('1000.00', $r['promedio']);
        $this->assertSame('0.00', $r['desvio_pct']);
        $this->assertFalse($r['alerta']);
    }
}
