<?php

namespace Tests\Feature;

/**
 * Períodos (módulo Compartido): CRUD anidado bajo empresa, cerrar/abrir,
 * validación de rango de fechas y aislamiento por tenant.
 */
class PeriodoCrudTest extends FeatureTestCase
{
    /** @return array{0: array<string,mixed>, 1: int} [auth, empresaId] */
    private function empresaDe(array $user): array
    {
        $auth = $this->bearer($user['token']);
        $empresa = $this->postJson('/empresas', ['nombre' => 'ACME SA'], $auth);

        return [$auth, (int) $empresa['json']['data']['id']];
    }

    public function test_ciclo_completo_y_cerrar_abrir(): void
    {
        [$auth, $empresaId] = $this->empresaDe($this->actingAsUser());

        // Crear período
        $created = $this->postJson("/empresas/{$empresaId}/periodos", [
            'nombre'    => '2026-01',
            'fecha_ini' => '2026-01-01',
            'fecha_fin' => '2026-01-31',
        ], $auth);
        $this->assertSame(201, $created['status']);
        $id = (int) $created['json']['data']['id'];
        $this->assertSame('N', $created['json']['data']['cerrado']);

        // Listar
        $list = $this->getJson("/empresas/{$empresaId}/periodos", $auth);
        $this->assertCount(1, $list['json']['data']);

        // Cerrar
        $cerrar = $this->postJson("/empresas/{$empresaId}/periodos/{$id}/cerrar", [], $auth);
        $this->assertSame(200, $cerrar['status']);
        $this->assertSame('S', $cerrar['json']['data']['cerrado']);

        // No se puede modificar ni borrar mientras está cerrado → 409
        $edit = $this->putJson("/empresas/{$empresaId}/periodos/{$id}", ['nombre' => 'x'], $auth);
        $this->assertSame(409, $edit['status']);
        $this->assertSame(409, $this->deleteJson("/empresas/{$empresaId}/periodos/{$id}", $auth)['status']);

        // Cerrar de nuevo → 409 (ya cerrado)
        $recerrar = $this->postJson("/empresas/{$empresaId}/periodos/{$id}/cerrar", [], $auth);
        $this->assertSame(409, $recerrar['status']);

        // Abrir y luego modificar y borrar
        $this->assertSame(200, $this->postJson("/empresas/{$empresaId}/periodos/{$id}/abrir", [], $auth)['status']);
        $upd = $this->putJson("/empresas/{$empresaId}/periodos/{$id}", ['nombre' => '2026-Enero'], $auth);
        $this->assertSame(200, $upd['status']);
        $this->assertSame('2026-Enero', $upd['json']['data']['nombre']);
        $this->assertSame(200, $this->deleteJson("/empresas/{$empresaId}/periodos/{$id}", $auth)['status']);
    }

    public function test_fecha_fin_anterior_a_inicio_falla(): void
    {
        [$auth, $empresaId] = $this->empresaDe($this->actingAsUser());

        $resp = $this->postJson("/empresas/{$empresaId}/periodos", [
            'nombre'    => 'malo',
            'fecha_ini' => '2026-02-01',
            'fecha_fin' => '2026-01-01',
        ], $auth);

        $this->assertSame(422, $resp['status']);
        $this->assertArrayHasKey('fecha_fin', $resp['json']['errors']);
    }

    public function test_no_se_acceden_periodos_de_empresa_de_otro_tenant(): void
    {
        [$aliceAuth, $aliceEmpresaId] = $this->empresaDe($this->actingAsUser());
        $bobAuth = $this->bearer($this->actingAsUser()['token']);

        // Bob intenta listar los períodos de la empresa de Alice → 404 (no es suya).
        $resp = $this->getJson("/empresas/{$aliceEmpresaId}/periodos", $bobAuth);
        $this->assertSame(404, $resp['status']);
    }
}
