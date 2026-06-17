<?php

namespace Tests\Feature;

/**
 * Cálculo de vacaciones (Ley 20.744): días según antigüedad al 31/12 + importe
 * (remuneración / 25 × días).
 */
class VacacionesTest extends FeatureTestCase
{
    public function test_dias_e_importe_segun_antiguedad(): void
    {
        $auth = $this->bearer($this->actingAsUser()['token']);
        $e = (int) $this->postJson('/empresas', ['nombre' => 'Sueldos SA'], $auth)['json']['data']['id'];
        // Ingreso 2020-01-01 → al 31/12/2026 = 6 años → 21 días. Básico 25.000 → valor día 1.000.
        $emp = (int) $this->postJson("/empresas/{$e}/empleados", [
            'nombres'       => 'Juan',
            'fecha_ingreso' => '2020-01-01',
            'basico'        => '25000',
        ], $auth)['json']['data']['id'];

        $resp = $this->getJson("/empresas/{$e}/empleados/{$emp}/vacaciones?anio=2026", $auth);

        $this->assertSame(200, $resp['status']);
        $d = $resp['json']['data'];
        $this->assertSame(6, $d['antiguedad_anios']);
        $this->assertSame(21, $d['dias']);
        $this->assertSame('1000.00', $d['valor_dia']);
        $this->assertSame('21000.00', $d['importe']); // 1000 × 21
    }

    public function test_antiguedad_menor_da_14_dias(): void
    {
        $auth = $this->bearer($this->actingAsUser()['token']);
        $e = (int) $this->postJson('/empresas', ['nombre' => 'X SA'], $auth)['json']['data']['id'];
        $emp = (int) $this->postJson("/empresas/{$e}/empleados", [
            'nombres'       => 'Ana',
            'fecha_ingreso' => '2024-01-01',
            'basico'        => '25000',
        ], $auth)['json']['data']['id'];

        $resp = $this->getJson("/empresas/{$e}/empleados/{$emp}/vacaciones?anio=2026", $auth);

        $this->assertSame(200, $resp['status']);
        $this->assertSame(14, $resp['json']['data']['dias']);
    }
}
