<?php

namespace Tests\Feature;

/**
 * Requerimientos (módulo Fiscal): CRUD por empresa, con plazos y estado validado.
 */
class RequerimientoTest extends FeatureTestCase
{
    /** @return array{auth: array<string,mixed>, empresaId: int} */
    private function escenario(): array
    {
        $auth      = $this->bearer($this->actingAsUser()['token']);
        $empresaId = (int) $this->postJson('/empresas', ['nombre' => 'Contrib SA'], $auth)['json']['data']['id'];

        return ['auth' => $auth, 'empresaId' => $empresaId];
    }

    public function test_crud_y_estado(): void
    {
        ['auth' => $auth, 'empresaId' => $e] = $this->escenario();

        $created = $this->postJson("/empresas/{$e}/requerimientos", [
            'requerimiento'      => 'Presentar comprobantes de IVA 2025',
            'ente'               => 'AFIP',
            'formulario'         => 'F.8400',
            'fecha_notificacion' => '2026-06-01',
            'plazo_dias'         => 15,
        ], $auth);
        $this->assertSame(201, $created['status']);
        $id = (int) $created['json']['data']['id'];
        $this->assertSame('notificado', $created['json']['data']['estado']);

        $this->assertCount(1, $this->getJson("/empresas/{$e}/requerimientos", $auth)['json']['data']);

        $upd = $this->putJson("/empresas/{$e}/requerimientos/{$id}", [
            'requerimiento'      => 'Presentar comprobantes de IVA 2025',
            'estado'             => 'presentado',
            'fecha_presentacion' => '2026-06-10',
        ], $auth);
        $this->assertSame(200, $upd['status']);
        $this->assertSame('presentado', $upd['json']['data']['estado']);

        $this->assertSame(200, $this->deleteJson("/empresas/{$e}/requerimientos/{$id}", $auth)['status']);
    }

    public function test_estado_invalido_y_requerimiento_obligatorio(): void
    {
        ['auth' => $auth, 'empresaId' => $e] = $this->escenario();

        $sinTexto = $this->postJson("/empresas/{$e}/requerimientos", ['ente' => 'AFIP'], $auth);
        $this->assertSame(422, $sinTexto['status']);
        $this->assertArrayHasKey('requerimiento', $sinTexto['json']['errors']);

        $estadoMalo = $this->postJson("/empresas/{$e}/requerimientos", [
            'requerimiento' => 'X', 'estado' => 'cualquiera',
        ], $auth);
        $this->assertSame(422, $estadoMalo['status']);
        $this->assertArrayHasKey('estado', $estadoMalo['json']['errors']);
    }

    public function test_aislamiento_por_tenant(): void
    {
        ['empresaId' => $e] = $this->escenario();
        $bobAuth = $this->bearer($this->actingAsUser()['token']);

        $this->assertSame(404, $this->getJson("/empresas/{$e}/requerimientos", $bobAuth)['status']);
    }
}
