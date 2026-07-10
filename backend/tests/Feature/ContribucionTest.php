<?php

namespace Tests\Feature;

/**
 * Contribuciones patronales (módulo Sueldos): definiciones por empresa y cálculo
 * sobre la base del recibo liquidado.
 */
class ContribucionTest extends FeatureTestCase
{
    /** @return array{auth: array<string,mixed>, empresaId: int} */
    private function escenario(): array
    {
        $auth      = $this->bearer($this->actingAsUser()['token']);
        $empresaId = (int) $this->postJson('/empresas', ['nombre' => 'Contrib SA'], $auth)['json']['data']['id'];

        return ['auth' => $auth, 'empresaId' => $empresaId];
    }

    public function test_calcula_contribuciones_sobre_recibo(): void
    {
        ['auth' => $auth, 'empresaId' => $e] = $this->escenario();

        $empResp = $this->postJson("/empresas/{$e}/empleados", ['nombres' => 'Juan', 'basico' => '1000000'], $auth);
        $empId = (int) $empResp['json']['data']['id'];

        $cResp = $this->postJson("/empresas/{$e}/conceptos", [
            'codigo' => '001', 'descripcion' => 'Basico', 'formula' => 'BASICO', 'tipo' => 1,
        ], $auth);
        $c1 = (int) $cResp['json']['data']['id'];

        // Contribución patronal: 12,35% sobre remunerativo.
        $this->postJson("/empresas/{$e}/contribuciones", [
            'descripcion' => 'Jubilación patronal', 'porcentaje' => '12.350',
        ], $auth);

        $liqResp = $this->postJson("/empresas/{$e}/liquidaciones", ['periodo_liquidado' => '2026-01'], $auth);
        $liqId = (int) $liqResp['json']['data']['id'];
        $base = "/empresas/{$e}/liquidaciones/{$liqId}/empleados/{$empId}";

        // Liquidar el recibo (genera base remunerativa 1.000.000).
        $this->putJson("{$base}/novedades", ['novedades' => [['concepto_id' => $c1, 'cantidad' => 1]]], $auth);
        $this->postJson("{$base}/liquidar", [], $auth);

        // Calcular contribuciones.
        $resp = $this->postJson("{$base}/contribuciones", [], $auth);
        $this->assertSame(200, $resp['status']);
        $d = $resp['json']['data'];
        $this->assertSame('1000000.00', $d['lineas'][0]['base_imponible']);
        $this->assertSame('123500.00', $d['lineas'][0]['importe_total']);
        $this->assertSame('123500.00', $d['total']);

        // Persistido.
        $this->assertCount(1, $this->getJson("{$base}/contribuciones", $auth)['json']['data']['lineas']);
    }

    public function test_detraccion_de_empresa_reduce_la_base(): void
    {
        // B6: la detracción (monto de la config de empresa) resta de la base de las
        // contribuciones marcadas con aplica_detraccion.
        ['auth' => $auth, 'empresaId' => $e] = $this->escenario();

        $this->putJson("/empresas/{$e}/sueldos/config", ['detraccion_monto' => '300000'], $auth);

        $empId = (int) $this->postJson(
            "/empresas/{$e}/empleados",
            ['nombres' => 'Ana', 'basico' => '1000000'],
            $auth,
        )['json']['data']['id'];
        $c1 = (int) $this->postJson("/empresas/{$e}/conceptos", [
            'codigo' => '001', 'descripcion' => 'Basico', 'formula' => 'BASICO', 'tipo' => 1,
        ], $auth)['json']['data']['id'];
        $this->postJson("/empresas/{$e}/contribuciones", [
            'descripcion' => 'Jub (SIPA)', 'porcentaje' => '10', 'aplica_detraccion' => 'S',
        ], $auth);

        $liqId = (int) $this->postJson(
            "/empresas/{$e}/liquidaciones",
            ['periodo_liquidado' => '2026-01'],
            $auth,
        )['json']['data']['id'];
        $base = "/empresas/{$e}/liquidaciones/{$liqId}/empleados/{$empId}";
        $this->putJson("{$base}/novedades", ['novedades' => [['concepto_id' => $c1, 'cantidad' => 1]]], $auth);
        $this->postJson("{$base}/liquidar", [], $auth);

        $d = $this->postJson("{$base}/contribuciones", [], $auth)['json']['data'];
        // base 1.000.000 − 300.000 = 700.000 → 10% = 70.000
        $this->assertSame('700000.00', $d['lineas'][0]['base_imponible']);
        $this->assertSame('300000.00', $d['lineas'][0]['detraccion']);
        $this->assertSame('70000.00', $d['lineas'][0]['importe_total']);
    }

    public function test_crud_definiciones_y_aislamiento(): void
    {
        ['auth' => $auth, 'empresaId' => $e] = $this->escenario();

        $created = $this->postJson("/empresas/{$e}/contribuciones", [
            'descripcion' => 'ART', 'importe_fijo' => '1624.00',
        ], $auth);
        $this->assertSame(201, $created['status']);

        $bobAuth = $this->bearer($this->actingAsUser()['token']);
        $this->assertSame(404, $this->getJson("/empresas/{$e}/contribuciones", $bobAuth)['status']);
    }
}
