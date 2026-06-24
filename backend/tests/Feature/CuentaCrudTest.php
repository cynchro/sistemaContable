<?php

namespace Tests\Feature;

/**
 * Cuentas (plan de cuentas, módulo Compartido): CRUD anidado bajo empresa,
 * acotado al tenant dueño de la empresa.
 */
class CuentaCrudTest extends FeatureTestCase
{
    /** @return array{0: array<string,mixed>, 1: int} [auth, empresaId] */
    private function empresaDe(array $user): array
    {
        $auth    = $this->bearer($user['token']);
        $empresa = $this->postJson('/empresas', ['nombre' => 'ACME SA'], $auth);

        return [$auth, (int) $empresa['json']['data']['id']];
    }

    public function test_crud_completo(): void
    {
        [$auth, $empresaId] = $this->empresaDe($this->actingAsUser());

        $created = $this->postJson("/empresas/{$empresaId}/cuentas", [
            'codigo' => '1.1.01',
            'nombre' => 'Caja',
        ], $auth);
        $this->assertSame(201, $created['status']);
        $id = (int) $created['json']['data']['id'];

        $this->assertCount(1, $this->getJson("/empresas/{$empresaId}/cuentas", $auth)['json']['data']);

        $updated = $this->putJson("/empresas/{$empresaId}/cuentas/{$id}", [
            'codigo' => '1.1.02',
            'nombre' => 'Banco',
        ], $auth);
        $this->assertSame(200, $updated['status']);
        $this->assertSame('Banco', $updated['json']['data']['nombre']);

        $this->assertSame(200, $this->deleteJson("/empresas/{$empresaId}/cuentas/{$id}", $auth)['status']);
        $this->assertSame(404, $this->getJson("/empresas/{$empresaId}/cuentas/{$id}", $auth)['status']);
    }

    public function test_crear_sin_nombre_falla(): void
    {
        [$auth, $empresaId] = $this->empresaDe($this->actingAsUser());

        $resp = $this->postJson("/empresas/{$empresaId}/cuentas", ['codigo' => 'x'], $auth);

        $this->assertSame(422, $resp['status']);
        $this->assertArrayHasKey('nombre', $resp['json']['errors']);
    }

    public function test_no_se_acceden_cuentas_de_empresa_de_otro_tenant(): void
    {
        [, $aliceEmpresaId] = $this->empresaDe($this->actingAsUser());
        $bobAuth = $this->bearer($this->actingAsUser()['token']);

        $resp = $this->getJson("/empresas/{$aliceEmpresaId}/cuentas", $bobAuth);
        $this->assertSame(404, $resp['status']);
    }
}
