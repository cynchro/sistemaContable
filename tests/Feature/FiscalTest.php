<?php

namespace Tests\Feature;

/**
 * Módulo Fiscal (de sistemaCuarto): tributos (catálogo por tenant) y vencimientos
 * (obligaciones por empresa con workflow de estados).
 */
class FiscalTest extends FeatureTestCase
{
    /** @return array{auth: array<string,mixed>, empresaId: int} */
    private function escenario(): array
    {
        $auth      = $this->bearer($this->actingAsUser()['token']);
        $empresaId = (int) $this->postJson('/empresas', ['nombre' => 'Contrib SA'], $auth)['json']['data']['id'];

        return ['auth' => $auth, 'empresaId' => $empresaId];
    }

    public function test_crud_tributos_por_tenant(): void
    {
        ['auth' => $auth, 'empresaId' => $e] = $this->escenario();

        $created = $this->postJson('/tributos', ['nombre' => 'IVA', 'tipo_persona' => 'juridica'], $auth);
        $this->assertSame(201, $created['status']);
        $id = (int) $created['json']['data']['id'];

        // Hijo jerárquico.
        $hijo = $this->postJson('/tributos', ['nombre' => 'IVA Mensual', 'parent_id' => $id], $auth);
        $this->assertSame(201, $hijo['status']);
        $this->assertSame($id, (int) $hijo['json']['data']['parent_id']);

        $this->assertCount(2, $this->getJson('/tributos', $auth)['json']['data']);

        $bobAuth = $this->bearer($this->actingAsUser()['token']);
        $this->assertCount(0, $this->getJson('/tributos', $bobAuth)['json']['data']);
    }

    public function test_vencimiento_y_workflow_de_estado(): void
    {
        ['auth' => $auth, 'empresaId' => $e] = $this->escenario();

        $created = $this->postJson("/empresas/{$e}/vencimientos", [
            'titulo'            => 'IVA 06/2026',
            'agencia'           => 'AFIP',
            'tributos'          => ['IVA', 'Ganancias'],
            'fecha_vencimiento' => '2026-07-20',
        ], $auth);
        $this->assertSame(201, $created['status']);
        $id = (int) $created['json']['data']['id'];
        $this->assertSame('creado', $created['json']['data']['estado']);
        $this->assertSame(['IVA', 'Ganancias'], $created['json']['data']['tributos']);

        // Avanzar estado.
        $est = $this->putJson("/empresas/{$e}/vencimientos/{$id}/estado", [
            'estado' => 'documentacion_recibida', 'observaciones' => 'Llegó la doc',
        ], $auth);
        $this->assertSame(200, $est['status']);
        $this->assertSame('documentacion_recibida', $est['json']['data']['estado']);

        // Estado inválido → 422.
        $bad = $this->putJson("/empresas/{$e}/vencimientos/{$id}/estado", ['estado' => 'cualquiera'], $auth);
        $this->assertSame(422, $bad['status']);

        $this->assertSame(200, $this->deleteJson("/empresas/{$e}/vencimientos/{$id}", $auth)['status']);
    }

    public function test_vencimiento_requiere_titulo_y_aisla_por_tenant(): void
    {
        ['auth' => $auth, 'empresaId' => $e] = $this->escenario();

        $resp = $this->postJson("/empresas/{$e}/vencimientos", ['agencia' => 'AFIP'], $auth);
        $this->assertSame(422, $resp['status']);
        $this->assertArrayHasKey('titulo', $resp['json']['errors']);

        $bobAuth = $this->bearer($this->actingAsUser()['token']);
        $this->assertSame(404, $this->getJson("/empresas/{$e}/vencimientos", $bobAuth)['status']);
    }
}
