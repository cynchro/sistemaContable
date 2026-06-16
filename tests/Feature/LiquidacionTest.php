<?php

namespace Tests\Feature;

/**
 * Liquidación de sueldos end-to-end: legajo → conceptos → novedades → liquidar →
 * recibo. Verifica el cálculo (con antigüedad y referencias entre conceptos) y la
 * persistencia de recibos, acotado al tenant.
 */
class LiquidacionTest extends FeatureTestCase
{
    /** @return array{auth: array<string,mixed>, empresaId: int} */
    private function escenario(): array
    {
        $auth      = $this->bearer($this->actingAsUser()['token']);
        $empresaId = (int) $this->postJson('/empresas', ['nombre' => 'Nómina SA'], $auth)['json']['data']['id'];

        return ['auth' => $auth, 'empresaId' => $empresaId];
    }

    public function test_liquidacion_completa_y_recibo(): void
    {
        ['auth' => $auth, 'empresaId' => $e] = $this->escenario();

        // Empleado con básico y 10 años de antigüedad.
        $empId = (int) $this->postJson("/empresas/{$e}/empleados", [
            'nombres'       => 'Juan',
            'basico'        => '900000.00',
            'fecha_ingreso' => '2016-01-01',
        ], $auth)['json']['data']['id'];

        // Conceptos con fórmula.
        $c1 = (int) $this->postJson("/empresas/{$e}/conceptos", [
            'codigo' => '001', 'descripcion' => 'Basico', 'formula' => 'BASICO/30*CAN', 'tipo' => 1, 'orden' => 1,
        ], $auth)['json']['data']['id'];
        $c2 = (int) $this->postJson("/empresas/{$e}/conceptos", [
            'codigo' => '110', 'descripcion' => 'Antig', 'formula' => 'BASICO*(ANTIG/100)', 'tipo' => 1, 'orden' => 2,
        ], $auth)['json']['data']['id'];
        $c3 = (int) $this->postJson("/empresas/{$e}/conceptos", [
            'codigo' => '500', 'descripcion' => 'Jub', 'formula' => '(001#+110#)*0.11', 'tipo' => 3, 'orden' => 3,
        ], $auth)['json']['data']['id'];

        // Liquidación de período.
        $liqId = (int) $this->postJson("/empresas/{$e}/liquidaciones", [
            'periodo_liquidado' => '2026-01',
            'fecha_desde'       => '2026-01-01',
            'fecha_hasta'       => '2026-01-31',
        ], $auth)['json']['data']['id'];

        // Novedades del empleado.
        $base = "/empresas/{$e}/liquidaciones/{$liqId}/empleados/{$empId}";
        $nov = $this->putJson("{$base}/novedades", ['novedades' => [
            ['concepto_id' => $c1, 'cantidad' => 30],
            ['concepto_id' => $c2, 'cantidad' => 1],
            ['concepto_id' => $c3, 'cantidad' => 1],
        ]], $auth);
        $this->assertSame(200, $nov['status']);
        $this->assertCount(3, $nov['json']['data']);

        // Ejecutar liquidación.
        $liq = $this->postJson("{$base}/liquidar", [], $auth);
        $this->assertSame(200, $liq['status']);
        $d = $liq['json']['data'];
        $this->assertSame('990000.00', $d['total_haberes']);     // 900000 + 90000
        $this->assertSame('108900.00', $d['total_descuentos']);  // (990000)*0.11
        $this->assertSame('881100.00', $d['neto']);
        $this->assertCount(3, $d['lineas']);

        // Recibo persistido.
        $recibo = $this->getJson("{$base}/recibo", $auth);
        $this->assertSame(200, $recibo['status']);
        $this->assertCount(3, $recibo['json']['data']['lineas']);
    }

    public function test_recalcular_reemplaza_recibos(): void
    {
        ['auth' => $auth, 'empresaId' => $e] = $this->escenario();
        $emp = $this->postJson("/empresas/{$e}/empleados", ['nombres' => 'Ana', 'basico' => '600000'], $auth);
        $empId = (int) $emp['json']['data']['id'];
        $c1 = (int) $this->postJson("/empresas/{$e}/conceptos", [
            'codigo' => '001', 'descripcion' => 'Basico', 'formula' => 'BASICO', 'tipo' => 1,
        ], $auth)['json']['data']['id'];
        $liqResp = $this->postJson("/empresas/{$e}/liquidaciones", ['periodo_liquidado' => '2026-02'], $auth);
        $liqId = (int) $liqResp['json']['data']['id'];
        $base = "/empresas/{$e}/liquidaciones/{$liqId}/empleados/{$empId}";

        $this->putJson("{$base}/novedades", ['novedades' => [['concepto_id' => $c1, 'cantidad' => 1]]], $auth);
        $this->postJson("{$base}/liquidar", [], $auth);
        // Recalcula → sigue habiendo 1 recibo (no se duplica).
        $this->postJson("{$base}/liquidar", [], $auth);

        $this->assertCount(1, $this->getJson("{$base}/recibo", $auth)['json']['data']['lineas']);
    }

    public function test_recibo_congela_snapshot_del_empleado(): void
    {
        ['auth' => $auth, 'empresaId' => $e] = $this->escenario();
        $emp = $this->postJson("/empresas/{$e}/empleados", ['nombres' => 'Original', 'basico' => '500000'], $auth);
        $empId = (int) $emp['json']['data']['id'];
        $c1 = (int) $this->postJson("/empresas/{$e}/conceptos", [
            'codigo' => '001', 'descripcion' => 'B', 'formula' => 'BASICO', 'tipo' => 1,
        ], $auth)['json']['data']['id'];
        $liqResp = $this->postJson("/empresas/{$e}/liquidaciones", ['periodo_liquidado' => '2026-03'], $auth);
        $liqId = (int) $liqResp['json']['data']['id'];
        $base = "/empresas/{$e}/liquidaciones/{$liqId}/empleados/{$empId}";

        $this->putJson("{$base}/novedades", ['novedades' => [['concepto_id' => $c1, 'cantidad' => 1]]], $auth);
        $this->postJson("{$base}/liquidar", [], $auth);

        // Cambiar el nombre del empleado DESPUÉS de liquidar.
        $this->putJson("/empresas/{$e}/empleados/{$empId}", ['nombres' => 'Cambiado'], $auth);

        // El recibo conserva el snapshot (nombre al momento de liquidar).
        $recibo = $this->getJson("{$base}/recibo", $auth);
        $this->assertSame('Original', $recibo['json']['data']['empleado']['nombres']);
        $this->assertSame('500000.00', $recibo['json']['data']['empleado']['basico']);
    }

    public function test_aislamiento_por_tenant(): void
    {
        ['empresaId' => $e] = $this->escenario();
        $bobAuth = $this->bearer($this->actingAsUser()['token']);

        $this->assertSame(404, $this->getJson("/empresas/{$e}/liquidaciones", $bobAuth)['status']);
    }
}
