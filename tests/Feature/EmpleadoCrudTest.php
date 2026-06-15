<?php

namespace Tests\Feature;

/**
 * Legajo de empleados (módulo Sueldos): CRUD anidado bajo empresa, acotado al
 * tenant dueño de la empresa.
 */
class EmpleadoCrudTest extends FeatureTestCase
{
    /** @return array{0: array<string,mixed>, 1: int} [auth, empresaId] */
    private function empresaDe(array $user): array
    {
        $auth    = $this->bearer($user['token']);
        $empresa = $this->postJson('/empresas', ['nombre' => 'Empleadora SA'], $auth);

        return [$auth, (int) $empresa['json']['data']['id']];
    }

    public function test_crud_completo(): void
    {
        [$auth, $empresaId] = $this->empresaDe($this->actingAsUser());

        $created = $this->postJson("/empresas/{$empresaId}/empleados", [
            'nombres'         => 'Juan',
            'primer_apellido' => 'Pérez',
            'cuil'            => '20111111112',
            'legajo'          => 1,
            'basico'          => '850000.00',
        ], $auth);
        $this->assertSame(201, $created['status']);
        $id = (int) $created['json']['data']['id'];
        $this->assertSame('Juan', $created['json']['data']['nombres']);

        $this->assertCount(1, $this->getJson("/empresas/{$empresaId}/empleados", $auth)['json']['data']);

        $updated = $this->putJson("/empresas/{$empresaId}/empleados/{$id}", [
            'nombres'         => 'Juan Carlos',
            'primer_apellido' => 'Pérez',
            'activo'          => 'N',
        ], $auth);
        $this->assertSame(200, $updated['status']);
        $this->assertSame('Juan Carlos', $updated['json']['data']['nombres']);
        $this->assertSame('N', $updated['json']['data']['activo']);

        $this->assertSame(200, $this->deleteJson("/empresas/{$empresaId}/empleados/{$id}", $auth)['status']);
        $this->assertSame(404, $this->getJson("/empresas/{$empresaId}/empleados/{$id}", $auth)['status']);
    }

    public function test_crear_sin_nombres_falla(): void
    {
        [$auth, $empresaId] = $this->empresaDe($this->actingAsUser());

        $resp = $this->postJson("/empresas/{$empresaId}/empleados", ['cuil' => '20111111112'], $auth);

        $this->assertSame(422, $resp['status']);
        $this->assertArrayHasKey('nombres', $resp['json']['errors']);
    }

    public function test_no_se_acceden_empleados_de_empresa_de_otro_tenant(): void
    {
        [, $aliceEmpresaId] = $this->empresaDe($this->actingAsUser());
        $bobAuth = $this->bearer($this->actingAsUser()['token']);

        $resp = $this->getJson("/empresas/{$aliceEmpresaId}/empleados", $bobAuth);
        $this->assertSame(404, $resp['status']);
    }
}
