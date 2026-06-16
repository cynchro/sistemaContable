<?php

namespace Tests\Feature;

/**
 * Módulo Honorarios (de sistemaCuarto): catálogos (servicios, factores) y documento
 * de honorarios por empresa con cálculo uc × valor_uc × factor × cantidad.
 */
class HonorariosTest extends FeatureTestCase
{
    /** @return array{auth: array<string,mixed>, empresaId: int} */
    private function escenario(): array
    {
        $auth      = $this->bearer($this->actingAsUser()['token']);
        $empresaId = (int) $this->postJson('/empresas', ['nombre' => 'Estudio Cliente'], $auth)['json']['data']['id'];

        return ['auth' => $auth, 'empresaId' => $empresaId];
    }

    public function test_honorario_calcula_por_servicio_y_complejidad(): void
    {
        ['auth' => $auth, 'empresaId' => $e] = $this->escenario();

        $servResp = $this->postJson('/servicios', ['descripcion' => 'Balance anual', 'uc' => '10'], $auth);
        $servId = (int) $servResp['json']['data']['id'];
        $this->postJson('/factores-complejidad', ['nivel' => 'alta', 'factor' => '1.5'], $auth);

        // valor_uc 1000; 1 servicio de 10 UC, complejidad alta (1.5), cantidad 2 → 10*1000*1.5*2 = 30000
        $resp = $this->postJson("/empresas/{$e}/honorarios", [
            'valor_uc' => '1000',
            'fecha'    => '2026-06-30',
            'lineas'   => [
                ['servicio_id' => $servId, 'complejidad' => 'alta', 'cantidad' => 2],
            ],
        ], $auth);

        $this->assertSame(201, $resp['status']);
        $d = $resp['json']['data'];
        $this->assertSame('30000.00', $d['total']);
        $this->assertSame('30000.00', $d['lineas'][0]['importe']);
        $this->assertSame('1.500', $d['lineas'][0]['factor']);
        $this->assertSame('Balance anual', $d['lineas'][0]['descripcion']);
    }

    public function test_complejidad_inexistente_usa_factor_1(): void
    {
        ['auth' => $auth, 'empresaId' => $e] = $this->escenario();
        $serv = $this->postJson('/servicios', ['descripcion' => 'Trámite', 'uc' => '5'], $auth);
        $servId = (int) $serv['json']['data']['id'];

        $resp = $this->postJson("/empresas/{$e}/honorarios", [
            'valor_uc' => '1000',
            'lineas'   => [['servicio_id' => $servId]],
        ], $auth);

        $this->assertSame('5000.00', $resp['json']['data']['total']); // 5*1000*1*1
    }

    public function test_servicio_de_otro_tenant_da_404(): void
    {
        ['auth' => $auth, 'empresaId' => $e] = $this->escenario();
        $otroAuth = $this->bearer($this->actingAsUser()['token']);
        $ajeno = $this->postJson('/servicios', ['descripcion' => 'Ajeno', 'uc' => '1'], $otroAuth);
        $servAjeno = (int) $ajeno['json']['data']['id'];

        $resp = $this->postJson("/empresas/{$e}/honorarios", [
            'valor_uc' => '1000', 'lineas' => [['servicio_id' => $servAjeno]],
        ], $auth);

        $this->assertSame(404, $resp['status']);
    }
}
