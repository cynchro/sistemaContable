<?php

namespace Tests\Feature;

/**
 * Conceptos de liquidación (módulo Sueldos): CRUD anidado bajo empresa, con fórmula.
 */
class ConceptoCrudTest extends FeatureTestCase
{
    /** @return array{0: array<string,mixed>, 1: int} [auth, empresaId] */
    private function empresaDe(array $user): array
    {
        $auth    = $this->bearer($user['token']);
        $empresa = $this->postJson('/empresas', ['nombre' => 'Liquida SA'], $auth);

        return [$auth, (int) $empresa['json']['data']['id']];
    }

    public function test_crud_con_formula(): void
    {
        [$auth, $empresaId] = $this->empresaDe($this->actingAsUser());

        $created = $this->postJson("/empresas/{$empresaId}/conceptos", [
            'codigo'      => '001',
            'descripcion' => 'Sueldo básico',
            'formula'     => 'BASICO/30*CAN',
            'tipo'        => 1,
            'orden'       => 1,
        ], $auth);
        $this->assertSame(201, $created['status']);
        $id = (int) $created['json']['data']['id'];
        $this->assertSame('BASICO/30*CAN', $created['json']['data']['formula']);

        $this->assertCount(1, $this->getJson("/empresas/{$empresaId}/conceptos", $auth)['json']['data']);

        $updated = $this->putJson("/empresas/{$empresaId}/conceptos/{$id}", [
            'codigo'      => '001',
            'descripcion' => 'Sueldo básico mensual',
            'formula'     => 'BASICO',
        ], $auth);
        $this->assertSame(200, $updated['status']);
        $this->assertSame('BASICO', $updated['json']['data']['formula']);

        $this->assertSame(200, $this->deleteJson("/empresas/{$empresaId}/conceptos/{$id}", $auth)['status']);
        $this->assertSame(404, $this->getJson("/empresas/{$empresaId}/conceptos/{$id}", $auth)['status']);
    }

    public function test_crear_sin_codigo_falla(): void
    {
        [$auth, $empresaId] = $this->empresaDe($this->actingAsUser());

        $resp = $this->postJson("/empresas/{$empresaId}/conceptos", ['descripcion' => 'X'], $auth);

        $this->assertSame(422, $resp['status']);
        $this->assertArrayHasKey('codigo', $resp['json']['errors']);
    }

    public function test_aislamiento_por_tenant(): void
    {
        [, $aliceEmpresaId] = $this->empresaDe($this->actingAsUser());
        $bobAuth = $this->bearer($this->actingAsUser()['token']);

        $this->assertSame(404, $this->getJson("/empresas/{$aliceEmpresaId}/conceptos", $bobAuth)['status']);
    }
}
