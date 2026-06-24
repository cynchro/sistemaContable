<?php

namespace Tests\Feature;

/**
 * Grupo familiar del empleado (módulo Sueldos): CRUD anidado bajo empleado.
 */
class FamiliarCrudTest extends FeatureTestCase
{
    /** @return array{auth: array<string,mixed>, empresaId: int, empleadoId: int} */
    private function escenario(): array
    {
        $auth      = $this->bearer($this->actingAsUser()['token']);
        $empresaId = (int) $this->postJson('/empresas', ['nombre' => 'Fam SA'], $auth)['json']['data']['id'];
        $empResp   = $this->postJson("/empresas/{$empresaId}/empleados", ['nombres' => 'Juan'], $auth);
        $empleadoId = (int) $empResp['json']['data']['id'];

        return ['auth' => $auth, 'empresaId' => $empresaId, 'empleadoId' => $empleadoId];
    }

    public function test_crud_completo(): void
    {
        ['auth' => $auth, 'empresaId' => $e, 'empleadoId' => $emp] = $this->escenario();
        $base = "/empresas/{$e}/empleados/{$emp}/familiares";

        $created = $this->postJson($base, [
            'nombre' => 'Hijo 1', 'fecha_nacimiento' => '2018-05-10', 'deduce_ganancias' => 'S',
        ], $auth);
        $this->assertSame(201, $created['status']);
        $id = (int) $created['json']['data']['id'];

        $this->assertCount(1, $this->getJson($base, $auth)['json']['data']);

        $updated = $this->putJson("{$base}/{$id}", ['nombre' => 'Hijo Uno', 'escolarizado' => 'S'], $auth);
        $this->assertSame(200, $updated['status']);
        $this->assertSame('Hijo Uno', $updated['json']['data']['nombre']);

        $this->assertSame(200, $this->deleteJson("{$base}/{$id}", $auth)['status']);
        $this->assertSame(404, $this->getJson("{$base}/{$id}", $auth)['status']);
    }

    public function test_crear_sin_nombre_falla(): void
    {
        ['auth' => $auth, 'empresaId' => $e, 'empleadoId' => $emp] = $this->escenario();

        $resp = $this->postJson("/empresas/{$e}/empleados/{$emp}/familiares", ['deduce_ganancias' => 'S'], $auth);

        $this->assertSame(422, $resp['status']);
        $this->assertArrayHasKey('nombre', $resp['json']['errors']);
    }

    public function test_aislamiento_por_tenant(): void
    {
        ['empresaId' => $e, 'empleadoId' => $emp] = $this->escenario();
        $bobAuth = $this->bearer($this->actingAsUser()['token']);

        $this->assertSame(404, $this->getJson("/empresas/{$e}/empleados/{$emp}/familiares", $bobAuth)['status']);
    }
}
