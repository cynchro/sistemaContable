<?php

namespace Tests\Feature;

/**
 * Auditoría de operaciones de IVA: cada escritura exitosa sobre rutas del módulo se
 * registra (quién/qué/cuándo) y se lee paginada por tenant. Los GET no generan
 * registros y los writes fallidos (4xx) tampoco.
 */
class AuditoriaIvaTest extends FeatureTestCase
{
    /** @return array{auth: array<string,mixed>, empresaId: int, periodoId: int} */
    private function escenario(): array
    {
        $this->pdo->exec("INSERT INTO tipos_comprobante (id, codigo, nombre, signo) VALUES (9, 'FA', 'Factura A', 1)");
        $this->pdo->exec("INSERT INTO condiciones_iva (id, codigo, nombre) VALUES (1, 'RI', 'Responsable Inscripto')");

        $auth      = $this->bearer($this->actingAsUser()['token']);
        $empresaId = (int) $this->postJson('/empresas', ['nombre' => 'Audit SA'], $auth)['json']['data']['id'];
        $periodoId = (int) $this->postJson("/empresas/{$empresaId}/periodos", [
            'nombre' => '2026-01', 'fecha_ini' => '2026-01-01', 'fecha_fin' => '2026-01-31',
        ], $auth)['json']['data']['id'];

        return ['auth' => $auth, 'empresaId' => $empresaId, 'periodoId' => $periodoId];
    }

    public function test_una_venta_creada_queda_registrada_en_la_auditoria(): void
    {
        $ctx = $this->escenario();

        $this->postJson("/empresas/{$ctx['empresaId']}/periodos/{$ctx['periodoId']}/ventas", [
            'fecha' => '2026-01-10', 'tipo_comprobante_id' => 9, 'condicion_iva_id' => 1,
            'cliente_nombre' => 'ACME', 'letra' => 'A', 'punto_venta' => '1', 'numero' => '10',
            'discriminaciones' => [['neto_gravado' => '1000.00', 'iva_alicuota' => '21.000']],
        ], $ctx['auth']);

        $resp = $this->getJson('/iva/auditoria', $ctx['auth']);
        $this->assertSame(200, $resp['status']);

        $rows = $resp['json']['data']['results'];
        $ventas = array_values(array_filter($rows, fn ($r) => str_contains((string) $r['uri'], '/ventas')));

        $this->assertCount(1, $ventas);
        $this->assertSame('POST', $ventas[0]['metodo']);
        $this->assertSame(201, (int) $ventas[0]['status']);
        $this->assertNotNull($ventas[0]['user_id']);
        $this->assertStringContainsString('ACME', (string) $ventas[0]['datos']);
    }

    public function test_los_get_y_writes_fallidos_no_se_registran(): void
    {
        $ctx = $this->escenario();

        // Lecturas varias (no deben auditarse).
        $this->getJson("/empresas/{$ctx['empresaId']}/periodos/{$ctx['periodoId']}/ventas", $ctx['auth']);
        $this->getJson("/empresas/{$ctx['empresaId']}/periodos/{$ctx['periodoId']}/totales", $ctx['auth']);

        // Write inválido (falta fecha → 422): no debe auditarse.
        $bad = $this->postJson("/empresas/{$ctx['empresaId']}/periodos/{$ctx['periodoId']}/ventas", [
            'tipo_comprobante_id' => 9,
        ], $ctx['auth']);
        $this->assertSame(422, $bad['status']);

        $resp = $this->getJson('/iva/auditoria', $ctx['auth']);
        $this->assertSame(0, (int) $resp['json']['data']['total']);
    }
}
