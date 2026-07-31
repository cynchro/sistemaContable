<?php

namespace Tests\Feature;

/**
 * Motor de alertas estadísticas v1 (documento "Satélite Visual IVA" §7): compara el total del
 * último período de una empresa contra el promedio de sus períodos anteriores. La mecánica pura
 * se prueba en AlertaEstadisticaCalculatorTest; acá se prueba el endpoint end-to-end con
 * comprobantes reales.
 */
class AlertaEstadisticaTest extends FeatureTestCase
{
    private function crearPeriodoConCompra(array $auth, int $empresaId, string $mes, string $netoGravado): int
    {
        $periodoId = (int) $this->postJson("/empresas/{$empresaId}/periodos", [
            'nombre' => $mes, 'fecha_ini' => "{$mes}-01", 'fecha_fin' => "{$mes}-28",
        ], $auth)['json']['data']['id'];

        // Número único por comprobante (proveedor+PV+número es único por empresa, no por
        // período): se deriva del mes para no colisionar entre los distintos períodos del test.
        $numero = substr($mes, -2);
        $resp   = $this->postJson("/empresas/{$empresaId}/periodos/{$periodoId}/compras", [
            'fecha' => "{$mes}-15", 'cuit' => '30111111118', 'proveedor_nombre' => 'Proveedor Ocasional',
            'letra' => 'A', 'punto_venta' => '1', 'numero' => $numero,
            'discriminaciones' => [['neto_gravado' => $netoGravado, 'iva_alicuota' => '21.000']],
        ], $auth);
        $this->assertSame(201, $resp['status'], (string) json_encode($resp['json']));

        return $periodoId;
    }

    public function test_detecta_desvio_por_encima_del_umbral(): void
    {
        $auth      = $this->bearer($this->actingAsUser()['token']);
        $empresaId = (int) $this->postJson('/empresas', ['nombre' => 'Alertas SA'], $auth)['json']['data']['id'];

        // 3 períodos históricos con neto 1000 (total 1210) + el actual con un salto a 3000
        // (total 3630) — desvío muy por encima del umbral (30%).
        $this->crearPeriodoConCompra($auth, $empresaId, '2026-01', '1000.00');
        $this->crearPeriodoConCompra($auth, $empresaId, '2026-02', '1000.00');
        $this->crearPeriodoConCompra($auth, $empresaId, '2026-03', '1000.00');
        $this->crearPeriodoConCompra($auth, $empresaId, '2026-04', '3000.00');

        $resp = $this->getJson('/alertas', $auth);
        $this->assertSame(200, $resp['status']);

        $compras = array_values(array_filter(
            $resp['json']['data'],
            static fn (array $a): bool => $a['empresa_id'] === $empresaId && $a['tipo'] === 'compras',
        ));
        $this->assertCount(1, $compras);
        $this->assertTrue($compras[0]['alerta']);
        $this->assertSame('1210.00', $compras[0]['promedio']);
        $this->assertSame('3630.00', $compras[0]['actual']);
    }

    public function test_sin_desvio_no_dispara_alerta(): void
    {
        $auth      = $this->bearer($this->actingAsUser()['token']);
        $empresaId = (int) $this->postJson('/empresas', ['nombre' => 'Alertas Estable SA'], $auth)
            ['json']['data']['id'];

        $this->crearPeriodoConCompra($auth, $empresaId, '2026-01', '1000.00');
        $this->crearPeriodoConCompra($auth, $empresaId, '2026-02', '1000.00');
        $this->crearPeriodoConCompra($auth, $empresaId, '2026-03', '1000.00');
        $this->crearPeriodoConCompra($auth, $empresaId, '2026-04', '1050.00');

        $resp = $this->getJson('/alertas', $auth);
        $compras = array_values(array_filter(
            $resp['json']['data'],
            static fn (array $a): bool => $a['empresa_id'] === $empresaId && $a['tipo'] === 'compras',
        ));
        $this->assertCount(1, $compras);
        $this->assertFalse($compras[0]['alerta']);
    }

    public function test_sin_historial_suficiente_no_aparece(): void
    {
        $auth      = $this->bearer($this->actingAsUser()['token']);
        $empresaId = (int) $this->postJson('/empresas', ['nombre' => 'Empresa Nueva SA'], $auth)
            ['json']['data']['id'];

        $this->crearPeriodoConCompra($auth, $empresaId, '2026-01', '1000.00');
        $this->crearPeriodoConCompra($auth, $empresaId, '2026-02', '5000.00');

        $resp = $this->getJson('/alertas', $auth);
        $deEstaEmpresa = array_filter(
            $resp['json']['data'],
            static fn (array $a): bool => $a['empresa_id'] === $empresaId,
        );
        $this->assertCount(0, $deEstaEmpresa);
    }

    public function test_no_mezcla_empresas_de_otro_tenant(): void
    {
        $alice = $this->actingAsUser();
        $bob   = $this->actingAsUser();
        $aliceAuth = $this->bearer($alice['token']);
        $bobAuth   = $this->bearer($bob['token']);

        $empresaAlice = (int) $this->postJson('/empresas', ['nombre' => 'Empresa Alice'], $aliceAuth)
            ['json']['data']['id'];
        $this->crearPeriodoConCompra($aliceAuth, $empresaAlice, '2026-01', '1000.00');
        $this->crearPeriodoConCompra($aliceAuth, $empresaAlice, '2026-02', '1000.00');
        $this->crearPeriodoConCompra($aliceAuth, $empresaAlice, '2026-03', '1000.00');
        $this->crearPeriodoConCompra($aliceAuth, $empresaAlice, '2026-04', '5000.00');

        $resp = $this->getJson('/alertas', $bobAuth);
        $this->assertSame(200, $resp['status']);
        $deAlice = array_filter(
            $resp['json']['data'],
            static fn (array $a): bool => $a['empresa_id'] === $empresaAlice,
        );
        $this->assertCount(0, $deAlice);
    }
}
